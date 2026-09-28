# Локальная разработка

## Запуск

Сайт открывается сам на http://rentacar.test, пока запущен Laravel Herd. Админка — http://rentacar.test/admin.

```bash
cd ~/Herd/rentacar
composer install
npm install
php artisan migrate
npm run dev        # пересобирает стили и скрипты при каждом сохранении; остановить — Ctrl+C
```

Если стили «не подхватились» — остановите `npm run dev` и выполните `npm run build`.

## Тесты

```bash
php artisan test
```

Перед каждым пушем все тесты должны пройти. Тесты используют отдельную временную базу — ваши локальные данные не трогают. Что такое тесты и как их писать — раздел «Автотесты».

## Получить копию боевых данных к себе

Удобно, чтобы проверять правки на настоящих машинах и ценах.

```bash
# 1. на хостинге — свежая копия
ssh u1234567@server123.hosting.reg.ru "cd car-on-time && /opt/php/8.4/bin/php artisan backup:database"

# 2. скачать копии и фото
scp "u1234567@server123.hosting.reg.ru:car-on-time/storage/backups/db-*.sql.gz" ~/Downloads/
rsync -avz u1234567@server123.hosting.reg.ru:car-on-time/storage/app/public/ ~/Herd/rentacar/storage/app/public/

# 3. залить копию в ЛОКАЛЬНУЮ базу (локальные данные заменятся)
gunzip -c ~/Downloads/db-ДАТА.sql.gz | mysql -u root car_on_time
```

В копии — настоящие телефоны клиентов. Не пересылайте её никому и не кладите в GitHub.

**Сразу после заливки выключите интеграции**, иначе тестовые заявки уйдут в настоящий рабочий Telegram-чат:

```bash
php artisan integrations:disable
```

Подробно — раздел «Интеграции: как включать и выключать».

## Полезные команды

| Команда | Что делает |
|---|---|
| `php artisan migrate` | применить новые миграции |
| `php artisan migrate:status` | какие миграции применены |
| `php artisan optimize:clear` | сбросить все кэши, если изменения «не видны» |
| `php artisan route:list` | список адресов сайта |
| `php artisan user:owner email@…` | создать владельца админки |
| `php artisan integrations:status` | какие интеграции включены |
| `php artisan integrations:disable` | выключить все интеграции разом |
| `php artisan queue:work` | запустить фоновые задачи (Битрикс24, SMS) на компьютере |
| `vendor/bin/pint` | выровнять оформление PHP-кода |
