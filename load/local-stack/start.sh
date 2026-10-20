#!/usr/bin/env bash
# Starts a private, production-like stack for load testing on this machine: its own PHP-FPM (OPcache on, Xdebug off)
# and nginx on 127.0.0.1:${LT_PORT:-8081}, using an isolated Postgres schema. It never touches your normal
# nginx/PHP-FPM setup, ports or database tables.
#
#   load/local-stack/start.sh            # start (rebuilds the Laravel caches for this configuration)
#   load/local-stack/stop.sh             # stop
#
# Tuning knobs (environment variables, defaults in brackets):
#   LOADTEST_DIR [~/.cache/nailpolish-loadtest]  where configs, caches, logs and the nginx cache live
#   FPM_CHILDREN [24]        PHP-FPM worker processes
#   SESSION_DRIVER [database] | file | redis      CACHE_STORE [database] | file | redis
#   DB_PERSISTENT [false]    keep PHP-FPM workers' database connections open between requests
#   PHOTO_CACHE [on]         nginx fastcgi_cache for /photos/* (on|off)
#   DB_SEARCH_PATH [loadtest]  Postgres schema holding the load-test data (see database/seeders/LoadTestSeeder.php)
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/../.." && pwd)"
RUN="${LOADTEST_DIR:-$HOME/.cache/nailpolish-loadtest}"
PORT="${LT_PORT:-8081}"
CHILDREN="${FPM_CHILDREN:-24}"
SESSION="${SESSION_DRIVER:-database}"
CACHE="${CACHE_STORE:-database}"
PHOTOS="${PHOTO_CACHE:-on}"
SCHEMA="${DB_SEARCH_PATH:-loadtest}"
PHP_BIN="${PHP_BIN:-$(command -v php)}"
PHP_DIR="$(dirname "$(dirname "$PHP_BIN")")"
FPM_BIN="${FPM_BIN:-$PHP_DIR/sbin/php-fpm}"
NGINX_BIN="${NGINX_BIN:-/usr/sbin/nginx}"
EXT_DIR="$("$PHP_BIN" -n -r 'echo ini_get("extension_dir");')"

[ "$SCHEMA" != "public" ] || { echo "Refusing to run the load test against the real 'public' schema." >&2; exit 1; }
"$ROOT/load/local-stack/stop.sh" >/dev/null 2>&1 || true
mkdir -p "$RUN"/{cache/views,fcache,tmp,logs}

# --- PHP: no php.ini (so no Xdebug), OPcache on, as on a production server --------------------------------------
cat > "$RUN/php.ini" <<EOF
zend_extension=$EXT_DIR/opcache.so
opcache.enable=1
opcache.memory_consumption=128
opcache.interned_strings_buffer=16
opcache.max_accelerated_files=10000
opcache.validate_timestamps=0
memory_limit=256M
; same upload limits as deploy/php/nailpolish.ini: four 10 MB photos per Manicure
upload_max_filesize=12M
post_max_size=50M
display_errors=0
log_errors=1
expose_php=0
realpath_cache_size=4096k
realpath_cache_ttl=600
EOF

# --- the environment the app sees (as it would in a production .env) -------------------------------------------
declare -A ENVV=(
  [APP_ENV]=production [APP_DEBUG]=false [LOG_LEVEL]=warning [LOG_CHANNEL]=single
  [DB_SEARCH_PATH]="$SCHEMA" [LOAD_TEST_MODE]=true
  [SESSION_DRIVER]="$SESSION" [CACHE_STORE]="$CACHE" [QUEUE_CONNECTION]=database [DB_PERSISTENT]="${DB_PERSISTENT:-false}"
  [APP_CONFIG_CACHE]="$RUN/cache/config.php" [APP_ROUTES_CACHE]="$RUN/cache/routes.php"
  [APP_EVENTS_CACHE]="$RUN/cache/events.php" [APP_PACKAGES_CACHE]="$RUN/cache/packages.php"
  [APP_SERVICES_CACHE]="$RUN/cache/services.php" [VIEW_COMPILED_PATH]="$RUN/cache/views"
)
ENV_LINES=""; for k in "${!ENVV[@]}"; do ENV_LINES+="env[$k] = \"${ENVV[$k]}\""$'\n'; done

