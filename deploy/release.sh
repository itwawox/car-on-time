#!/usr/bin/env bash
# Выкладка на хостинг (reg.ru и любой хостинг с SSH, без Node.js и Composer на сервере).
#
# Запускается НЕ на хостинге, а там, где собран сайт: в GitHub Actions (автоматически после мержа в main)
# или на своём компьютере. Порядок:
#   1) на хостинге: копия базы и режим обслуживания;
#   2) заливка готовых файлов (код + vendor + собранные стили) через rsync;
#   3) на хостинге: миграции, кэши, перезапуск фоновых задач, сайт снова открыт.
#
# Настройки — переменные окружения. В GitHub — секреты среды staging/production.
# Локально — файл deploy/.env.deploy (боевой) или другой, указанный в DEPLOY_ENV_FILE:
#   DEPLOY_ENV_FILE=deploy/.env.deploy.dev bash deploy/release.sh   # тестовый сайт
#   DEPLOY_HOST   адрес сервера, например server123.hosting.reg.ru
#   DEPLOY_USER   логин хостинга, например u1234567
#   DEPLOY_PATH   папка сайта на хостинге, например /var/www/u1234567/data/car-on-time
#   DEPLOY_PHP    PHP на хостинге, например /opt/php/8.4/bin/php
#   DEPLOY_PORT   порт SSH (по умолчанию 22)
#   DEPLOY_REF    ветка, которую выкладываем (необязательно; по умолчанию — текущая ветка git)
#   DEPLOY_URL    адрес сайта этой среды, например https://car-on-time.ru или https://dev.void-web.ru.
#                 Сверяется с APP_URL в .env на хостинге — защита от выкладки не туда.
set -euo pipefail
cd "$(dirname "$0")/.."

DEPLOY_ENV_FILE="${DEPLOY_ENV_FILE:-deploy/.env.deploy}"
if [ -z "${GITHUB_ACTIONS:-}" ] && [ -f "$DEPLOY_ENV_FILE" ]; then
    set -a && . "$DEPLOY_ENV_FILE" && set +a
    echo "Настройки выкладки: $DEPLOY_ENV_FILE"
fi

: "${DEPLOY_HOST:?Не задан DEPLOY_HOST}"
: "${DEPLOY_USER:?Не задан DEPLOY_USER}"
: "${DEPLOY_PATH:?Не задан DEPLOY_PATH}"
: "${DEPLOY_URL:?Не задан DEPLOY_URL — адрес сайта этой среды, например https://car-on-time.ru}"
DEPLOY_PHP="${DEPLOY_PHP:-php}"
DEPLOY_PORT="${DEPLOY_PORT:-22}"

SSH=(ssh -p "$DEPLOY_PORT" -o StrictHostKeyChecking=accept-new "$DEPLOY_USER@$DEPLOY_HOST")
remote() { "${SSH[@]}" "cd '$DEPLOY_PATH' && $*"; }
step() { printf '\n\033[1;34m==> %s\033[0m\n' "$1"; }

[ -f vendor/autoload.php ] || { echo "Нет vendor: сначала composer install --no-dev --optimize-autoloader"; exit 1; }
[ -f public/build/manifest.json ] || { echo "Нет собранных стилей: сначала npm ci && npm run build"; exit 1; }

on_error() {
    printf '\n\033[1;31m!!! Выкладка прервана.\033[0m Включаем сайт обратно. Копия базы — в storage/backups на хостинге.\n'
    remote "[ -f artisan ] && $DEPLOY_PHP artisan up" >/dev/null 2>&1 || true
}
trap on_error ERR

step "Хостинг: папка сайта"
# Без cd: при первой выкладке папки ещё нет, её и создаём
"${SSH[@]}" "mkdir -p '$DEPLOY_PATH'" \
    || { echo "Не удалось войти на хостинг или создать папку $DEPLOY_PATH. Проверьте DEPLOY_HOST, DEPLOY_USER, DEPLOY_SSH_KEY и DEPLOY_PATH."; exit 1; }

step "Хостинг: проверяем PHP"
remote "$DEPLOY_PHP -r 'exit(version_compare(PHP_VERSION, \"8.4.1\", \">=\") ? 0 : 1);'" \
    || { echo "На хостинге нужен PHP 8.4. Проверьте DEPLOY_PHP (например /opt/php/8.4/bin/php)."; exit 1; }

step "Хостинг: сверяем адрес сайта"
REMOTE_URL=$(remote "[ -f .env ] && grep -E '^APP_URL=' .env | head -n 1 | cut -d= -f2- | tr -d '\"'\\'' \\r' || true")
if [ -z "$REMOTE_URL" ]; then
    echo "Первая выкладка — на хостинге ещё нет .env"
elif [ "${REMOTE_URL%/}" != "${DEPLOY_URL%/}" ]; then
    echo "Остановлено: на хостинге APP_URL=$REMOTE_URL, а выкладываем для $DEPLOY_URL."
    echo "Проверьте секреты среды в GitHub (Settings → Environments) — похоже, выбран не тот сервер."
    exit 1
else
    echo "$REMOTE_URL — совпадает"
fi

step "Хостинг: копия базы и режим обслуживания"
remote "if [ -f artisan ] && [ -f .env ]; then $DEPLOY_PHP artisan backup:database && $DEPLOY_PHP artisan down --retry=30 || true; else echo 'Первая выкладка — пропускаем'; fi"

step "Записываем версию"
DEPLOY_COMMIT=$(git rev-parse HEAD)
DEPLOY_BRANCH="${DEPLOY_REF:-$(git rev-parse --abbrev-ref HEAD)}"
[ "$DEPLOY_BRANCH" = "HEAD" ] && DEPLOY_BRANCH=""
printf '{"commit":"%s","branch":"%s","deployed_at":"%s"}\n' "$DEPLOY_COMMIT" "$DEPLOY_BRANCH" "$(date -u +%Y-%m-%dT%H:%M:%SZ)" > version.json
cat version.json

step "Заливаем файлы"
rsync -az --delete -e "ssh -p $DEPLOY_PORT -o StrictHostKeyChecking=accept-new" \
    --exclude='.git/' --exclude='.github/' --exclude='node_modules/' --exclude='tests/' \
    --exclude='.env' --exclude='.env.*' --exclude='deploy/.env.deploy' \
    --exclude='/storage/' --exclude='/public/storage' --exclude='/public/hot' --exclude='/bootstrap/cache/' \
    --exclude='/_legacy/' --exclude='/public/legacy-image' --exclude='/image-cars/' --exclude='/.agents/' --exclude='/.claude/' --exclude='/.grok/' \
    --exclude='*.sqlite' --exclude='.DS_Store' --exclude='/.cursor/' --exclude='.phpunit.result.cache' --exclude='/.mcp.json' --exclude='/boost.json' \
    ./ "$DEPLOY_USER@$DEPLOY_HOST:$DEPLOY_PATH/"

rm -f version.json

step "Хостинг: миграции, кэши, фоновые задачи"
remote "DEPLOY_PHP='$DEPLOY_PHP' bash deploy/remote-finish.sh"

trap - ERR
printf '\n\033[1;32mГотово. Сайт обновлён.\033[0m\n'
