=== Smart MCQ Manager ===
Contributors: smart-mcq
Requires at least: 6.0
Requires PHP: 7.4
Stable tag: 0.6.0
License: GPLv2 or later

Step 6: Private question bank, import tools, exam builder, authenticated exam interface, automatic scoring, answer explanations, and student history.

== Features ==
* Private MCQ question bank (A-D) with subjects, topics, exam sources, language, difficulty, references and explanations.
* CSV/XLSX bulk importer with mapping, preview, validation and duplicate checks.
* Exam builder with manual/random question selection, exam duration, attempt limits, pass percentage, per-question or fixed marks, penalties and scheduled access.
* Logged-in student exam player with autosaved answers, shuffled questions/options, server timer, secure submission and resume.
* Automatic grading and negative marking; correct, wrong and skipped counts; score percentage, attempted accuracy and pass/fail.
* Detailed answer review from frozen attempt snapshots; personal results history.
* Security via WordPress REST nonce, owner-scoped attempts, answer-key protection, guarded updates and privacy exporter/eraser.

== Shortcodes ==
[smcq_exam id="123"] — student-facing examination for exam ID 123.
[smcq_history] — student-only personal history and post-submission answer reviews.

== Installation ==
1. Back up WordPress and try the update on a staging site.
2. WordPress Admin > Plugins > Add New > Upload Plugin. Select smart-mcq-manager-step6-v0.6.0.zip.
3. Choose Replace current with uploaded when upgrading. Do not uninstall the old version.
4. Create an exam in Smart MCQ > Exams, publish it, then add [smcq_exam id="123"] to a WordPress page.
5. Log in as a Subscriber and complete a test attempt. Create a second page containing [smcq_history].
6. Exclude personalized exam/history pages and plugin REST endpoints from full-page cache.

== Upgrade Notes ==
= 0.6.0 =
* Adds result_json to the existing attempt table using dbDelta; old saved attempts can be graded on demand.
* Safely computes grades against frozen attempts and preserves existing question and exam metadata.
* Only a completed attempt's owner can read its score, correct answers and review.

== Limitations ==
This is a development release, not a live-proctored exam product. Requires WordPress integration testing. No delayed results, teacher dashboard, public leaderboard, certificates, subscription controls, or offline answer queue yet. Completed results are immediately visible to the owner. If upgrading from Step 5, old attempts did not preserve explanation text in their snapshots.