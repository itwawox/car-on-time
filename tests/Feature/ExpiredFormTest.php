<?php

use App\Models\Booking;
use App\Models\Brand;
use App\Models\Car;
use App\Models\Location;

// «Страница устарела» (ошибка 419): форма открыта дольше жизни сессии или браузер показал страницу из кеша со старым токеном

beforeEach(function () {
    $brand = Brand::query()->create(['slug' => 'kia', 'name' => 'Kia']);
    $this->car = Car::query()->create(['brand_id' => $brand->id, 'name' => 'Kia Rio', 'slug' => 'kia-rio', 'gearbox' => 'at', 'seats' => 5, 'status' => 'published', 'min_days' => 1]);
    $this->car->prices()->create(['price' => 2000, 'days_from' => 1, 'days_to' => null]);
    $this->location = Location::query()->create(['slug' => 'office', 'name' => 'Офис', 'type' => 'office', 'is_active' => true]);

    // В тестах Laravel не проверяет CSRF-токен — включаем проверку как на сайте
    $this->app->instance('env', 'local');
});

function bookingForm(array $overrides = []): array
{
    return [
        'car_id' => test()->car->id,
        'phone' => '+7 (978) 948-48-48',
        'starts_at' => now()->addDays(2)->format('Y-m-d\T10:00'),
        'ends_at' => now()->addDays(5)->format('Y-m-d\T10:00'),
        'pickup_location_id' => test()->location->id,
        'return_location_id' => test()->location->id,
        'pd_consent' => '1',
        ...$overrides,
    ];
}

it('returns the visitor to the form with entered data instead of a bare 419 page', function () {
    $carUrl = route('car.show', $this->car->slug);
    $startsAt = now()->addDays(3)->format('Y-m-d\T12:00');

    $this->from($carUrl)
        ->post('/zayavka', bookingForm(['_token' => 'stale-token', 'starts_at' => $startsAt]))
        ->assertRedirect($carUrl);

    // Страницу открываем до проверок сессии: они сами запускают сессию и сбивают сохранённые ошибки
    $this->get($carUrl)
        ->assertOk()
        ->assertSee('Страница устарела')
        ->assertSee('value="+7 (978) 948-48-48"', false)
        ->assertSee('value="'.$startsAt.'"', false);

    expect(Booking::query()->count())->toBe(0);
});

it('explains an expired form in Russian and hands a fresh token to scripts', function () {
    $response = $this->postJson('/obratnyj-zvonok', ['phone' => '+7 (978) 948-48-48', '_token' => 'stale-token'])
        ->assertStatus(419);

    expect($response->json('message'))->toContain('Страница устарела')
        ->and($response->json('token'))->toBe(session()->token());
});

it('accepts the form sent again with the fresh token', function () {
    $this->from(route('car.show', $this->car->slug))->post('/zayavka', bookingForm(['_token' => 'stale-token']));

    $this->post('/zayavka', bookingForm(['_token' => session()->token()]))->assertRedirect();

    expect(Booking::query()->count())->toBe(1);
});

it('does not serve a cached page with a form token from an expired session', function () {
    $carUrl = route('car.show', $this->car->slug);
    $etag = $this->get($carUrl)->assertOk()->headers->get('ETag');

    // Сессия истекла: у посетителя новый токен, а в кеше браузера — страница со старым
    $this->flushSession();
    session()->regenerateToken();

    $this->withHeaders(['If-None-Match' => $etag])->get($carUrl)->assertOk();
});

it('still answers 304 to search robots, they never send forms', function () {
    $carUrl = route('car.show', $this->car->slug);
    $robot = ['User-Agent' => 'Mozilla/5.0 (compatible; YandexBot/3.0; +http://yandex.com/bots)'];
    $etag = $this->withHeaders($robot)->get($carUrl)->assertOk()->headers->get('ETag');

    $this->flushSession();
    session()->regenerateToken();

    $this->withHeaders([...$robot, 'If-None-Match' => $etag])->get($carUrl)->assertStatus(304);
});
