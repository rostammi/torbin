<?php

namespace Tests\Feature;

use App\Models\PriceAlert;
use App\Models\Tour;
use App\Services\Alerts\PriceAlertNotifier;
use App\Services\Alerts\SmsSender;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PriceAlertsTest extends TestCase
{
    use RefreshDatabase;

    public function test_visitor_can_subscribe_at_the_current_minimum_price(): void
    {
        [$tour] = $this->pricedTour();

        $this->post(route('price-alerts.store', $tour), [
            'phone' => '+98 912 345 6789',
            'consent' => '1',
        ])->assertRedirect()->assertSessionHas('success');

        $alert = PriceAlert::firstOrFail();
        $this->assertSame('09123456789', $alert->phone);
        $this->assertSame(8_000_000, $alert->target_price);
        $this->assertSame('price_drop', $alert->origin);
        $this->assertSame('pending', $alert->contact_status);
        $this->assertTrue($alert->is_active);
        $this->assertNotSame('09123456789', $alert->getRawOriginal('phone'));
    }

    public function test_alert_is_sent_only_after_a_real_price_drop_and_can_be_cancelled(): void
    {
        [$tour, $source] = $this->pricedTour();
        $token = 'test-unsubscribe-token';
        $alert = $tour->priceAlerts()->create([
            'phone' => '09123456789',
            'phone_hash' => hash_hmac('sha256', '09123456789', config('app.key')),
            'unsubscribe_token' => $token,
            'unsubscribe_token_hash' => hash('sha256', $token),
            'target_price' => 8_000_000,
            'currency' => 'تومان',
            'is_active' => true,
        ]);

        $sms = $this->mock(SmsSender::class);
        $sms->shouldReceive('send')->once()->withArgs(fn ($phone, $message) => $phone === '09123456789' && str_contains($message, '7,500,000'));

        $notifier = app(PriceAlertNotifier::class);
        $this->assertSame(0, $notifier->notifyForTour($tour));

        $source->update(['latest_price' => 7_500_000]);
        $this->assertSame(1, $notifier->notifyForTour($tour));
        $this->assertSame(7_500_000, $alert->fresh()->target_price);
        $this->assertNotNull($alert->fresh()->last_notified_at);
        $this->assertSame(0, $notifier->notifyForTour($tour));

        $this->get(route('price-alerts.unsubscribe', $token))->assertRedirect(route('tours.show', $tour));
        $this->assertFalse($alert->fresh()->is_active);
    }

    public function test_tour_page_shows_the_price_alert_form(): void
    {
        [$tour] = $this->pricedTour();

        $this->get(route('tours.show', $tour))
            ->assertOk()
            ->assertSee('خبرم کن ارزان‌تر شد')
            ->assertSee('09123456789');
    }

    public function test_visitor_can_request_a_call_when_the_page_only_has_a_contact_offer(): void
    {
        $tour = Tour::create([
            'title' => 'اقامتگاه بدون قیمت',
            'slug' => 'stay-without-price',
            'category' => 'stay',
            'description' => 'توضیحات',
            'is_active' => true,
        ]);
        $tour->priceSources()->create([
            'provider_name' => 'ارائه‌دهنده نمونه',
            'source_url' => 'https://example.com/stay',
            'buy_url' => 'https://example.com/stay',
            'extraction_type' => 'manual',
            'latest_price' => null,
            'currency' => 'تومان',
            'is_active' => true,
        ]);

        $this->get(route('stays.show', $tour))
            ->assertOk()
            ->assertSee('درخواست استعلام تلفنی')
            ->assertSee('ثبت درخواست تماس');

        $this->post(route('price-alerts.store', $tour), [
            'phone' => '۰۹۱۲۳۴۵۶۷۸۹',
            'consent' => '1',
        ])->assertRedirect()->assertSessionHas('success');

        $request = PriceAlert::sole();
        $this->assertSame('09123456789', $request->phone);
        $this->assertSame('no_price_contact', $request->origin);
        $this->assertSame('pending', $request->contact_status);
        $this->assertNull($request->target_price);
        $this->assertFalse($request->is_active);
    }

    private function pricedTour(): array
    {
        $tour = Tour::create([
            'title' => 'تور شیراز',
            'slug' => 'alert-shiraz',
            'description' => 'توضیحات',
            'is_active' => true,
        ]);
        $source = $tour->priceSources()->create([
            'provider_name' => 'آژانس نمونه',
            'source_url' => 'https://example.com/shiraz',
            'buy_url' => 'https://example.com/shiraz',
            'extraction_type' => 'manual',
            'latest_price' => 8_000_000,
            'currency' => 'تومان',
            'is_active' => true,
        ]);
        $source->agency->update(['balance' => 100_000]);

        return [$tour, $source];
    }
}
