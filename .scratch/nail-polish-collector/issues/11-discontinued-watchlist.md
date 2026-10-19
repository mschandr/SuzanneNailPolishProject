# 11: Discontinued and Watchlist

**What to build:** An Import marks Polishes that vanished from the Vendor's listing Discontinued; the Polish page shows a stored 'no longer sold' notice. A scheduled job re-checks the Watchlist monthly for 6 months, quarterly for 18 months, then yearly, and clears the notice if the Polish returns.

**Blocked by:** 09

**Status:** parked (depends on a Vendor data source)

- [ ] Vanished Polishes become Discontinued after an Import
- [ ] Notice is shown from stored data, never a live check on view
- [ ] Schedule follows the agreed cadence
- [ ] A returning Polish leaves the Watchlist and the notice disappears

Not built. Detecting that a Polish is no longer sold means checking a Vendor's pages or feed, which needs a Vendor's permission (ticket 10). Revisit once a Vendor agrees, or if a Vendor supplies a regular file.
