=== Smart MCQ Manager ===
Contributors: smart-mcq
Requires at least: 6.0
Requires PHP: 7.4
Stable tag: 0.9.0
License: GPLv2 or later

Step 9: WordPress MCQ question bank, import, exam builder/player, grading, analytics, practice, and private result reporting.

== Features ==
* Private MCQ question bank with answer explanations, subjects, topics, sources, years and difficulty.
* CSV/XLSX importer, column mapping, preview, validation and duplicate detection.
* Exam creation, manual/random question assignment, marks, penalty rules and scheduled sessions.
* Logged-in student player with timed attempts, answer autosave, submission, grading and result review.
* Student dashboard with progress trend, subject/topic accuracy and low-sample weak-topic suggestions.
* Administrator exam report table with per-student scores and passing status.
* Optional anonymous top-10 leaderboard visible after scheduled exam end, for logged-in students only.
* Four smart revision modes: wrong answers, bookmarked, weak topics, and mixed.
* Explicit admin opt-in per question for safe practice release; progressive answer feedback.
* Private session resume, bookmark saving and practice progress history.
* REST nonce and per-user data isolation, privacy export and erasure support.

* Step 9: Admin exam reports with date/student/pass filters, sampled analytics, CSV/Excel export and Print/Save as PDF.
* Student self-service CSV/Excel/print exports limited to the signed-in user.
* XLSX export requires PHP ZIP; printable HTML works with browser Save as PDF.

== Shortcodes ==
[smcq_exam id="123"] — student exam.
[smcq_history] — private attempt history and grading details.
[smcq_dashboard] — private performance overview and report download links.
[smcq_my_report] — private student exports.
[smcq_practice] — private bookmark/revision practice.
[smcq_leaderboard id="123"] — anonymous rankings after closing time (requires opt-in).

== Installation ==
1. Back up your existing site and use a staging site first.
2. WordPress Plugins > Add New > Upload Plugin: smart-mcq-manager-step9-v0.9.0.zip.
3. Choose Replace current with uploaded. Do not uninstall the old plugin first.
4. Enable practice availability on safe published questions (single edit or question-list bulk action).
5. Create a WordPress page with [smcq_practice] and complete a new exam as a test student.
6. Open Smart MCQ > Exam Reports as an administrator.
7. For leaderboard, set the exam's scheduled end date and check the anonymous leaderboard option before adding its shortcode.
8. Exclude all MCQ shortcode pages and the smcq REST endpoints from page caching.

== Upgrade Notes ==
= 0.9.0 =
* Adds private, filtered administrator reporting and 5,000-row export cap.
* Adds safe CSV, Unicode XLSX (PHP ZIP required), and browser print-to-PDF.
* Adds student-scoped self-service results downloads; retains all previous data.

= 0.8.0 =
* Adds per-user bookmark and practice-session database tables.
* Adds opt-in question release, answer-by-answer practice feedback and personal revision history.
* Retains all Step 7 data and functionality.

= 0.7.0 =
* Adds personal dashboard, topic insights, admin reports, optional anonymous leaderboard.
* New attempts freeze original subject/topic labels. Old attempts cannot supply historical topic labels.
* Keeps Step 6 result table and plugin content unchanged; no new schema version.
* Statistics are intentionally sampled (200 personal, 500 administrator, 2000 leaderboard attempts).

== Limitations ==
Private practice requires a logged-in user and released questions. It is not a substitute for published examinations. Practice explanation access begins after an answer or finalization; administrator opt-in should be used only for non-embargoed questions.

Development release. Full WordPress staging testing is required. Leaderboards may omit offline attempts awaiting timeout finalization; do not use for high-stakes final results without a reliable close-out task. Personalized results are still visible immediately after submission. See README.md for details.