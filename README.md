# Smart MCQ Manager — Step 7 (v0.7.0)

WordPress MCQ question bank, CSV/XLSX importer, exam builder, authenticated examination player, secure grading, student history, **student analytics**, **administrator exam reports** and **opt-in anonymous leaderboards**.

> Development release: back up your database and test the update on staging. Automated helper tests are not a substitute for WordPress integration/concurrency testing.

## Requirements

- WordPress 6.0+, PHP 7.4+, compatible MySQL/MariaDB.
- PHP ZIP and SimpleXML for XLSX imports.
- HTTPS for login/session security.
- Exclude pages containing MCQ shortcodes and `/wp-json/smcq/v1/` from full-page or proxy caching. The analytics request uses same-origin credentials and a WordPress REST nonce.

## What Step 7 adds

1. **Student dashboard:** `[smcq_dashboard]` shows the student's completed attempts, sampled average score, passes, correct/wrong/skipped counts, recent score trend, and subject/topic accuracy with low-sample warnings.
2. **Weak-topic suggestions:** Five lowest-accuracy topics **with at least three answered questions**. These are learning suggestions, not definitive diagnoses.
3. **Frozen topic metadata:** New attempts store the question's subject and topic names at attempt start. Questions edited later do not change historical topic analytics. **Old Step 5/6 attempts lack frozen subject/topic labels**, so those attempts contribute to scores but not category breakdowns.
4. **Administrator exam reports:** **Smart MCQ → Exam Reports** shows completed attempts for a selected exam, recent sampled average score, paginated student scores, accuracy, pass/fail and submit time. Access is restricted to WordPress administrators (`manage_options`).
5. **Optional anonymous leaderboard:** Enable **Publish anonymous top-10 leaderboard** in the examination settings, and set a valid **scheduled end**. Show `[smcq_leaderboard id="123"]` on a page. It is visible **only to logged-in students after the scheduled closing time**, and displays pseudonyms rather than user names or emails. Highest percentage wins; ties break by fewer wrong answers, then shorter completion time. Only each participant's best included attempt appears.
6. **Authenticated API:** `GET /wp-json/smcq/v1/analytics/me` returns only the logged-in student's aggregate progress, never answers, correct keys, or other students' data.

## Installation and usage

1. Back up WordPress and database; use staging first.
2. Go to **Plugins → Add New Plugin → Upload Plugin**. Upload `smart-mcq-manager-step7-v0.7.0.zip` and choose **Replace current with uploaded**. Do not delete or uninstall the previous plugin first.
3. Keep using `[smcq_exam id="123"]` to publish an exam and `[smcq_history]` for the student's result history.
4. Create a WordPress **My Progress** page containing `[smcq_dashboard]`. Log in as a test student and complete a new exam to see subject/topic data.
5. Go to **Smart MCQ → Exam Reports** (administrator only). Select an exam to see its results.
6. To publish an anonymous leaderboard, edit that exam, set **Scheduled end**, enable the leaderboard checkbox, update the exam, then create a page with `[smcq_leaderboard id="123"]`. Its contents remain hidden until the closing time.

### Shortcodes

```text
[smcq_exam id="123"]          Student examination interface
[smcq_history]                Personal result history and answer review
[smcq_dashboard]              Personal performance statistics and topic insights
[smcq_leaderboard id="123"]   Opt-in anonymous ranking after scheduled close
```

## Privacy, performance and caveats

- **Private by default.** Students only access their own personal dashboard via the authenticated REST route. The administrator report requires `manage_options`; leaderboard disclosure requires explicit exam opt-in and scheduled close. Leaderboards show anonymized participant identifiers. Although pseudonyms hide direct identifiers, small cohorts can still allow inferred identities: obtain consent/disclose the leaderboard before using it in sensitive classes.
- Personal aggregates use **up to the latest 200 completed attempts**, not all history. The dashboard shows the overall completed attempt count and indicates when aggregates are sampled. The trend uses at most 20 attempts. Topic grouping uses only frozen category names from Step 7 onward.
- Administrator aggregate uses the latest **500 submissions** for each selected exam. The report table lists 25 attempts per page; total count is not sampled. The examination selection dropdown displays up to 100 most recent exam posts.
- Leaderboards consider at most the latest **2,000 completed attempts** per exam, and rank the best attempt per participant **within that sample**. This is a pragmatic initial cap, not a complete ranking solution for large-volume examinations. Migrate to incremental indexed aggregates for very large institutions.
- Timeout completion is still lazy: if an exam closes while a student is offline, their active attempt is submitted when their next exam API call occurs. Thus an anonymous leaderboard may omit an expired but never revisited attempt. High-stakes final ranks need a server-side closing job and a verified results-release workflow.
- Pre-existing examination result visibility remains immediate after submission; Step 7 **does not** add delayed answer-key disclosure or professional proctoring.
- No new database table or migration required beyond the Step 6 `result_json` column. Existing question, exam, attempt and import data are retained.
- Disable or bypass full-page caching for **every** page containing `[smcq_exam]`, `[smcq_history]`, `[smcq_dashboard]` or `[smcq_leaderboard]`. Keep REST endpoints uncacheable.

## Development checks

```bash
find . -name '*.php' -print0 | xargs -0 -n1 php -l
php tests/test-importer-cli.php
php tests/test-exams-cli.php
php tests/test-player-cli.php
php tests/test-results-cli.php
php tests/test-analytics-cli.php
node --check assets/player.js
node --check assets/analytics.js
```

Integration checklist on staging: create one exam and several accounts; check category snapshots, result accuracy, nonce authentication, report permissions, timetable visibility, anonymous names, old-attempt history and failure on foreign REST access. PHP helper tests cover algorithms but cannot exercise the live WordPress database, authentication, plugin/theme compatibility or hosting limits.

### Next: Step 8

Advanced reporting/export and smart practice (wrong-answer revision, bookmarks), or paid-exam access depending on priorities.

License: GPL-2.0-or-later.