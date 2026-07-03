#!/usr/bin/env bash
set -euo pipefail

cd "$(dirname "$0")/../.."

if [ ! -f ".env" ]; then
    if [ -f ".env.production.example" ]; then
        cp .env.production.example .env
    else
        cp .env.example .env
    fi

    echo "Se creo .env. Editalo con los datos reales del servidor y vuelve a ejecutar este script."
    exit 1
fi

composer install --no-dev --optimize-autoloader

if [ -f "package-lock.json" ]; then
    npm ci
else
    npm install
fi

npm run build

php artisan migrate --force
php artisan storage:link
php artisan optimize:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache

echo "Despliegue terminado."