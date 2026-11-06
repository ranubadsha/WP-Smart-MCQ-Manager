# Smart MCQ Manager — Step 6 (v0.6.0)

WordPress plugin for a private MCQ question bank, CSV/XLSX question import, exam creation, authenticated student exams, **server-side grading**, **answer review**, and **student result history**. This is a development release: test on a staging WordPress installation before production use.

## Requirements

- WordPress 6.0+, PHP 7.4+, MySQL/MariaDB compatible with your WordPress version.
- PHP ZIP + SimpleXML for Excel (.xlsx) imports only.
- HTTPS for authenticated exam delivery; logged-in users may take published exams.
- Exclude exam and history pages from full-page caching, and prevent caching of `/wp-json/smcq/v1/` endpoints.
- Make a full database backup before upgrading from v0.5.0.

## New in Step 6

- **Instant results** upon manual submission or server-enforced timeout (timeout finalized at the student's next server request).
- **Per-question / fixed marks**, negative deductions, earned score, maximum marks, score percentage, attempted accuracy, and pass/fail by the examination's **frozen passing percentage**.
- **Immutable per-attempt question and answer snapshots**, including frozen explanation text on attempts started in v0.6.0+. Editing the question bank after an attempt begins does not change its grade.
- Detailed answer review with the student's answer, correct answer, original option order, per-question earned marks, and explanation.
- Personal history page using `[smcq_history]`, with paginated attempts and a view-results button.
- Completed v0.5.0 attempts are scored on demand using their original snapshot and saved answers; if those snapshots did not store explanations, old result reviews show no explanation rather than inventing one.
- A new `GET /wp-json/smcq/v1/attempts/{id}/result` endpoint and `GET /wp-json/smcq/v1/history` endpoint. Both require authenticated WordPress REST cookies/nonces and are scoped to the **owner**. Active attempts cannot see answers or grade fields.
- Scoring is performed on the **server**. A version-checked database update prevents late writes, simultaneous submissions, or a different tab's answer save from corrupting the final grade. Repeated submissions return the existing stored result.
- Privacy exporter includes the grade summary; WordPress privacy erasure deletes attempts and grades for that user.

## How to use

1. From **Smart MCQ → Exams**, create an examination. Choose manual questions or automatic question generation, configure marks, negative marking, passing percentage, duration and attempt limits; then **Publish**.
2. Create a WordPress page with shortcode `[smcq_exam id="123"]` (replace 123 with the exam post ID).
3. Login as a student. Open the exam, start, answer, navigate, and submit. Results appear immediately, with expandable answer reviews. When time expires, only saved answers count.
4. Create a separate WordPress page using `[smcq_history]`. Students will see **only their own** completed attempts, grades and answer reviews. The exam page also shows a **View Latest Result** button if a submitted attempt exists.
5. Test both a correct answer and a wrong answer with negative marking on a staging site before publishing real examinations.

### Grading example

- Correct questions: 7 × 1 mark = 7 points.
- Wrong questions: 2 × −0.25 = −0.50 point.
- Skipped questions: 1 × 0 = 0.
- Net score: **6.50 / 10** (65%); attempted accuracy: 7 / 9 = 77.78%.
- The pass threshold is calculated from the frozen maximum available score, not the number of answered questions. Negative net scores can be reported; no negative points are awarded for skipped questions.

## Data model and upgrades

The existing `{$wpdb->prefix}smcq_attempts` table gains one nullable `result_json LONGTEXT` column (schema version 2). Existing question posts, taxonomies, importer data, exam metadata and attempts are preserved. No separate student user table is created.

**Installation/upgrade:** In **Plugins → Add New → Upload Plugin**, upload `smart-mcq-manager-step6-v0.6.0.zip`, then select **Replace current with uploaded**. Keep the existing `smart-mcq-manager` plugin directory. **Do not uninstall first.** When upgrading, WordPress runs the schema migration. Check the database table gained `result_json`; test as a Subscriber. If an existing Step 5 attempt was already submitted, its score will be calculated when its result or history entry is requested.

## Security and constraints

All result and history API endpoints require authentication; attempting to access another user's attempt is rejected. Student active-state payloads continue to exclude correct answers, explanations, and marks. A failed answer save must be retried before submitting; Step 6 does **not** queue offline answers. The countdown is server-authoritative and timeout finalization is lazy; a browser that closes at the deadline does not force a background job.

**Current limitations:** No public leaderboard, teacher/administrator result dashboard, certificates, scheduled delayed answer publication, offline saving, WooCommerce access control, or anticheating proctoring. Students can view their own correct answers as soon as an attempt is submitted, even while other students are still taking the same exam. Use a future delayed-review setting for high-stakes live tests. The exam builder limits question sets to 200; importer caps files at 500 rows / 3 MiB. Verify hosting performance, caching configuration, database privileges and real concurrency before production use.

## Development checks

```bash
find . -name '*.php' -print0 | xargs -0 -n1 php -l
php tests/test-importer-cli.php
php tests/test-exams-cli.php
php tests/test-player-cli.php
php tests/test-results-cli.php
node --check assets/player.js
```

The CLI checks validate helper behavior; they do not replace real WordPress REST, database migration, and browser integration tests. CI runs the PHP tests on PHP 7.4/8.2/8.3 and checks JavaScript syntax.

## Planned Step 7

Student progress and topic analytics, instructor results dashboard, and leaderboards (with appropriate result privacy and disclosure settings).

License: GPL-2.0-or-later.