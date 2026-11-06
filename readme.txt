=== Smart MCQ Manager ===
Contributors: smart-mcq
Requires at least: 6.0
Requires PHP: 7.4
Stable tag: 1.3.0
License: GPLv2 or later

Private WordPress MCQ question bank, automatic examinations and grading, analytics, revision, paid exams, question types, optional question reporting and Gutenberg blocks.

== Description ==

v1.3.0 builds on v1.2.0. It extends question presentation and answer formats (single, multiple, True/False, passage, image, audio, plain-text math), adds structured JSON import, question issue reports, dynamic blocks, and printable pass certificates. It preserves prior exams, questions and data.

This release does not include all original roadmap features, and requires staging validation before production deployment. See README.md for precise scope and limitations.

== Installation ==

1. Back up WordPress database and plugin directory.
2. Upgrade on staging using Plugins > Add New > Upload Plugin.
3. Test question formats, exam grading, privacy and payment access before production.

== Changelog ==

= 1.3.0 =
* Add more question formats with frozen answer snapshots and exact-set grading.
* Improve student exam and practice frontends for checkbox answers and context media.
* Add structured JSON question imports to existing import wizard.
* Add owned-attempt student question error reports and admin-only issue review.
* Add dynamic Gutenberg Exam/Dashboard/Practice/History blocks.
* Add private, printable pass certificates.
* Add regression tests covering new scoring and imports.

= 1.2.0 =
* Add indexes, System Status and schema maintenance.