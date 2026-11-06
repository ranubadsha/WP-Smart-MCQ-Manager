=== Smart MCQ Manager ===
Contributors: smart-mcq
Requires at least: 6.0
Requires PHP: 7.4
Stable tag: 1.2.0
License: GPLv2 or later

WordPress MCQ exams, private question bank, answer grading, analytics, practice, reports, WooCommerce premium access and server-admin health tools.

== Description ==

Step 12 adds validated database index migrations and a private System Status screen. Features from Steps 1-11 are preserved.

* Private MCQ question bank with categories and exam metadata.
* CSV and XLSX importer, exam builder, online player, timer and automatic results.
* Student progress analytics, practice and bookmarks, private reports and optional monitoring.
* Optional WooCommerce-based paid-exam access.
* Admin-only database diagnostics and nonce-protected schema repair.
* WordPress CLI diagnostics: wp smcq doctor --format=json (requires WP-CLI).

== Installation ==

1. Back up WordPress database and plugin files; test on staging.
2. Upload the ZIP and choose Replace current with uploaded.
3. Visit Smart MCQ > System Status; inspect checks for missing indexes/tables.
4. Test student attempts and permissions on staging before production.

== Changelog ==

= 1.2.0 =
* Add composite indexes for attempt and practice lookups.
* Verify index availability before recording schema migration success.
* Add System Status, schema repair action and WP-CLI doctor.
* Add isolated regression tests for health diagnostics.

= 1.1.0 =
* WooCommerce premium exam access and subscriptions compatibility.

== Important ==

Live WordPress integration, real payment, and scale/load tests must be completed on a staging environment before launch. Exam security monitoring cannot guarantee the prevention of cheating.