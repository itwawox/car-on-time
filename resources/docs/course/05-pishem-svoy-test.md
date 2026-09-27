# Пишем свой тест

## Правило «сначала красный»

Когда нашли баг, порядок такой:

1. **Пишем тест**, который воспроизводит баг. Запускаем — он **красный** (падает). Это доказывает, что тест правда ловит проблему.
2. **Исправляем** код.
3. Запускаем — тест **зелёный**. Баг исправлен, и тест не даст ему вернуться.

Если написать тест после исправления, непонятно, умеет ли он вообще падать.

## Реальный пример: ВКонтакте в карточке заявки

Владелец заметил, что в карточке машины нет иконки ВК, хотя в подвале она есть.

**Шаг 1. Создаём файл теста:**

```bash
php artisan make:test --pest MessengersTest
```

Появится `tests/Feature/MessengersTest.php`.

**Шаг 2. Пишем, как должно быть:**

```php
use App\Models\Setting;

// Перед каждым тестом файла: в настройках есть WhatsApp и ВК
beforeEach(function () {
    Setting::put('whatsapp', 'https://wa.me/79780000000');
    Setting::put('vk', 'https://vk.com/car_on_time');
});

it('adds VK to the messengers block only when asked', function () {
    expect(view('partials.messengers', ['variant' => 'icons', 'withVk' => true])->render())
        ->toContain('https://vk.com/car_on_time')
        ->and(view('partials.messengers', ['variant' => 'grid'])->render())
        ->not->toContain('https://vk.com/car_on_time');
});
```

- `beforeEach` — подготовка, общая для всех тестов файла.
- `Setting::put(...)` — как будто владелец заполнил поле в админке.
- `view(...)->render()` — нарисовали кусок страницы (блок мессенджеров) и получили HTML.

**Шаг 3. Запускаем — красный:**

```bash
./vendor/bin/pest tests/Feature/MessengersTest.php
```

Pest пишет: «ожидал, что HTML содержит vk.com, а его там нет». Баг подтверждён.

**Шаг 4. Исправляем** шаблон `resources/views/partials/messengers.blade.php` — добавили ВК.

**Шаг 5. Запускаем снова — зелёный.** Готово.

## Самые нужные проверки

| Запись | Что проверяет |
|---|---|
| `$this->get('/адрес')->assertOk()` | страница открывается |
| `->assertSee('текст')` / `->assertDontSee('текст')` | текст есть / нет на странице |
| `->assertForbidden()` | доступ запрещён (403) |
| `->assertNotFound()` | страницы нет (404) |
| `->assertRedirect('/куда')` | перенаправление |
| `$this->post('/zayavka', [...])` | отправили форму |
| `expect($x)->toBe(5)` | значение ровно 5 |
| `expect($x)->toContain('…')` | строка или список содержит |
| `expect($x)->toBeTrue()`, `->toBeNull()` | да / пусто |
| `$this->assertDatabaseHas('bookings', ['phone' => '+79780000000'])` | в базе есть такая запись |

Вход под сотрудником:

```php
$this->actingAs(User::factory()->create());                    // владелец
$this->actingAs(User::factory()->role('manager')->create());   // менеджер
```

## Подмены: тест не ходит в настоящие сервисы

### Внешний сервис — `Http::fake()`

Тест не должен отправлять настоящую сделку в Битрикс24. Подменяем интернет-запросы. Пример из `Bitrix24IntegrationTest.php` — «портал лежит, а заявка клиента всё равно принята»:

```php
Http::fake(fn () => throw new ConnectionException('portal is down'));   // любой запрос «падает»

$booking = $this->book();                                                // клиент оставил заявку

$this->assertNull($booking->crm_id);                                     // в CRM не попала…
$this->assertSame('failed', IntegrationLog::query()->sole()->status);    // …ошибка записана в журнал
```

А так проверяют, **что** ушло бы в Битрикс24:

```php
Http::assertSent(fn (Request $r) => str_ends_with($r->url(), 'crm.lead.add.json')
    && $r['fields']['NAME'] === 'Анна');
```

### Файлы — `Storage::fake()`

```php
Storage::fake('local');   // файлы пишутся во временную папку и потом исчезают
```

Так `BookingDocumentsTest.php` проверяет загрузку паспорта, не засоряя ваш диск.

### Время — `$this->travel()`

Многое зависит от времени: «заявка без ответа больше 15 минут», «документы удаляются через 30 дней». Ждать тест не может, поэтому «перематывает» часы:

```php
$this->travel(-20)->minutes();   // «сейчас» — 20 минут назад
// … создали заявку …
$this->travelBack();             // вернули время
```

Пример — `BookingWorkflowTest.php`, тест про просроченную заявку.

### Датасеты — один тест, много примеров

Из `tests/Unit/PhoneAndAttributionTest.php`:

```php
it('normalizes russian phone numbers to +7', function (string $input, string $expected) {
    expect(Phone::e164($input))->toBe($expected);
})->with([
    'with brackets' => ['+7 (978) 948-48-48', '+79789484848'],
    'starting with 8' => ['8 978 948 48 48', '+79789484848'],
    'ten digits' => ['9789484848', '+79789484848'],
]);
```

Pest запустит тест три раза — по разу на каждую строку.

## Старый стиль и Pest

Один и тот же тест в двух стилях:

```php
// Старый стиль (PHPUnit) — так написана часть наших тестов
class DocsTest extends TestCase
{
    public function test_docs_are_for_owner_only(): void
    {
        $this->actingAs(User::factory()->role('manager')->create());
        $this->get('/admin/docs')->assertForbidden();
    }
}

// Pest — так пишем новые
it('hides docs from managers', function () {
    $this->actingAs(User::factory()->role('manager')->create());
    $this->get('/admin/docs')->assertForbidden();
});
```

Работают оба, старые не переписываем.

## Частые ошибки

- **Тест проверяет «как устроено», а не «что видит пользователь».** Лучше проверять страницу, базу, отправленный запрос.
- **Тест зависит от ваших данных.** База в тестах пустая: всё нужное создавайте в самом тесте.
- **Тест ходит в интернет.** Всегда `Http::fake()`.
- **Забыли запустить все тесты**, а не только новый. Изменение могло сломать соседний.

## Попробуйте сами

Напишите тест: страница `/faq` открывается и на ней есть слово «Вопросы».

```bash
php artisan make:test --pest MyFirstTest
```

```php
it('opens the faq page', function () {
    $this->get('/faq')->assertOk()->assertSee('Вопросы');
});
```

```bash
./vendor/bin/pest tests/Feature/MyFirstTest.php
```

Потом удалите файл: он учебный, в проект его не добавляем.
