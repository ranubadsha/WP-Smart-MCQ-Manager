=== Smart MCQ Manager ===
Contributors: smart-mcq
Requires at least: 6.0
Requires PHP: 7.4
Stable tag: 0.7.0
License: GPLv2 or later

Step 7: WordPress MCQ question bank, import, exam builder/player, grading, history, and progress analytics.

== Features ==
* Private MCQ question bank with answer explanations, subjects, topics, sources, years and difficulty.
* CSV/XLSX importer, column mapping, preview, validation and duplicate detection.
* Exam creation, manual/random question assignment, marks, penalty rules and scheduled sessions.
* Logged-in student player with timed attempts, answer autosave, submission, grading and result review.
* Student dashboard with progress trend, subject/topic accuracy and low-sample weak-topic suggestions.
* Administrator exam report table with per-student scores and passing status.
* Optional anonymous top-10 leaderboard visible after scheduled exam end, for logged-in students only.
* REST nonce and per-user data isolation, privacy export and erasure support.

== Shortcodes ==
[smcq_exam id="123"] — student exam.
[smcq_history] — private attempt history and grading details.
[smcq_dashboard] — private performance overview.
[smcq_leaderboard id="123"] — anonymous rankings after closing time (requires opt-in).

== Installation ==
1. Back up your existing site and use a staging site first.
2. WordPress Plugins > Add New > Upload Plugin: smart-mcq-manager-step7-v0.7.0.zip.
3. Choose Replace current with uploaded. Do not uninstall the old plugin first.
4. Create a WordPress page with [smcq_dashboard] and complete a new exam as a test student.
5. Open Smart MCQ > Exam Reports as an administrator.
6. For leaderboard, set the exam's scheduled end date and check the anonymous leaderboard option before adding its shortcode.
7. Exclude all MCQ shortcode pages and the smcq REST endpoints from page caching.

== Upgrade Notes ==
= 0.7.0 =
* Adds personal dashboard, topic insights, admin reports, optional anonymous leaderboard.
* New attempts freeze original subject/topic labels. Old attempts cannot supply historical topic labels.
* Keeps Step 6 result table and plugin content unchanged; no new schema version.
* Statistics are intentionally sampled (200 personal, 500 administrator, 2000 leaderboard attempts).

== Limitations ==
Development release. Full WordPress staging testing is required. Leaderboards may omit offline attempts awaiting timeout finalization; do not use for high-stakes final results without a reliable close-out task. Personalized results are still visible immediately after submission. See README.md for details.