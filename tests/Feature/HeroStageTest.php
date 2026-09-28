<?php

use App\Models\Brand;
use App\Models\Car;
use App\Models\Setting;
use App\Models\User;
use App\Support\HeroStage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

// «Сцена» первого экрана главной: машина под задачу, выбор машины и своё фото — в «Настройках сайта»

beforeEach(function () {
    Storage::fake('public');
    $brand = Brand::query()->create(['slug' => 'lada', 'name' => 'Lada']);
    $this->makeCar = function (string $name) use ($brand): Car {
        $car = Car::query()->create([
            'brand_id' => $brand->id, 'name' => $name, 'slug' => str($name)->slug(), 'gearbox' => 'at',
            'seats' => 5, 'status' => 'published', 'min_days' => 1,
        ]);
        $car->addMedia(UploadedFile::fake()->image($car->slug.'.jpg', 1280, 704))->toMediaCollection('gallery');

        return $car;
    };
    $this->scenario = fn (?int $coverId) => [
        'key' => 'family', 'title' => 'Для семьи', 'count' => 72, 'price' => 1550, 'cover_id' => $coverId, 'url' => '/katalog?body[]=krossover',
    ];
});

it('shows the first matching car of the scenario by default', function () {
    $car = ($this->makeCar)('Largus Cross');

    $slide = HeroStage::slides([($this->scenario)($car->id)])[0];

    expect($slide)->toMatchArray(['tab' => 'Для семьи', 'title' => 'Для семьи', 'alt' => 'Largus Cross', 'cutout' => false])
        ->and($slide['image'])->toBe($car->coverUrl('large'));
});

it('uses the car chosen in the admin instead of the automatic one', function () {
    $auto = ($this->makeCar)('Largus Cross');
    $chosen = ($this->makeCar)('Vesta SW');
    Setting::put('hero_stage_family_car', $chosen->id);

    expect(HeroStage::slides([($this->scenario)($auto->id)])[0]['alt'])->toBe('Vesta SW');
});

it('shows an uploaded transparent photo as is', function () {
    $car = ($this->makeCar)('Largus Cross');
    Storage::disk('public')->put('hero/family.png', 'png');
    Setting::put('hero_stage_family_image', 'hero/family.png');

    $slide = HeroStage::slides([($this->scenario)($car->id)])[0];

    expect($slide['image'])->toBe(Storage::disk('public')->url('hero/family.png'))
        ->and($slide['cutout'])->toBeTrue();
});

it('can be switched off in the admin', function () {
    $car = ($this->makeCar)('Largus Cross');
    Setting::put('hero_stage_enabled', false);

    expect(HeroStage::slides([($this->scenario)($car->id)]))->toBe([]);
});

it('keeps the home page clean when there is no car to show', function () {
    $this->get('/')->assertOk()->assertDontSee('data-hero-stage', false);
});

it('lets the owner pick a car and upload a photo for each task', function () {
    config(['app.env' => 'local']);
    $this->actingAs(User::factory()->create());

    $this->get('/admin/site-settings')->assertOk()
        ->assertSee('Главная: сцена первого экрана')
        ->assertSee('своё фото на прозрачном фоне');
});