cat > "$RUN/php-fpm.conf" <<EOF
[global]
pid = $RUN/php-fpm.pid
error_log = $RUN/logs/php-fpm.log
daemonize = yes
[www]
listen = $RUN/php.sock
listen.mode = 0666
pm = static
pm.max_children = $CHILDREN
pm.max_requests = 2000
request_terminate_timeout = 60s
clear_env = no
catch_workers_output = yes
$ENV_LINES
EOF

# --- Laravel's production caches, built with this exact environment ---------------------------------------------
( cd "$ROOT"
  for k in "${!ENVV[@]}"; do export "$k=${ENVV[$k]}"; done
  rm -f "$RUN"/cache/*.php
  "$PHP_BIN" artisan config:cache >/dev/null && "$PHP_BIN" artisan route:cache >/dev/null \
    && "$PHP_BIN" artisan event:cache >/dev/null && "$PHP_BIN" artisan view:cache >/dev/null )

# --- nginx -----------------------------------------------------------------------------------------------------
if [ "$PHOTOS" = "on" ]; then
  PHOTO_BLOCK="
    # Public photo responses carry 'Cache-Control: public, max-age=60' and no cookies, so nginx may serve repeats
    # without waking PHP. Private ones are 'private, no-store' and are never cached.
    location ^~ /photos/ {
      include /etc/nginx/fastcgi_params;
      fastcgi_param HTTP_HOST \$http_host;   # keep :$PORT (this distro's fastcgi_params uses \$host, which drops it)
      fastcgi_param SCRIPT_FILENAME \$document_root/index.php;
      fastcgi_param SCRIPT_NAME /index.php;
      fastcgi_pass unix:$RUN/php.sock;
      fastcgi_cache photos;
      fastcgi_cache_key \"\$scheme\$host\$request_uri\";
      fastcgi_cache_valid 200 60s;
      fastcgi_cache_lock on;
      fastcgi_cache_use_stale updating;
      add_header X-Cache-Status \$upstream_cache_status always;
    }"
else
  PHOTO_BLOCK=""
fi

cat > "$RUN/nginx.conf" <<EOF
worker_processes auto;
daemon off;
pid $RUN/nginx.pid;
error_log $RUN/logs/nginx-error.log warn;
events { worker_connections 8192; multi_accept on; }
http {
  include /etc/nginx/mime.types;
  default_type application/octet-stream;
  access_log off;
  sendfile on; tcp_nopush on; tcp_nodelay on;
  keepalive_timeout 65; keepalive_requests 10000;
  client_body_temp_path $RUN/tmp/body; fastcgi_temp_path $RUN/tmp/fcgi; proxy_temp_path $RUN/tmp/proxy;
  uwsgi_temp_path $RUN/tmp/uwsgi; scgi_temp_path $RUN/tmp/scgi;
  gzip on; gzip_comp_level 4; gzip_min_length 1024; gzip_types text/html text/css application/javascript application/json image/svg+xml;
  fastcgi_cache_path $RUN/fcache levels=1:2 keys_zone=photos:20m max_size=500m inactive=10m use_temp_path=off;
  server {
    listen 127.0.0.1:$PORT backlog=4096;
    server_name _;
    root $ROOT/public;
    index index.php;
    client_max_body_size 50m;
    $PHOTO_BLOCK
    location / { try_files \$uri \$uri/ /index.php?\$query_string; }
    location ~ \.php\$ {
      include /etc/nginx/fastcgi_params;
      fastcgi_param HTTP_HOST \$http_host;
      fastcgi_param SCRIPT_FILENAME \$document_root\$fastcgi_script_name;
      fastcgi_pass unix:$RUN/php.sock;
      fastcgi_keep_conn on;
      fastcgi_buffer_size 32k; fastcgi_buffers 16 16k;
    }
    location ~ /\.(?!well-known) { deny all; }
  }
}
EOF

"$FPM_BIN" -n -c "$RUN/php.ini" -y "$RUN/php-fpm.conf"
( "$NGINX_BIN" -c "$RUN/nginx.conf" -p "$RUN" -e "$RUN/logs/nginx-error.log" >/dev/null 2>&1 & echo $! > "$RUN/nginx.starter.pid" )

for i in $(seq 1 40); do curl -s -o /dev/null "http://127.0.0.1:$PORT/health" && break; sleep 0.25; done
echo "stack up: http://127.0.0.1:$PORT  fpm_children=$CHILDREN session=$SESSION cache=$CACHE photo_cache=$PHOTOS schema=$SCHEMA"
curl -s "http://127.0.0.1:$PORT/health"; echo
