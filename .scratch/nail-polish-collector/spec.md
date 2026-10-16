# Nail Polish Collector: spec

Status: draft, for review. Vocabulary is defined in `CONTEXT.md`; stack in `docs/adr/0001-laravel-postgres-digitalocean.md`.

## Summary
A multi-user web app (responsive, phone-friendly views, usable on desktop) where each User records which Polishes they own and where they are stored. A public Catalog of Polishes is imported from Vendors by the Admin. Users can also photograph Manicures and share them in a public Gallery.

## Accounts and access
- Sign-in by magic link to an email address; no passwords or social login.
- Self-signup is open to exactly the first 100 Users (Admin and invited Users not counted). Then the page closes and shows an Invite request form (collects email addresses).
- After that, the Admin generates a single-use Invite code for a specific email address, on the fly, unlimited.
- Display name required at signup; email never shown publicly.
- Admin is a role; initially one person, grantable to others.
- First-run tutorial (3-4 steps: add Polishes you own, check whether you own one, create Locations); skippable, replayable from Help.
- Self-serve "delete my account" with confirmation: permanently deletes everything keyed to the user (FK `ON DELETE CASCADE` on every user-owned table, with a test that fails when a new one lacks it), plus a queued job removing image files from Spaces. Backups roll off after ~30 days. Promoted Polishes stay in the Catalog with no link to their creator. Hearts the User gave are removed.

## Catalog (public, Admin-edited)
- Polish identity: brand + shade name; attributes: collection, Finish (multiple), Color family (exactly one), Swatch photo, Vendor link.
- Finish and Color family are fixed lists; the Admin can add values from a simple screen. Users may not.
- Users can create Private Polishes (visible only to them); the Admin can promote one to the public Catalog.
- IDs (Users, Polishes) are UUIDs. Polish URLs: `/<brand>/<uuid>/<color-name>`; the UUID decides identity, a wrong slug redirects to the canonical URL. Private Polishes return 404 to anyone but the owner.
- Catalog is publicly browsable without an account.

## Import (Admin panel only, no CLI)
- Admin picks a Vendor and runs an Import from the admin screen (e.g. on a launch day).
- First Vendors: Holo Taco, ILNP, Mooncat. One adapter each; prefer the site's public product JSON if it is a Shopify store. Check each site's terms and robots.txt before writing an adapter; rate-limit; keep local copies of images and the source URL.
- Import only adds new Polishes (matched by Vendor + shade name), leaves existing records and manual edits untouched, and shows a summary of what was added.
- An Import also marks Polishes that vanished from the Vendor's listing as Discontinued.
- Runs as a queued job.

## Discontinued and Watchlist
- Discontinued Polishes show a "no longer sold" notice on the Polish record, read from stored results (never a live check on view).
- They go on the Watchlist, re-checked monthly for 6 months, quarterly for the next 18 months, then yearly with no end. A Polish that returns leaves the Watchlist and the notice goes away.
- Active Polishes are only refreshed by Imports.

## Collection
- Browse the Catalog by brand with swatch images; tap to mark owned; multi-select to set a Location in bulk. Also search "what I own" (only the current User's Bottles).
- A Bottle belongs to one User and one Polish, with a Location and a Status (owned, used up, given away, discarded). Two Bottles of one Polish are two records. Non-owned Bottles remain as history.
- Locations are private, free-named, created inline, flat (no nesting).
- Wishlist: out of scope for now.

## Manicures and Gallery
- A Manicure: up to 4 photos (10 MB each, resized, EXIF stripped), date, optional caption, and an ordered list of Polishes worn for the whole hand (optionally linked to the User's Bottle). Advanced mode: assign Polishes per nail (10 nails).
- Visibility: account default public; per-Manicure override; changes apply immediately. Soft cap of 500 photos per User.
- Public Manicures appear on the page of each Polish used, on the owner's profile, and in the Gallery.
- Gallery: searchable/filterable by Polish, brand, Finish, Color family, display name; sorted by newest or most hearted.
- Heart: one per User per Manicure, not on your own; shows a count; saves to a Liked list. No comments, followers or separate upvotes.
- Report button on public Manicures; the Admin can hide/delete a Manicure and suspend a User. No automated moderation.

## Non-functional
- Handle 1000 simultaneous users on a small droplet: cache Catalog pages, serve images from Spaces + CDN, run Imports/Watchlist checks as queued jobs.
- Phone-friendly views are a priority for in-store "do I own this?" lookups.

## Out of scope
Wishlist, nested Locations, social features beyond Hearts, automated moderation, CLI import, barcodes, external catalog integration, other Vendors (Rogue Lacquer, OPI, Zoya later).
