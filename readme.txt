=== Smart MCQ Manager ===
Contributors: smart-mcq
Requires at least: 6.0
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later

WordPress MCQ examinations, question imports, marking, results, analytics, practice and export. Step 10 adds opt-in security activity logging, session leases and admin reports.

== Description ==

Features:
- Private question bank (Bangla and English), subjects, topics, difficulty
- Import questions from CSV/XLSX with validation
- Exam builder, schedules, positive/negative marks, attempt limits
- Authenticated online exam with server timer, autosave and frozen grading
- Student results, answer reviews, dashboard, revision practice and reports
- Optional anonymous post-exam leaderboard
- Private CSV/XLSX exports and print/save as PDF
- Step 10: per-exam optional tab visibility / fullscreen-exit activity events
- Step 10: optional single-tab editing session and explicit takeover
- Step 10: administrator event logs, filters, CSV export, privacy exporter/eraser

== Installation ==

1. Back up the database. Upload the v1.0.0 ZIP via Plugins > Add New > Upload Plugin.
2. Select Replace current with uploaded and do not uninstall the prior version.
3. Edit an exam under Smart MCQ > Exams. Enable new Exam Security settings if desired.
4. Add [smcq_exam id="123"] to a page, using your exam's actual ID.
5. Open Smart MCQ > Exam Security for an administrator-only audit log.

== Privacy and limitations ==

Monitoring and single-tab editing are OFF by default. Browsers may fail to report some events; they are not proof of misconduct. No automatic penalties. Activity logs omit IP addresses, user agent and raw session token. 30-day cleanup relies on WordPress scheduling. Test on staging before production use.

== Changelog ==

= 1.0.0 =
- Step 10: Optional exam security monitoring and tab/device editing lease.
- Separate privacy-conscious event records, admin event report and CSV.
- Session takeover UX, activity monitoring notice, fullscreen shortcut.
- Security test suite; preserves prior features and data.

= 0.9.0 =
- Step 9 reports, private result exports, Excel and print-to-PDF.