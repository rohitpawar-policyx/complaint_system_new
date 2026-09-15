#!/usr/bin/env sh
set -e

# If Render's "Docker Command" is overridden (e.g. for the separate Reverb
# service: `php artisan reverb:start --host=0.0.0.0 --port=$PORT`), run
# exactly that instead of the normal migrate/seed/serve flow below - the
# Reverb service doesn't touch the database or serve HTTP pages, it only
# needs the WebSocket server process.
if [ "$#" -gt 0 ]; then
    exec "$@"
fi

php artisan config:cache
php artisan route:cache
php artisan view:cache

php artisan migrate --force
php artisan db:seed --force

exec php artisan serve --host=0.0.0.0 --port="${PORT:-10000}"
