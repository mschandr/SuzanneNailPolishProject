#!/usr/bin/env bash
# Confirms that public photos are served by nginx's cache (not PHP) and that nothing private is cached.
#   load/check-photo-cache.sh https://example.com
set -euo pipefail
BASE="${1:?base url, e.g. https://example.com}"
GALLERY="$(curl -sk "$BASE/gallery")"
PHOTO="$(echo "$GALLERY" | grep -o '/photos/[0-9a-f-]\{36\}/thumb' | head -1)"
[ -n "$PHOTO" ] || { echo "No public photo found in the Gallery: add a public Manicure first."; exit 1; }

echo "photo: $PHOTO"
for i in 1 2 3; do
  H="$(curl -sk -D - -o /dev/null "$BASE$PHOTO")"
  printf "request %d: %s  cache=%s  cookie=%s  cache-control=%s\n" "$i" \
    "$(echo "$H" | head -1 | tr -d '\r')" \
    "$(echo "$H" | grep -i '^x-cache-status' | awk '{print $2}' | tr -d '\r')" \
    "$(echo "$H" | grep -ic '^set-cookie' || true)" \
    "$(echo "$H" | grep -i '^cache-control' | cut -d: -f2- | tr -d '\r')"
done
echo
echo "Expected: request 1 MISS, EXPIRED or HIT, requests 2-3 HIT; cookie=0; cache-control public, max-age=60."
