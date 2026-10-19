#!/usr/bin/env bash
RUN="${LOADTEST_DIR:-$HOME/.cache/nailpolish-loadtest}"
for f in "$RUN/nginx.pid" "$RUN/php-fpm.pid"; do
  [ -f "$f" ] && kill "$(cat "$f")" 2>/dev/null
done
sleep 0.5
echo "stack stopped"
