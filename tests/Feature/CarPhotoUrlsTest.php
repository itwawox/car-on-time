<?php

use App\Models\Brand;
use App\Models\Car;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

// Абсолютные http://…/storage/… на https-странице браузер блокирует как смешанный контент

it('serves car photos with relative urls on https pages', function () {
    Storage::fake('public');
    expect(config('filesystems.disks.public.url'))->toBe('/storage');

    $brand = Brand::query()->create(['slug' => 'kia', 'name' => 'Kia']);
    $car = Car::query()->create([
        'brand_id' => $brand->id, 'name' => 'Kia Rio', 'slug' => 'kia-rio', 'gearbox' => 'at',
        'seats' => 5, 'status' => 'published', 'min_days' => 1,
    ]);
    $car->addMedia(UploadedFile::fake()->image('rio.jpg', 1600, 900))->toMediaCollection('gallery');

    $html = $this->get('https://localhost/avto/kia-rio')->assertOk()->getContent();

    expect($html)->toContain('/storage/')
        ->not->toMatch('/(src|srcset)="http:\/\//')
        ->toContain('og:image" content="https://');
});
