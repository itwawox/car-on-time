# Папки проекта

Папка проекта — `~/Herd/rentacar`. Главное, что в ней лежит:

| Папка | Что внутри | Пример |
|---|---|---|
| `app/Models` | **Модели** — описание таблиц базы | `Car.php`, `Booking.php` |
| `app/Http/Controllers` | **Контроллеры** — принимают запрос и отдают страницу | `BookingController.php` |
| `app/Http/Requests` | Проверка данных из форм (папка появится, когда переведём на неё старые контроллеры) | |
| `app/Services` | Большие расчёты и интеграции | `QuoteCalculator.php`, `Bitrix24Sync.php` |
| `app/Support` | Небольшие помощники | `Phone.php`, `DocsSearch.php` (поиск по этому курсу) |
| `app/Jobs` | Задания для очереди | отправка в CRM и SMS |
| `app/Filament` | Вся админка | страницы, таблицы, этот курс — `Pages/CourseDocs.php` |
| `routes/web.php` | Адреса сайта | `/avto/...`, `/zayavka` |
| `routes/console.php` | Расписание для cron | |
| `resources/views` | Шаблоны страниц (Blade — HTML с вставками) | `car.blade.php` |
| `resources/css`, `resources/js` | Стили и скрипты витрины | `app.css`, `app.js` |
| `resources/docs` | Этот курс и документация | `course/`, `developers/` |
| `database/migrations` | Изменения структуры базы | |
| `tests` | **Автотесты** | `Feature/`, `Unit/` |
| `config` | Настройки Laravel | |
| `public` | То, что браузер может скачать напрямую | картинки, собранные стили |
| `storage` | Загруженные файлы, логи, кэш | |
| `vendor`, `node_modules` | Чужие библиотеки — **не редактируем** | |
| `deploy`, `.github` | Выкладка на хостинг и проверки в GitHub | |

В корне ещё есть важные файлы:

- `composer.json` — список PHP-библиотек;
- `package.json` — список JS-библиотек;
- `phpstan.neon` — настройки Larastan;
- `README.md` — краткое описание проекта.

## Где искать, если нужно поменять…

| Задача | Куда смотреть |
|---|---|
| Текст на странице | сначала админка («Настройки сайта»), потом `resources/views` |
| Цвет или отступ | `resources/css/app.css`, потом `npm run build` |
| Как считается цена | `app/Services/QuoteCalculator.php` |
| Раздел в админке | `app/Filament` |
| Что происходит после заявки | `app/Http/Controllers/BookingController.php`, `app/Jobs` |
| Проверку, которая упала | `tests/` — файл и строку Pest покажет сам |

## Попробуйте сами

Откройте папку `tests/Feature` и найдите файл, который проверяет промокоды. Подсказка: его имя начинается с `Promo`.
