#!/usr/bin/env bash
# Deploy to the DigitalOcean droplet. Usage: deploy/deploy.sh user@host /var/www/nailpolish
set -euo pipefail
HOST="${1:?user@host}"
DIR="${2:?remote app dir}"

ssh "$HOST" "cd $DIR && git pull --ff-only \
  && composer install --no-dev --optimize-autoloader --no-interaction \
  && php artisan migrate --force \
  && php artisan config:cache && php artisan route:cache && php artisan view:cache \
  && php artisan queue:restart"
