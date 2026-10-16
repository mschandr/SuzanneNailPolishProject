# 09: Import framework and Holo Taco adapter

**What to build:** Having checked the site's terms and robots.txt, the Admin picks a Vendor in the admin panel and runs a queued Import. It adds only new Polishes (matched by Vendor + shade name) with local image copies and the source URL, rate-limited, leaving existing records and manual edits untouched, and shows a summary.

**Blocked by:** 05

**Status:** ready-for-agent

- [ ] Terms and robots.txt check documented before the adapter is written
- [ ] Import runs as a queued job from the admin panel
- [ ] Re-running adds only new Polishes
- [ ] Manual edits survive a re-import
- [ ] Summary lists what was added
