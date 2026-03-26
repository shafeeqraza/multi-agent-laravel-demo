#!/usr/bin/env bash
set -euo pipefail

cd /var/www/html

if [ ! -f .env ] && [ -f .env.example ]; then
  cp .env.example .env
fi

php artisan key:generate --force --no-interaction || true

if [ "${DB_CONNECTION:-}" = "mysql" ]; then
  echo "Waiting for MySQL..."
  php -r '
    $host = getenv("DB_HOST") ?: "db";
    $port = getenv("DB_PORT") ?: "3306";
    $db   = getenv("DB_DATABASE") ?: "laravel";
    $user = getenv("DB_USERNAME") ?: "root";
    $pass = getenv("DB_PASSWORD") ?: "";
    $start = time();
    while (true) {
      try {
        new PDO("mysql:host={$host};port={$port};dbname={$db};charset=utf8mb4", $user, $pass, [
          PDO::ATTR_TIMEOUT => 2,
        ]);
        break;
      } catch (Throwable $e) {
        if (time() - $start > 60) {
          fwrite(STDERR, "MySQL not ready after 60s: {$e->getMessage()}\n");
          exit(1);
        }
        usleep(500000);
      }
    }
  ';
fi

php artisan migrate --force --no-interaction || true

chown -R www-data:www-data storage bootstrap/cache

exec /usr/bin/supervisord -c /etc/supervisor/conf.d/supervisord.conf
