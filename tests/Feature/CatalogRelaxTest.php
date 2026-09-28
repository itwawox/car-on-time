<?php

use App\Models\Brand;
use App\Models\Car;

// Пустая выдача каталога: вместо тупика — какое одно условие убрать и сколько машин тогда найдётся

beforeEach(function () {
    $brand = Brand::query()->create(['slug' => 'kia', 'name' => 'Kia']);
    foreach (['Rio' => 5, 'Ceed' => 5, 'Carnival' => 8] as $name => $seats) {
        $car = Car::query()->create([
            'brand_id' => $brand->id, 'name' => $name, 'slug' => strtolower($name), 'gearbox' => 'mt',
            'seats' => $seats, 'status' => 'published', 'min_days' => 1,
        ]);
        $car->prices()->create(['price' => 2000, 'days_from' => 1, 'days_to' => null]);
    }
});

it('suggests which single filter to drop and how many cars that brings back', function () {
    $this->get('/katalog?kp=at&seats=7')
        ->assertOk()
        ->assertSee('Под эти фильтры машин нет')
        ->assertSeeInOrder(['Убрать «Автомат»', '→ 1 машина'])
        ->assertSee('href="'.e(url('/katalog').'?seats=7').'"', false);
});

it('does not suggest dropping a filter that still leaves nothing', function () {
    // Без «Автомата» машин от 9 мест нет, без «от 9 мест» нет машин на автомате — подсказывать нечего
    $this->get('/katalog?kp=at&seats=9')
        ->assertOk()
        ->assertSee('Под эти фильтры машин нет')
        ->assertDontSee('Убрать «')
        ->assertSee('Сбросить фильтры');
});

it('shows no suggestions when cars are found', function () {
    $this->get('/katalog?seats=7')->assertOk()->assertSee('Найдено 1 авто')->assertDontSee('Убрать «');
});
