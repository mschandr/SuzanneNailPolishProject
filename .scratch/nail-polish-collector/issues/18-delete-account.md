# 18: Delete my account

**What to build:** A User permanently deletes their account with a confirmation. Every user-owned table cascades on delete (with a test that fails if a table lacks it), a queued job removes their image files from Spaces, their Hearts are removed, and promoted Polishes stay in the Catalog.

**Blocked by:** 08, 13

**Status:** ready-for-agent

- [ ] Confirmation step
- [ ] All user-owned rows removed via cascade
- [ ] Test fails when a user-owned table lacks the cascade
- [ ] Images removed from Spaces by a job
- [ ] Promoted Polishes remain
