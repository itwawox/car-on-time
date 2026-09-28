<?php

use App\Models\Brand;
use App\Models\Car;
use App\Support\Lqip;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

// Размытое превью фото: крошечная копия обложки прямо в разметке, пока фото грузится

beforeEach(function () {
    Storage::fake('public');
    $brand = Brand::query()->create(['slug' => 'kia', 'name' => 'Kia']);
    $this->car = Car::query()->create([
        'brand_id' => $brand->id, 'name' => 'Kia Rio', 'slug' => 'kia-rio', 'gearbox' => 'at',
        'seats' => 5, 'status' => 'published', 'min_days' => 1,
    ]);
    $this->car->prices()->create(['price' => 2000, 'days_from' => 1, 'days_to' => null]);
    $this->car->addMedia(UploadedFile::fake()->image('rio.jpg', 1280, 704))->toMediaCollection('gallery');
});

it('puts a tiny blurred preview of the cover into the car card and gallery', function () {
    $preview = Lqip::forCar($this->car->fresh());

    expect($preview)->toStartWith('data:image/')->and(strlen((string) $preview))->toBeLessThan(2000);

    $this->get('/katalog')->assertOk()->assertSee('--lqip: url(', false);
    $this->get('/avto/kia-rio')->assertOk()->assertSee('--lqip: url(', false);
});

it('builds the preview once and then serves it from cache', function () {
    $first = Lqip::forCar($this->car->fresh());
    Storage::disk('public')->deleteDirectory((string) $this->car->getFirstMedia('gallery')->id);

    expect(Lqip::forCar($this->car->fresh()))->toBe($first);
});

it('skips the preview for a car without photos', function () {
    $this->car->clearMediaCollection('gallery');

    expect(Lqip::forCar($this->car->fresh()))->toBeNull();
});
