#!/usr/bin/env bash
# Выполняется НА ХОСТИНГЕ после заливки файлов (его вызывает deploy/release.sh).
set -euo pipefail
cd "$(dirname "$0")/.."
PHP="${DEPLOY_PHP:-php}"

# Папки, которых нет в GitHub: создаются при первой выкладке, дальше не трогаются
mkdir -p storage/app/public storage/app/private storage/framework/cache/data storage/framework/sessions \
    storage/framework/views storage/logs storage/backups bootstrap/cache

if [ ! -f .env ]; then
    echo "На хостинге нет файла .env — создайте его по разделу «Первая настройка хостинга» и повторите выкладку."
    exit 1
fi

"$PHP" artisan migrate --force
"$PHP" artisan storage:link >/dev/null 2>&1 || true
"$PHP" artisan optimize:clear
"$PHP" artisan optimize
"$PHP" artisan filament:optimize
"$PHP" artisan queue:restart
"$PHP" artisan up
