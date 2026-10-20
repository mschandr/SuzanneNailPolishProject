# Nail Polish Collector

A multi-user web app for keeping track of the nail polishes you own and where they are, and for sharing photos of the
manicures you wear them in.

- **Catalog**: a public list of polishes (brand, shade, finish, colour family), built by the Admin by hand, by CSV import,
  and by promoting polishes users add privately.
- **Collection**: mark the polishes you own, with the place each one is kept; tick many at once; search "do I own this?"
  from your phone in a shop. Shades the Catalog doesn't have can be added as private polishes.
- **Manicures and the Gallery**: upload photos (metadata stripped, private by choice), list the polishes worn (for the
  whole hand or nail by nail), and browse and heart other people's, filtered by polish, brand, finish or colour.
- **Admin**: import polishes, review reports, hide or delete Manicures, suspend users, send invite codes.
- Sign-in is by emailed link. The first 100 people can sign up themselves; after that it's by invitation.

Laravel 13 / PHP 8.3 / PostgreSQL. Domain vocabulary is in [`CONTEXT.md`](CONTEXT.md); decisions are in [`docs/adr/`](docs/adr).

## Running it locally

```bash
composer install
cp .env.example .env && php artisan key:generate
# create a Postgres database, put its details in .env (and set ADMIN_EMAIL to your own address), then:
php artisan migrate
php artisan db:seed                                   # colour families and finishes
php artisan db:seed --class=MythicalCatalogSeeder     # optional: 10 made-up polishes to play with
php artisan db:seed --class=FeaturedGallerySeeder     # optional: two openly licensed sample photos
php artisan storage:link
php artisan serve
php artisan queue:work                                # sign-in emails are queued: they never send without this
```

With the default `MAIL_MAILER=log` the sign-in link is written to `storage/logs/laravel.log` instead of being emailed.
Sign in with the `ADMIN_EMAIL` address to become the Admin.

## Tests

```bash
php artisan test
```

The suite runs on in-memory SQLite and a cache that serializes values like production's. SQLite is not Postgres, so
`load/profile.php` and the checks described in `load/README.md` also exist for the real database.

## Putting it on a server

- [`docs/production.md`](docs/production.md): what to install, the settings that mattered in load testing, sizing, and a
  go-live checklist.
- [`deploy/`](deploy): nginx, PHP-FPM, OPcache, supervisor and `.env` templates, and `deploy.sh`.
- [`load/README.md`](load/README.md): a k6 load test that logs real users in and browses like people do, plus how to run it
  on the real droplet.

## What isn't built

- Reading product data from Vendors' websites. Their terms forbid it, so polishes come in by CSV
  (`/admin/imports`). Discontinued and Watchlist checks would need a Vendor's written permission.
- Wishlists, comments and followers (deliberately left out for now).
