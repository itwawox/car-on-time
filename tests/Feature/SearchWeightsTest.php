<?php

use App\Models\Brand;
use App\Models\Car;
use App\Models\Setting;
use App\Support\Search\SearchSettings;
use Illuminate\Support\Facades\Cache;

// Веса полей из «Умный поиск → Настройки поиска» должны влиять на оценку совпадения
function nameMatchScore(float $nameWeight): float
{
    Setting::put('search_tuning', ['weights' => ['name' => $nameWeight]]);
    SearchSettings::flush();
    Cache::flush();

    return Car::search('solaris')->raw()['results'][0]['score'];
}

it('applies search field weights from admin settings', function () {
    $brand = Brand::query()->create(['slug' => 'hyundai', 'name' => 'Hyundai']);
    Car::query()->create(['brand_id' => $brand->id, 'name' => 'Solaris', 'slug' => 'solaris', 'gearbox' => 'at', 'seats' => 5, 'status' => 'published', 'min_days' => 1]);

    expect(nameMatchScore(6))->toBeGreaterThan(nameMatchScore(1));
});
