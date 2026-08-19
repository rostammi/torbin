<?php

namespace Tests\Unit;

use App\Jobs\RecoverPriceSourceLink;
use App\Models\PriceSource;
use App\Models\Tour;
use App\Services\Outbound\DestinationLinkValidator;
use App\Services\Outbound\RejectedUrlRegistry;
use App\Services\PriceCrawler;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class RecoverPriceSourceLinkTest extends TestCase
{
    use RefreshDatabase;

    public function test_recovered_source_is_reactivated_only_after_new_link_is_validated(): void
    {
        $tour = Tour::create([
            'title' => 'تور باتومی',
            'slug' => 'recover-batumi',
            'description' => '...',
            'is_active' => true,
        ]);
        $source = $tour->priceSources()->create([
            'provider_name' => 'منبع نمونه',
            'source_url' => 'https://93.184.216.34/tours',
            'buy_url' => 'https://93.184.216.34/expired',
            'extraction_type' => 'marketplace_html',
            'selector' => 'باتومی',
            'latest_price' => 20_000_000,
            'is_active' => false,
            'last_status' => 'broken_link',
            'rejected_urls' => ['https://93.184.216.34/expired'],
        ]);
        $crawler = Mockery::mock(PriceCrawler::class);
        $crawler->shouldReceive('crawl')
            ->once()
            ->with(Mockery::on(fn ($argument) => $argument->is($source)), true)
            ->andReturnUsing(function ($argument) {
                $argument->update([
                    'buy_url' => 'https://93.184.216.34/new-offer',
                    'last_status' => 'success',
                ]);

                return true;
            });
        $validator = Mockery::mock(DestinationLinkValidator::class);
        $validator->shouldReceive('check')
            ->once()
            ->with('https://93.184.216.34/new-offer')
            ->andReturn(DestinationLinkValidator::VALID);

        (new RecoverPriceSourceLink($source->id))->handle($crawler, $validator, app(RejectedUrlRegistry::class));

        $source->refresh();
        $this->assertTrue($source->is_active);
        $this->assertSame('success', $source->last_status);
        $this->assertSame('https://93.184.216.34/new-offer', $source->buy_url);
    }

    public function test_same_rejected_url_can_never_reactivate_the_short_link(): void
    {
        $tour = Tour::create([
            'title' => 'تور باتومی',
            'slug' => 'reject-old-batumi',
            'description' => '...',
            'is_active' => true,
        ]);
        $source = $tour->priceSources()->create([
            'provider_name' => 'منبع نمونه',
            'source_url' => 'https://93.184.216.34/tours',
            'buy_url' => 'https://93.184.216.34/expired',
            'extraction_type' => 'marketplace_html',
            'selector' => 'باتومی',
            'latest_price' => 20_000_000,
            'is_active' => false,
            'last_status' => 'broken_link',
            'rejected_urls' => ['https://93.184.216.34/expired'],
        ]);
        $crawler = Mockery::mock(PriceCrawler::class);
        $crawler->shouldReceive('crawl')->times(3)->andReturn(true);
        $validator = Mockery::mock(DestinationLinkValidator::class);
        $validator->shouldNotReceive('check');

        try {
            (new RecoverPriceSourceLink($source->id))->handle($crawler, $validator, app(RejectedUrlRegistry::class));
            $this->fail('The rejected URL should not reactivate the source.');
        } catch (\RuntimeException $exception) {
            $this->assertStringContainsString('چند کاندیدا', $exception->getMessage());
        }

        $source->refresh();
        $this->assertFalse($source->is_active);
        $this->assertSame('recovery_failed', $source->last_status);
    }

    public function test_invalid_first_replacement_is_rejected_and_next_candidate_is_used_immediately(): void
    {
        $tour = Tour::create([
            'title' => 'تور مسقط',
            'slug' => 'recover-muscat-first-attempt',
            'description' => '...',
            'is_active' => true,
        ]);
        $source = $tour->priceSources()->create([
            'provider_name' => 'منبع نمونه',
            'source_url' => 'https://93.184.216.34/tours',
            'buy_url' => 'https://93.184.216.34/expired',
            'extraction_type' => 'marketplace_html',
            'selector' => 'مسقط',
            'latest_price' => 18_000_000,
            'is_active' => false,
            'last_status' => 'broken_link',
            'rejected_urls' => ['https://93.184.216.34/expired'],
        ]);
        $crawler = Mockery::mock(PriceCrawler::class);
        $crawler->shouldReceive('crawl')->twice()->andReturnUsing(function (PriceSource $argument) {
            $next = count($argument->fresh()->rejected_urls ?? []) === 1
                ? 'https://93.184.216.34/invalid-first'
                : 'https://93.184.216.34/valid-second';
            $argument->update([
                'buy_url' => $next,
                'latest_price' => 18_000_000,
                'last_status' => 'success',
            ]);

            return true;
        });
        $validator = Mockery::mock(DestinationLinkValidator::class);
        $validator->shouldReceive('check')
            ->once()
            ->with('https://93.184.216.34/invalid-first')
            ->andReturn(DestinationLinkValidator::BROKEN);
        $validator->shouldReceive('check')
            ->once()
            ->with('https://93.184.216.34/valid-second')
            ->andReturn(DestinationLinkValidator::VALID);

        (new RecoverPriceSourceLink($source->id))->handle($crawler, $validator, app(RejectedUrlRegistry::class));

        $source->refresh();
        $this->assertTrue($source->is_active);
        $this->assertSame('https://93.184.216.34/valid-second', $source->buy_url);
        $this->assertContains('https://93.184.216.34/invalid-first', $source->rejected_urls);
    }
}
