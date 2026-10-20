# UI review (October 2026)

A look at every screen: 58 screens, each captured at phone width (390 px) and desktop (1280 px) in a real browser, signed
out, signed in, as a new user and as the Admin, on a production-style stack with realistic data. Each page was also
measured (overflow, tap-target size, text size, headings, landmarks, page titles) and scanned with axe-core for
accessibility problems. Findings are in priority order; nothing here has been changed except the two bugs marked FIXED.

## In one paragraph

The app works and its structure is sound: every page has a language, a viewport, one main landmark and meaningful
headings (mostly), every image has alt text, nothing overflows on a phone except one admin table, the privacy labels are
clear ("Public" / "Only you"), and the Gallery grid is the best screen. But it has **no visual design**: there is no
stylesheet (about 15 lines of CSS in the layout, plus 57 inline `style=""` attributes in 16 views), so every control is a
browser default and every screen reads as a prototype in a plum header. For something that will be handed to around 100
strangers, and used on phones in shops, that is the main thing to fix.

## Fixed during the review (found by looking at the screens)

- **FIXED: the "Polishes worn" page returned a 500 error on Postgres** for every Manicure (`GROUP BY` with an inherited
  `ORDER BY`; SQLite accepts it, Postgres does not). A test now guards the cause.
- **FIXED: swatch images were broken** whenever the site wasn't opened at the `APP_URL` in `.env` (on a dev machine that is
  `http://localhost:8000`). Swatch links are now relative (`/storage/...`).
- Lesson: the test suite only ran on SQLite. `tests/run-on-postgres.sh` now runs all of it on real Postgres (660/660 pass).

## Must fix before real users (P1)

1. **A design foundation.** One small stylesheet (CSS variables for the plum palette, a type scale, spacing) and shared
   styles for buttons (primary, secondary, danger), form fields, cards, flash messages and tables. A hand-written
   ~300-line file keeps the no-build deploy; replace the inline styles with it.
2. **Navigation.** Signed in, there are 7-9 links that wrap onto three lines on a phone and use a fifth of the first
   screen, each only 19 px tall (the guideline is 44 px). "Sign out" is a default grey button among the links and nothing
   shows the current page. Use a compact bar with a menu button, group the "My ..." pages, keep the Admin link separate, and
   mark the current page.
3. **Tap targets.** 158 links on the Catalog page, 40-54 on Gallery, Collection and the admin lists are under 32 px.
4. **Home and landing pages say nothing.** The signed-out page is one sentence with no call to action. The signed-in Home
   shows "You have 6 bottles" and three links. The most important phone task, *"do I already own this?"*, is not on it:
   put the collection search there (and in the nav), plus recent additions and quick actions.
5. **Forms need a layout.** Labels sit beside the field, text boxes are narrow, date pickers show the browser's locale, the
   file chooser has no preview, and inputs under 16 px make iPhones zoom in when tapped. Put labels above fields, make them
   full width on a phone, show errors under the field, and give the photo upload **progress and a disabled button while it
   sends**: four 10 MB photos on mobile data take a while, and a second tap would create a duplicate Manicure.
6. **Destructive buttons sit beside harmless ones.** "Delete this manicure" is directly under "Save changes" with the same
   look; "Remove" sits beside "Save" on every Bottle; on the Admin moderation screen *Delete* and *Suspend* (with unlabeled
   reason boxes) sit beside *Dismiss* and *Hide*. Style them as danger, separate them, and keep the confirmation.
7. **Error pages are Laravel's bare defaults.** The 404, 403, 419 ("page expired") and 429 pages have no header, no way back
   and no explanation. Make branded ones, in plain language ("Your session expired, please try again").
8. **Page titles and headings.** 15 of 58 screens have the same tab title ("Nail Polish Collector"), so history, bookmarks
   and screen readers can't tell them apart. 9 screens have no `<h1>` (welcome, "check your email", the Manicure pages, Home).
9. **Accessibility (axe-core).** *Critical:* 33 text boxes have no label (the rename fields on Locations, Finishes and Color
   families). *Serious:* the pagination markup uses a forbidden ARIA attribute. Also: two unnamed `<nav>` landmarks, a
   redundant `alt`, an empty table header, no skip-to-content link, and form errors not tied to their fields.
10. **Legal pages.** Users upload photos that are public by default, yet there is no Privacy Policy or Terms link anywhere.

## Should fix (P2)

- **Collection screen:** dense rows with no swatch, the location shown twice (as text and as a select), Save and Remove
  stacked on a phone, and two identical bottles that can't be told apart. Use cards with a swatch, collapse the edit
  controls, and offer grouping by location.
- **Gallery:** the six filter fields stay open and fill the first phone screen; collapse them behind a "Filters" button
  with chips for what's active. Captions are tiny, hearts are text, and nothing shows a tile is tappable (no hover or focus
  style). The pagination is plain "« Previous Next »".
- **Manicure page:** photos of different heights stacked or side by side with no swipe or zoom; "Polishes worn" is a plain
  list with no swatches (the polish's look is the point); Heart should be a clear button; "Report" belongs in a small menu.
- **Catalog:** a 150-item bullet list with no search or A-Z. There is also **no way to search the whole Catalog for a polish
  by name** (Collection search covers only what you own): the obvious missing feature.
- **Brand page bulk selection:** a "Select" label above each image makes the grid uneven; use a checkbox overlaid on the
  tile, a selected state, and an action bar that appears (with a count) only when something is ticked.
- **"Polishes worn" screen:** the nail picker is a good idea; draw it as a hand with ten nails rather than a text grid, and
  show search results as cards with swatches.
- **Admin:** the import preview table overflows a phone by 67 px (the header stops short of the page). Make tables
  responsive, add a dashboard with counts (open reports, needs review, invite requests) and one consistent admin layout.
- **Empty states and feedback:** "No manicures found." should invite an action ("Upload your first manicure"); success
  messages need styling.
- **First impression:** the sign-in page doesn't say "no password, we'll email you a link", the "check your email" page has
  no heading, no "didn't get it?" help and no link-expiry note, and the 100-places limit isn't mentioned anywhere.

## Polish (P3)

- **Spelling:** "Color family" in the labels but "colour family" in 7 messages (import results, the review list). Pick one
  (the glossary says Color).
- Image `width`/`height` attributes (to stop layout jumping), a hover/focus style on every link and button, `color-scheme`,
  a wordmark, a proper footer (credits, help, terms, privacy), and a favicon in the brand color.

## What I'd do, in order

1. Foundation: stylesheet, layout, navigation, error pages, titles, headings, labels (about a day or two).
2. The screens people use most: Home, Collection, Gallery filters, the Manicure page, brand-page selection, forms and upload feedback.
3. Admin consistency, the hand diagram, a landing page with screenshots, legal pages.

Screenshots and the raw measurements are in the job's scratch folder; rerun them by seeding the `loadtest` schema
(`database/seeders/LoadTestSeeder.php`) and driving the pages with Playwright.
