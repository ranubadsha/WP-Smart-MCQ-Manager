=== Smart MCQ Manager ===
Contributors: smart-mcq
Tags: mcq, question-bank, quiz, exam, education
Requires at least: 6.0
Requires PHP: 7.4
Stable tag: 0.2.0
License: GPLv2 or later

Step 2: Private WordPress MCQ question bank with advanced question classifications and filters.

== Description ==

Features:
* Create, edit and delete private MCQ questions.
* Four text answer options (A, B, C, D), one correct answer, rich-text explanation.
* Organize questions with subjects, topics, exam sources and question tags.
* Store difficulty, language, exam year, book/reference, reference URL and per-question marks.
* Filter admin questions by subject, topic, exam source, difficulty, language and year.
* Show question ID, completeness, answer, difficulty, language and exam year columns.
* Retain Step 1 answer data and taxonomy associations when upgrading from v0.1.0.
* WordPress capability, nonce, sanitization and escaping checks.

== Installation ==

1. Back up your WordPress site before updating.
2. Plugins > Add New Plugin > Upload Plugin: upload the release ZIP.
3. If an older version is installed, choose Replace current with uploaded when prompted.
4. Activate Smart MCQ Manager.
5. Smart MCQ > Add MCQ Question: fill title, options, correct answer, explanation and metadata.
6. Use Smart MCQ's taxonomy menus to create subjects, topics, exam sources and question tags.

== Upgrade Notice ==

= 0.2.0 =
Existing question IDs and the _smcq_options, _smcq_correct and _smcq_explanation keys remain unchanged. No automatic deletion or destructive migration.

== Important ==

This is an admin-only question manager, not a student-facing exam system. Do not use it to administer online exams yet. Incomplete questions can still be saved; the Ready column indicates whether required answer fields exist. No payment system or public answer endpoint is included.