=== Smart MCQ Manager ===
Contributors: smart-mcq
Requires at least: 6.0
Requires PHP: 7.4
Stable tag: 0.5.0
License: GPLv2 or later

Step 5: Private MCQ question bank, bulk question import, exam builder, and secure logged-in student exam interface.

== Description ==
Features:
* Private question editor with four options, answer key, explanation and classification.
* Bulk CSV/XLSX import with mapping, validation, preview, and duplicate checks.
* Exam creation with manual/filtered question assignment and exam scheduling.
* Logged-in student exam interface via [smcq_exam id="123"].
* One active attempt per user/exam, allowed-attempt caps and server-side deadlines.
* AJAX-style REST answer saving, mobile navigation, and automatic timeout finalization on next server request.
* Immutable attempt snapshots hide correct answers from the student.
* WordPress privacy data exporter / eraser for attempts.

Not included yet: score calculation, negative-mark grading, answer review, reports, and student history. They are planned for Step 6.

== Installation ==
1. Back up your database, and use a staging site for initial testing.
2. Plugins -> Add New -> Upload Plugin -> select ZIP; replace current plugin when upgrading.
3. Create and publish an exam under Smart MCQ -> Exams.
4. Put [smcq_exam id="123"] (replace 123 with your exam post ID) on a WordPress page.
5. Log in as a student and start the examination.

== Important ==
* Students must log in. HTTPS recommended; exclude exam pages and /wp-json/smcq/v1/ from page caches.
* Saving requires an internet connection. If a save fails, retry it before submitting.
* Timeout finalization occurs on the next API call, not via background cron.
* WordPress 6.0+ and PHP 7.4+. Excel import needs ZIP/SimpleXML PHP extensions.
* Do not uninstall the previous version during upgrades; retain existing questions and exams.