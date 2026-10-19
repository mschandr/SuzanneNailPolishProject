# 19: Load check at 1000 concurrent users

**What to build:** A load test against the target of 1000 simultaneous users on the droplet, tuning caching and CDN until it holds.

**Blocked by:** 15

**Status:** ready-for-agent

- [x] Load test script committed (`load/k6-scenario.js`, `load/README.md`)
- [x] Target met with documented settings (1,000 users for 5 minutes, all thresholds pass: `load/README.md`, `docs/production.md`)
- [x] Images confirmed to bypass the app server (96-97% of photo requests answered by nginx's cache: `load/check-photo-cache.sh`, `docs/adr/0002-photos-served-through-the-app.md`)
