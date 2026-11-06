# Smart MCQ Manager — Step 12 (v1.2.0)

**Step 12 upgrades the existing WordPress MCQ plugin from v1.1.0 to v1.2.0.** The core features remain: private question bank, CSV/XLSX import, examination builder, authenticated exam player, server-side grading, student analytics, smart practice, private exports, optional anti-cheating monitoring, and WooCommerce paid-exam access. Existing question, examination, attempt and practice data is retained.

## New in Step 12 — database performance and health checks

1. **Composite indexes for high-volume exams and reports.** The `smcq_attempts` table adds `(user_id,status,id)`, `(exam_id,status,id)`, `(status,id)`, and `(status,deadline_at)` indexes. These target student history, exam reports/leaderboards, submitted-attempt reports, and timeout checks. The `smcq_practice_sessions` table adds `(user_id,status,id)` for student revision history. WordPress `dbDelta()` creates missing indexes without deleting existing records.
2. **Versioned, validated migrations.** Attempt schema version advances to **3** and practice schema version to **2**. Version options are only updated when the expected indexes actually exist; failed migrations remain visible rather than falsely reporting success. New installs get the latest schema automatically.
3. **Private System Status screen.** Go to **Smart MCQ → System Status**. Checks PHP/WordPress minimum versions, ZIP, SimpleXML, HTTPS, security-log cleanup scheduling, plugin schema versions, five plugin data tables and required index names. WooCommerce is reported as an **optional** dependency for premium exams. No attempt answers, student identities, API secrets, or payment data are shown.
4. **Administrator-triggered schema repair.** The system screen provides **Repair plugin schema**, protected by administrator capability checks and a WordPress nonce. It retries non-destructive table/index creation for attempts, practice, and security tables. **Back up the database first**; adding indexes can lock large tables temporarily.
5. **Optional WP-CLI health command.** On a server with WP-CLI installed, use `wp smcq doctor` or `wp smcq doctor --format=json`. Missing mandatory tables/indexes produce a nonzero exit status, suitable for deployment checks.
6. **New regression checks.** The PHP CLI tests verify index definitions, schema versions, diagnostic warnings, missing tables/indexes, and safe identifier validation. The existing nine suites remain unchanged.

The schema changes are aimed at **query access patterns**, not guaranteed speedup figures. Performance depends on table size, database configuration, host capacity, and actual load.

## Installation and staging verification

1. **Back up both the database and your existing plugin files.** First upgrade a *staging* copy of the site.
2. In WordPress, visit **Plugins → Add New → Upload Plugin**, choose `smart-mcq-manager-step12-v1.2.0.zip` and **Replace current with uploaded**.
3. Reopen **Smart MCQ → System Status**. Verify all table and schema checks pass. Warnings for optional services or PHP extensions may be acceptable depending on how the site is used.
4. If an index/table error remains, check your database privileges and server logs. After a backup, select **Repair plugin schema** in staging, and rerun the checks. For large installations, schedule schema migration during a maintenance window.
5. Confirm an existing student can resume an attempt, submit and see a result, access practice bookmarks, and download their own report. Check exam history and administrator reports.
6. Test premium access with WooCommerce enabled, including another student's account, a refund, and an expired entitlement; confirm no protected answer keys are exposed to an active exam session.
7. Run timed-load tests with representative question-bank size, simulated users, real database volume and WooCommerce orders **before any high-stakes launch**. The bundled unit tests do **not** replace live WordPress integration or load tests.

### Optional command-line checks

Run in a WordPress environment with WP-CLI:

```bash
wp smcq doctor
wp smcq doctor --format=json
```

Run from a clone of this repository without WordPress:

```bash
find . -name '*.php' -print0 | xargs -0 -n1 php -l
for f in tests/test-*-cli.php; do php "$f"; done
for f in assets/*.js; do node --check "$f"; done
```

## Compatibility and operational notes

- Requires **WordPress 6.0+** and **PHP 7.4+**. Supported configurations and installed extensions still need staging verification.
- PHP ZIP and SimpleXML are needed for relevant Excel operations; CSV is available without them. PHP ZIP is not available in the development CLI used for this build, so native XLSX runtime tests require a server with those extensions.
- Exam deadlines are **server-side**. WP-Cron handles scheduled audit-log cleanup, but depends on traffic unless replaced with a reliable system cron.
- WooCommerce is **optional** for free exams. For paid exams, live store/HPOS/subscription integration must be tested; this version retains the 2,000-order scan cap per customer and is not a large-scale entitlement ledger.
- Schema migrations run on upgrade, not by dropping tables; this plugin **does not automatically remove your data on deactivation**.
- Changing table indexes can increase write overhead and temporarily lock tables on some hosts. Do not perform the repair during a live high-stakes examination.
- Historical rankings still have the documented limits and should not be published as certified final results without fully finalized submissions.

### Existing shortcodes

```text
[smcq_exam id="123"]
[smcq_history]
[smcq_dashboard]
[smcq_my_report]
[smcq_practice]
[smcq_leaderboard id="123"]
```

### Release history

- **v1.2.0 / Step 12:** Index migrations, private system status, schema repair, WP-CLI doctor and regression tests.
- **v1.1.0 / Step 11:** WooCommerce exam entitlements, packages, access expiry and subscriptions compatibility.
- **v1.0.0 / Step 10:** Optional audit logging, session leases and administrator security event reporting.
- **v0.9.0 / Step 9:** Exports and private reporting.
- **v0.8.0 / Step 8:** Smart practice, wrong-answer revision and bookmarks.
- **v0.7.0 / Step 7:** Analytics, student dashboard and anonymous leaderboard.
- **v0.6.0 / Step 6:** Grading and answer review.
- **v0.5.0 / Step 5:** Authenticated student exam interface.
- **v0.4.0 / Step 4:** Exam builder.
- **v0.3.0 / Step 3:** Bulk import.
- **v0.2.0 / Step 2:** Question metadata and filters.
- **v0.1.0 / Step 1:** Initial question bank.

GPL-2.0-or-later.