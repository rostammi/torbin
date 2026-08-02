<?php

namespace Tests\Unit;

use App\Services\Search\TravelIntentParser;
use PHPUnit\Framework\TestCase;

class TravelIntentParserTest extends TestCase
{
    public function test_it_understands_persian_budget_and_domestic_travel_language(): void
    {
        $intent = (new TravelIntentParser)->parse('سفر داخلی با ۴ میلیون تومن کجا میتونم برم؟');

        $this->assertTrue($intent->isRecommendation);
        $this->assertSame('domestic', $intent->region);
        $this->assertSame(4_000_000, $intent->maximumBudget);
    }

    public function test_it_understands_decimal_budgets_and_destination_region(): void
    {
        $intent = (new TravelIntentParser)->parse('برای تور استانبول زیر ۱۲.۵ میلیون چه پیشنهادی دارید؟');

        $this->assertTrue($intent->isRecommendation);
        $this->assertSame('استانبول', $intent->destination);
        $this->assertSame('foreign', $intent->region);
        $this->assertSame(12_500_000, $intent->maximumBudget);
    }

    public function test_regular_destination_search_stays_a_regular_search(): void
    {
        $intent = (new TravelIntentParser)->parse('شیراز');

        $this->assertFalse($intent->isRecommendation);
        $this->assertNull($intent->maximumBudget);
    }
}
