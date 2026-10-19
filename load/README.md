# Load testing

Answers one question: **does the site hold up when 1,000 people use it at once?** Everything here is for testing only
and never touches real data.

## Last results (this repo, 4-core dev machine)

1,000 simulated users, 60 s ramp-up then 90 s steady, on a deliberately small test machine: its 4 cores also ran the
load generator (k6), nginx, 24 PHP-FPM workers and Postgres. Pass thresholds: under 1% failed requests, HTML pages
p95 under 1,500 ms, photos p95 under 500 ms, sign-in success over 99%.

| Configuration | Pages p50 / p95 | Photos p95 | Sign-in p95 | Failed | Verdict |
|---|---|---|---|---|---|
| Baseline: database sessions + cache, a new DB connection per request | 5,242 / 6,245 ms | 61 ms | 4,943 ms | 0% | **fails** |
| + persistent DB connections (`DB_PERSISTENT=true`) | 539 / 1,146 ms | 271 ms | 2,522 ms | 0% | passes |
| File sessions + file cache instead | 896 / 1,879 ms | 73 ms | 84 ms | 0% | fails (pages) |
| **+ sign-in without global lock + optimised autoloader (recommended)** | **526 / 959 ms** | **254 ms** | **88 ms** | **0%** | **passes** |
| same, 12 PHP workers | 880 / 1,314 ms | 29 ms | 78 ms | 0% | passes, slower |
| same, 40 PHP workers | 258 / 1,044 ms | 366 ms | 83 ms | 0% | passes, no better |

**Soak:** the recommended configuration held 1,000 users for 5 minutes (582,595 requests, 66,692 page views, 0% failed;
pages p50 649 ms / p95 1,175 ms; photos p95 259 ms; sign-in 100%), passing every threshold. PHP memory was flat
(1,246 MB mid-run, 1,227 MB after: no leak). The machine was fully saturated (4% idle CPU), so a bigger one would have
more headroom.

At 500 users the persistent-connection change took pages from p50 933 ms / p95 1,391 ms to **28 ms / 100 ms**.
Photos: **96-97%** were answered by nginx's cache without PHP (`load/check-photo-cache.sh` shows it directly).
Raw numbers are written to `$LOADTEST_DIR/results/*.json`. What is and isn't proven is at the end of this file.

## What the test does (`k6-scenario.js`)

Each virtual user is a person, not a hammer. About 30% are signed in through the **real magic-link flow** (using the
single-use tokens `LoadTestSeeder` writes); the rest are guests. They browse the Gallery (with filters and sorting),
Catalog brand pages, Polish pages and Manicure pages, view their collection, and heart Manicures, pausing 2-6 seconds
between page views. After each page the virtual browser fetches its photos six at a time. The dataset is 300 users,
6,000 polishes, 12,000 bottles, 2,400 manicures, about 4,800 photos and 4,500 hearts.

## Running it on this machine

```bash
# 1. An isolated schema in your Postgres, with realistic data. The seeder refuses to touch the real 'public' schema.
psql ... -c 'create schema loadtest'
DB_SEARCH_PATH=loadtest php artisan migrate --force
DB_SEARCH_PATH=loadtest php artisan db:seed --class=LoadTestSeeder --force

# 2. A private production-like stack: own nginx + PHP-FPM (OPcache on, Xdebug off) on 127.0.0.1:8081.
#    It does not touch your normal nginx or PHP-FPM. Knobs are documented at the top of the script.
export LOADTEST_DIR=$HOME/.cache/nailpolish-loadtest
DB_PERSISTENT=true load/local-stack/start.sh

# 3. k6 (https://k6.io/docs/get-started/installation/). Each run mints fresh sign-in tokens, runs, and samples CPU.
export K6_BIN=$(command -v k6)
load/run-one.sh 1000 my-run          # 1000 virtual users

load/check-photo-cache.sh http://127.0.0.1:8081
php load/profile.php                 # per-page milliseconds, queries and SQL time (run with the app's environment)
load/local-stack/stop.sh
```

Clean up afterwards: `drop schema loadtest cascade` and delete `storage/app/private/photos/loadtest`.

## Running it against the real droplet

Run k6 from a **different machine** (a second small droplet is ideal), or the test mostly measures the load generator.

1. On the droplet, create the isolated schema and a second app environment for it: a copy of `.env` with
   `DB_SEARCH_PATH=loadtest` and `LOAD_TEST_MODE=true`, served on its own hostname (or temporarily as the main one while
   no real users are on it). Run `migrate` and `LoadTestSeeder` with that environment. `LOAD_TEST_MODE` lifts the rate
   limits so one machine can act as 1,000 people: **never leave it on**.
2. Copy `storage/app/loadtest-users.json` (single-use sign-in tokens) to the machine running k6.
3. `k6 run -e BASE=https://your-test-hostname -e VUS=1000 -e USERS_FILE=/path/to/loadtest-users.json load/k6-scenario.js`
4. Look at the thresholds at the bottom: k6 exits non-zero if the site does not hold. Watch `top`, `pg_stat_activity`
   and the PHP-FPM status while it runs. If pages queue, add vCPUs or PHP workers (see `docs/production.md`).

## What this does and does not show

- **Shown:** the application code is cheap (3-16 ms and 3-8 queries per page); the bottlenecks were connection setup and a
  global sign-in lock, both fixed; public photos are served by nginx's cache, not PHP; nothing broke at 1,000 users.
- **Not shown:** performance on your actual droplet. This machine is a different CPU, shared with the load generator.
  Treat the numbers as relative (what helped) and re-run on the droplet for absolute ones.
- The dataset's photos are small (about 50 KB), real photos are larger, so bandwidth is understated.
- Only guests and signed-in browsing were simulated: no photo uploads (they are heavy PHP work: expect about 100-300 ms each).
- The soak lasted 5 minutes, not hours: slow leaks over days are not covered.
