# 09: Import framework (file-based)

**What to build:** The Admin adds many Polishes to the Catalog from a CSV file, with a preview and a checklist before anything is added. Nothing is scraped from any Vendor's site.

**Redesigned:** this ticket began as "pull polishes from Holo Taco". Holo Taco's terms forbid it, and ILNP's and Mooncat's do too (see `.scratch/vendor-terms/`), so the Import takes a file the Admin supplies instead.

**Blocked by:** None.

**Status:** done

- [x] Upload a CSV (template provided); columns brand, shade (required), collection, color_family, finishes, vendor_url, image_url
- [x] Preview with counts and a checklist: new rows ticked, existing / duplicate / invalid rows explained and not selectable
- [x] Only ticked new rows are added; existing polishes and the Admin's edits are never touched; re-importing is safe
- [x] Rows missing a colour family or finish are added and flagged "needs review", with an Admin list that clears on save
- [x] Image links are stored as links, never copied; only https links are shown
- [x] Audit-logged, cache refreshed, unapplied previews expire after 7 days
