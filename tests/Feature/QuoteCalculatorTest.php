<?php

namespace Tests\Feature;

use App\Models\Brand;
use App\Models\Car;
use App\Models\Season;
use App\Services\QuoteCalculator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class QuoteCalculatorTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_quotes_season_days(): void
    {
        $brand = Brand::query()->create(['name' => 'Renault', 'slug' => 'renault']);
        $season = Season::query()->create([
            'name' => 'Лето',
            'starts_month' => 6,
            'starts_day' => 1,
            'ends_month' => 10,
            'ends_day' => 4,
            'sort' => 1,
        ]);
        $car = Car::query()->create([
            'brand_id' => $brand->id,
            'name' => 'Logan',
            'slug' => 'logan',
            'gearbox' => 'mt',
            'deposit' => 5000,
            'min_days' => 2,
        ]);
        $car->prices()->create([
            'season_id' => $season->id,
            'days_from' => 2,
            'days_to' => 3,
            'price' => 1400,
        ]);

        $quote = app(QuoteCalculator::class)->quote(
            $car->fresh('prices'),
            Carbon::parse('2026-07-01 10:00'),
            Carbon::parse('2026-07-04 10:00'),
        );

        $this->assertTrue($quote['ok']);
        $this->assertSame(3, $quote['days']);
        $this->assertSame(4200, $quote['total']);
    }
}
