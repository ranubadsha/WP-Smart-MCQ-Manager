# Production sign-off checklist (not yet signed off)

Every item below requires a real staging environment and evidence before a public or high-stakes launch:

- [ ] Fresh and upgrade installation against WordPress 6.x/7.x on PHP 8.2+.
- [ ] Verify all five plugin data tables and indexes; run `wp smcq doctor`.
- [ ] Check question authors, reviewers, administrators, and students can access only their permitted pages and REST endpoints.
- [ ] Verify question approvals cannot be bypassed via WordPress native post actions or REST.
- [ ] Upload Bangla CSV, XLSX, DOCX, JSON; confirm Unicode, files/limits, correct grading and no data loss.
- [ ] Reproduce single-answer, multiple-answer, True/False, passage, image, audio and plain-text mathematics questions in desktop/mobile exams.
- [ ] Confirm saved attempt snapshots stay immutable after question edits or version restores.
- [ ] Verify server-timeout auto-submit and back-to-back concurrent answer submissions.
- [ ] Check no answer key leaks to public pages or active sessions, including REST responses and caches.
- [ ] Confirm custom/daily/weekly practice never exposes unreleased private questions.
- [ ] Test WooCommerce orders, refunds, subscriptions and coupon/group products with real sandbox gateways.
- [ ] Check privacy export/deletion behavior on the new and existing records.
- [ ] Test admin AI prompts on staging with low-cost key, budget controls, failure paths and manual review process.
- [ ] Test 500+ concurrent student sessions and worst-case query plans against production-sized datasets.
- [ ] Perform independent application security review and accessibility/Bangla localization audit.
- [ ] Record GitHub Actions integration run URL/results (workflow existence alone is not a pass).
- [ ] Rehearse rollback with a database backup and production-like sample data.

**Status:** Not signed off. No externally validated load/penetration/checkout testing has been completed by this assistant.