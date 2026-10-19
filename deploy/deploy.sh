#!/usr/bin/env bash
# Deploy to the DigitalOcean droplet. Usage: deploy/deploy.sh user@host /var/www/nailpolish
#
# The site is put into maintenance mode (visitors see a clean "back soon" page) while the code, database and caches
# change, then PHP-FPM is reloaded (OPcache is set not to re-check files, so running workers would otherwise keep
# serving old code) and the site comes back up. If any step fails the site STAYS in maintenance mode, so nobody sees
# half-updated pages: fix the problem and run the script again (or `php artisan up` once you are sure).
# The remote user needs passwordless sudo for `systemctl reload php8.3-fpm`.
set -euo pipefail
HOST="${1:?user@host}"
DIR="${2:?remote app dir}"

ssh "$HOST" "cd $DIR \
  && php artisan down --retry=15 \
  && git pull --ff-only \
  && composer install --no-dev --optimize-autoloader --no-interaction \
  && php artisan migrate --force \
  && php artisan config:cache && php artisan route:cache && php artisan view:cache && php artisan event:cache \
  && sudo systemctl reload php8.3-fpm \
  && php artisan queue:restart \
  && php artisan up"
