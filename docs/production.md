# Running Nail Polish Collector in production

How to set up a DigitalOcean droplet, and what the load tests (`load/README.md`) say about how much it can take.

## What to install

Ubuntu 24.04, then:

```
apt install nginx php8.3-fpm php8.3-{pgsql,gd,exif,mbstring,xml,curl,zip,intl} postgresql supervisor certbot python3-certbot-nginx composer
```

| What | File in this repo | Goes to |
|---|---|---|
| nginx site (TLS, photo cache, upload size) | `deploy/nginx/nailpolish.conf` | `/etc/nginx/sites-available/` (and the `fastcgi_cache_path` line in the `http {}` block) |
| nginx security headers | `deploy/nginx/security-headers.conf` | `/etc/nginx/snippets/nailpolish-security-headers.conf` |
| PHP-FPM pool (worker count) | `deploy/php/nailpolish-pool.conf` | `/etc/php/8.3/fpm/pool.d/` (remove the default `www.conf`) |
| PHP settings (OPcache, upload limits) | `deploy/php/nailpolish.ini` | `/etc/php/8.3/fpm/conf.d/90-nailpolish.ini` |
| Queue worker | `deploy/supervisor/nailpolish-worker.conf` | `/etc/supervisor/conf.d/` |
| Application settings | `deploy/env.production.example` | `/var/www/nailpolish/.env` |
| Deploying updates | `deploy/deploy.sh` | run from your machine |

Also needed:

- **Scheduler**: a cron line `* * * * * www-data php /var/www/nailpolish/artisan schedule:run >> /dev/null 2>&1`. It prunes expired sign-in links and invites.
- **Mail**: set a real `MAIL_*` provider. The default (`log`) sends nothing, so nobody could sign in. The queue worker must be running too, because the sign-in email is queued.
- **First Admin**: set `ADMIN_EMAIL`, then sign in with that address.
- **Postgres**: create a database and user; `max_connections` defaults to 100, which is plenty (see below). Raise `shared_buffers` to about a quarter of RAM.
- **Backups**: `pg_dump` the database and copy `storage/app/private/photos` (or use a Spaces bucket). Neither is in git.
- **Spaces for photos** (optional): `composer require league/flysystem-aws-s3-v3`, add an `s3` disk, set `PHOTOS_DISK`.

## What made the difference (measured)

On a 4-core test machine that was also running the load generator, nginx and Postgres, with 1,000 simulated people:

| Change | Result |
|---|---|
| Baseline (new database connection per request) | HTML pages p95 **6.2 s**; the site queues |
| **`DB_PERSISTENT=true`** (keep connections open) | pages p95 **1.1 s**; 500 users: p50 933 ms -> 28 ms |
| Sign-in takes no global lock for existing users | sign-in p95 2.5 s -> **88 ms** |
| Optimised Composer autoloader + OPcache | pages p95 **0.96 s** |
| nginx photo cache | **96-97%** of photo requests never reach PHP |
| File sessions/cache instead of database | **worse** (p95 1.9 s): PHP's session cleanup scans every file |
| 12 or 40 PHP workers instead of 24 | no better (24 won on 4 cores) |

## Sizing

- Each PHP worker uses about **50 MB** and one database connection. 24 workers need about 1.2 GB.
- Rule of thumb: **4-6 workers per vCPU**, never more than (RAM for PHP / 50 MB), and keep the total well under Postgres's `max_connections` (100).
- The load-test machine sustained about **180 page views per second** (each with its photos) while sharing its 4 cores with the load generator. A page costs 3-16 ms of PHP (3-8 database queries), so capacity scales roughly with vCPUs.
- A rough estimate from those numbers: **1,000 people actively browsing at once (a page every 3-8 seconds) wants about 4 vCPUs and 4-8 GB.** A smaller droplet (2 GB, 1-2 vCPU) is very likely fine for your expected ~100 users, but that is an extrapolation from a different machine, not a measurement. **Run the load test (`load/README.md`) against the real droplet before relying on any of these numbers.**

## Things to remember

- **Photos are checked on every request, then cached for 60 seconds.** Making a Manicure private or hiding it takes effect on the next request that misses the cache, so within a minute. See `docs/adr/0002-photos-served-through-the-app.md`.
- **Reload PHP-FPM after every deploy** (OPcache doesn't re-check files). `deploy/deploy.sh` does this.
- **`LOAD_TEST_MODE` must be `false`** in the real `.env`: it removes every rate limit.
- The first Laravel cache build after a deploy happens in `deploy.sh` (`config:cache`, `route:cache`, `view:cache`, `event:cache`).

## Go-live checklist

Tick these off in order; each is something that would silently not work if missed.

1. **Mail works.** `MAIL_*` set to a real provider; request a sign-in link for yourself and receive it. (`MAIL_MAILER=log` sends nothing.)
2. **Queue worker is running.** `supervisorctl status` shows `nailpolish-worker`; a sign-in email actually arrives. Without it no one can sign in.
3. **Scheduler cron is installed** (`schedule:run` every minute): old sign-in links, invites and import previews are cleared by it.
4. **`.env` is production**: `APP_ENV=production`, `APP_DEBUG=false`, `DB_PERSISTENT=true`, `LOAD_TEST_MODE=false`, `APP_URL` is the real https address. Then `php artisan config:cache route:cache view:cache event:cache`.
5. **You are the Admin**: `ADMIN_EMAIL` is yours; sign in, open `/admin`.
6. **Seed the lists**: `php artisan db:seed --force` (color families and finishes). Optionally `--class=FeaturedGallerySeeder` for the sample photos.
7. **HTTPS and headers**: certbot certificate installed; `curl -sI https://your-site/photos/<id>/thumb` still shows the security headers.
8. **Photos are cached by nginx**: run `load/check-photo-cache.sh https://your-site`; repeats show `HIT`, no cookie.
9. **Uploads work end to end**: upload a 10 MB photo from your phone (this exercises nginx's and PHP's size limits).
10. **PHP-FPM has OPcache on** (`php -i | grep opcache.enable`) and the pool size fits the droplet (see Sizing).
11. **Backups exist and have been restored once** (database dump + `storage/app/private/photos`).
12. **Load-tested on this droplet** from a second machine (`load/README.md`), then `drop schema loadtest cascade`, and `LOAD_TEST_MODE` is `false` again. `/health` must not show a `warning`.
13. **Invite people**: Admin → Invite requests / invite codes. The first 100 self-sign-ups need no code.
