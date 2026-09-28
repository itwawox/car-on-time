# Car on Time

Сайт аренды автомобилей в Крыму — [car-on-time.ru](https://car-on-time.ru): каталог с живым расчётом цены, заявка «в три поля», онлайн-предоплата, личный кабинет клиента и админка для менеджеров.

## Стек

- PHP 8.4, Laravel 13, MySQL
- Админка — Filament 4
- Витрина — Blade, Tailwind CSS 4, ванильный JavaScript, сборка Vite
- Фоновые задачи — очередь `database` и планировщик Laravel (CRON раз в минуту)
- Spatie Media Library, Spatie Honeypot, Laravel Scout со своим движком поиска
- Pest, Larastan (уровень 5), Pint

## Возможности

- Каталог, фильтры, умный поиск (опечатки, транслит, не та раскладка), сравнение, избранное, подбор машины
- Единый расчёт цены: сезонные тарифы, доставка, доп. услуги, промокоды, залог и «без залога»
- Заявки и брони: календарь занятости, статусы, SLA ответа, CRM-экран, документы клиента и цифровой акт осмотра
- Интеграции (включаются в админке): Битрикс24, SMS, ЮKassa, Telegram — через очередь с журналом обмена
- Роли сотрудников: владелец, менеджер, контент-редактор
- SEO: шаблоны мета-тегов, карта сайта, редиректы, IndexNow; тексты и цифры редактируются в админке

## Локальный запуск

```bash
composer install
npm install
cp .env.example .env
php artisan key:generate
php artisan migrate
php artisan user:owner you@example.com
npm run dev
```

Сайт рассчитан на Laravel Herd: `http://rentacar.test`, админка — `/admin`.

## Проверки

```bash
composer check    # Pint --test, Larastan, Pest
npm run build     # если менялись стили или скрипты
```

Те же проверки и `composer audit` / `npm audit` запускаются в GitHub Actions на каждый Pull Request.

## Выкладка

Хостинг — обычный reg.ru (без root и Node.js). Сайт собирает GitHub Actions и заливает на хостинг скриптом `deploy/release.sh`:

| Ветка | Среда GitHub | Сайт |
|---|---|---|
| `dev` | `staging` | https://dev.void-web.ru |
| `main` | `production` | https://car-on-time.ru |

Перед выкладкой делается копия базы, после неё выполняются миграции и сбрасываются кэши. Секреты доступа хранятся в Settings → Environments.

## Документация

Подробные инструкции — в админке, раздел «Документация»: первая настройка хостинга, выкладка и откат, резервные копии, база и файлы, интеграции. Исходники — `resources/docs`.
