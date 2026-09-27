<?php

namespace Tests\Feature;

use App\Support\Search\QueryIntent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class QueryIntentTest extends TestCase
{
    use RefreshDatabase;

    public function test_intent_extracts_filters_and_leaves_text(): void
    {
        $intent = QueryIntent::parse('аренда киа рио автомат до 3к 5 мест');

        $this->assertSame(['киа', 'рио'], $intent->terms);
        $this->assertSame('at', $intent->gearbox);
        $this->assertSame(3000, $intent->priceMax);
        $this->assertSame(5, $intent->seatsMin);
    }

    public function test_intent_understands_drive_fuel_seats_words_and_cheap(): void
    {
        $intent = QueryIntent::parse('недорого полный привод дизель семиместный');

        $this->assertSame([], $intent->terms);
        $this->assertTrue($intent->cheapFirst);
        $this->assertSame('4wd', $intent->drivetrain);
        $this->assertSame('diesel', $intent->fuel);
        $this->assertSame(7, $intent->seatsMin);
    }

    public function test_price_word_without_number_is_ignored(): void
    {
        $this->assertSame(['рио'], QueryIntent::parse('рио до')->terms);
    }
}
