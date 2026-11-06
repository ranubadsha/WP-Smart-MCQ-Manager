# Smart MCQ Manager — Step 8 (v0.8.0)

WordPress MCQ question bank, CSV/XLSX importer, exam builder, authenticated examination player, secure grading, student history, **student analytics**, **administrator exam reports** and **opt-in anonymous leaderboards** and **private smart practice**.

> Development release: back up your database and test the update on staging. Automated helper tests are not a substitute for WordPress integration/concurrency testing.

## Requirements

- WordPress 6.0+, PHP 7.4+, compatible MySQL/MariaDB.
- PHP ZIP and SimpleXML for XLSX imports.
- HTTPS for login/session security.
- Exclude pages containing MCQ shortcodes and `/wp-json/smcq/v1/` from full-page or proxy caching. The analytics request uses same-origin credentials and a WordPress REST nonce.

## What Step 8 adds

1. **Smart revision hub:** `[smcq_practice]` lets signed-in students start wrong-answer, bookmarked-question, weak-topic and mixed revision sessions (1–20 questions each). There is no practice timer or negative marking; results are kept separate from official examination results.
2. **Practice access is opt-in per question:** An editor must open a published question and tick **Allow students to practice and bookmark this question**. Questions are *not* released automatically on upgrade or import. The Question Bank bulk-actions dropdown can enable/disable practice for up to 200 selected questions at a time. **Do not release questions whose answer keys must remain secret during current or future examinations.**
3. **Wrong-answer practice:** A student's most recent *answered* result for each question in the latest 200 completed exam attempts determines whether it remains in the wrong-answer pool. Subsequent correct answers clear it from that pool; skipped answers are not classified as wrong.
4. **Weak-topic practice:** Topics with at least 3 answered questions and below 60% accuracy are candidates. A set draws from currently published, explicitly released questions belonging to those topics. Earlier Step 5/6 attempts without frozen topic names contribute to the wrong-question pool but not topic-level suggestions.
5. **Bookmarks:** Students can bookmark released questions during practice and from their submitted examination answer reviews (including the exam-history page). Bookmarks belong to the individual WordPress student account. Disabling practice on a question prevents its use in future practice sessions.
6. **Progressive feedback:** The server returns the correct choice and explanation only **after** the student saves an answer to that question, or after finalizing the practice session. Practice answers are locked after submission, and concurrent requests use version checks.
7. **Saved sessions and progress:** Each student can have one active session per practice mode and resume it later. Finishing records correct/wrong/skipped counts and a recent ten-session accuracy trend. Practice results do not change competitive-exam scores or leaderboards.
8. **Private database records:** The two new tables are `wp_smcq_bookmarks` and `wp_smcq_practice_sessions` (with the WordPress installation's actual database prefix). Bookmarks and sessions support WordPress personal-data export/erasure.

### How to enable Step 8

1. Install `smart-mcq-manager-step8-v0.8.0.zip` by choosing **Replace current with uploaded**. Back up your database and test on staging first. Do not uninstall the previous version.
2. Go to **Smart MCQ → All Questions**. Edit an appropriate question, check **Allow students to practice and bookmark this question**, and update it. Repeat for a small safe set, or use the question list's bulk action.
3. Create a page titled **My Practice** with `[smcq_practice]`. Keep this page and `/wp-json/smcq/v1/` outside full-page caches.
4. Log in as a student, finish a normal exam with some incorrect answers, visit **My Practice**, and select **Retry Wrong Answers** or another revision mode. Answer a question to view its explanation; click **Finish Session** to see the score. Bookmarks can also be saved from a submitted exam's answer-review page when the question is released for practice.
5. Check that the student cannot see any correct keys before answering; confirm a second student cannot access another account's bookmarks or sessions. Test database migrations and privacy export/erase on staging.

**Caveats:** A live session freezes question content, so disabling practice after a student already starts that session does not retract its existing copy. For embargoed/high-stakes questions, do not enable practice until answer publication is allowed. The revision history scans at most 200 completed exam attempts and the bookmark pool at most 500 IDs; larger-scale personalized recommendations need background indexing. Practice mode may have no eligible questions until an administrator releases some. Recent practice progress is not included in the competitive leaderboard.

## What Step 7 adds

1. **Student dashboard:** `[smcq_dashboard]` shows the student's completed attempts, sampled average score, passes, correct/wrong/skipped counts, recent score trend, and subject/topic accuracy with low-sample warnings.
2. **Weak-topic suggestions:** Five lowest-accuracy topics **with at least three answered questions**. These are learning suggestions, not definitive diagnoses.
3. **Frozen topic metadata:** New attempts store the question's subject and topic names at attempt start. Questions edited later do not change historical topic analytics. **Old Step 5/6 attempts lack frozen subject/topic labels**, so those attempts contribute to scores but not category breakdowns.
4. **Administrator exam reports:** **Smart MCQ → Exam Reports** shows completed attempts for a selected exam, recent sampled average score, paginated student scores, accuracy, pass/fail and submit time. Access is restricted to WordPress administrators (`manage_options`).
5. **Optional anonymous leaderboard:** Enable **Publish anonymous top-10 leaderboard** in the examination settings, and set a valid **scheduled end**. Show `[smcq_leaderboard id="123"]` on a page. It is visible **only to logged-in students after the scheduled closing time**, and displays pseudonyms rather than user names or emails. Highest percentage wins; ties break by fewer wrong answers, then shorter completion time. Only each participant's best included attempt appears.
6. **Authenticated API:** `GET /wp-json/smcq/v1/analytics/me` returns only the logged-in student's aggregate progress, never answers, correct keys, or other students' data.

## Installation and usage

1. Back up WordPress and database; use staging first.
2. Go to **Plugins → Add New Plugin → Upload Plugin**. Upload `smart-mcq-manager-step8-v0.8.0.zip` and choose **Replace current with uploaded**. Do not delete or uninstall the previous plugin first.
3. Keep using `[smcq_exam id="123"]` to publish an exam and `[smcq_history]` for the student's result history.
4. Create a WordPress **My Progress** page containing `[smcq_dashboard]`. Log in as a test student and complete a new exam to see subject/topic data.
5. Go to **Smart MCQ → Exam Reports** (administrator only). Select an exam to see its results.
6. To publish an anonymous leaderboard, edit that exam, set **Scheduled end**, enable the leaderboard checkbox, update the exam, then create a page with `[smcq_leaderboard id="123"]`. Its contents remain hidden until the closing time.

### Shortcodes

```text
[smcq_exam id="123"]          Student examination interface
[smcq_history]                Personal result history and answer review
[smcq_dashboard]              Personal performance statistics and topic insights
[smcq_practice]               Private practice sessions and revision progress
[smcq_leaderboard id="123"]   Opt-in anonymous ranking after scheduled close
```

## Privacy, performance and caveats

- **Private by default.** Students only access their own personal dashboard via the authenticated REST route. The administrator report requires `manage_options`; leaderboard disclosure requires explicit exam opt-in and scheduled close. Leaderboards show anonymized participant identifiers. Although pseudonyms hide direct identifiers, small cohorts can still allow inferred identities: obtain consent/disclose the leaderboard before using it in sensitive classes.
- Personal aggregates use **up to the latest 200 completed attempts**, not all history. The dashboard shows the overall completed attempt count and indicates when aggregates are sampled. The trend uses at most 20 attempts. Topic grouping uses only frozen category names from Step 7 onward.
- Administrator aggregate uses the latest **500 submissions** for each selected exam. The report table lists 25 attempts per page; total count is not sampled. The examination selection dropdown displays up to 100 most recent exam posts.
- Leaderboards consider at most the latest **2,000 completed attempts** per exam, and rank the best attempt per participant **within that sample**. This is a pragmatic initial cap, not a complete ranking solution for large-volume examinations. Migrate to incremental indexed aggregates for very large institutions.
- Timeout completion is still lazy: if an exam closes while a student is offline, their active attempt is submitted when their next exam API call occurs. Thus an anonymous leaderboard may omit an expired but never revisited attempt. High-stakes final ranks need a server-side closing job and a verified results-release workflow.
- Pre-existing examination result visibility remains immediate after submission; Step 7 **does not** add delayed answer-key disclosure or professional proctoring.
- Step 8 adds two personal-practice tables through `dbDelta()` with schema checking on activation/update. Existing question, exam, attempt and import data are retained.
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
node --check assets/practice.js
php tests/test-practice-cli.php
```

Integration checklist on staging: create one exam and several accounts; check category snapshots, result accuracy, nonce authentication, report permissions, timetable visibility, anonymous names, old-attempt history and failure on foreign REST access. PHP helper tests cover algorithms but cannot exercise the live WordPress database, authentication, plugin/theme compatibility or hosting limits.

### Next: Step 9

Detailed reporting and exports, or membership/premium exam access depending on priorities.

License: GPL-2.0-or-later.