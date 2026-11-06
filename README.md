# Smart MCQ Manager — Step 10 (v1.0.0)

A WordPress MCQ plugin with a private question bank, CSV/XLSX import, exam builder, logged-in exam delivery, frozen server-side grading, dashboards, revision practice, and exports. **Step 10 introduces optional per-exam activity monitoring, single-tab editing controls, a private security event report, and privacy controls.**

> Development release. **Use a staging site and back up the WordPress database before upgrading.** CLI tests do not replace WordPress integration, security penetration testing, or load tests.

## Requirements

- WordPress 6.0+, PHP 7.4+ and HTTPS.
- Existing imports require the PHP ZIP and SimpleXML extensions for Excel; Excel exports require ZIP.
- Exclude exam pages and authenticated REST endpoints (`/wp-json/smcq/v1/`) from CDN/page caches.
- Use a working WordPress scheduler. WP-Cron runs opportunistically; site owners requiring reliable 30-day log cleanup should configure real server cron.

## Step 10: Exam Security and Anti-Cheating

### Enable for individual exams

1. Open **Smart MCQ → Exams → Edit Exam → Examination Settings**.
2. Under **Exam Security (optional)**, choose either or both of:
   - **Log activity:** records browser tab hidden/visible, exits from fullscreen entered through the exam page, session conflicts/takeovers, and starts/submissions/timeouts. Disabled by default. Logs have no IP address, raw browser token or user-agent.
   - **Single-tab editing:** only one editing session is allowed per student attempt. Other tabs/devices receive a conflict message and an explicit **Continue this attempt here** button; takeover revokes the previous session's ability to save. Disabled by default. If the previous tab is abandoned, a replacement session can reclaim the lease after **180 seconds**; an active tab sends a lightweight heartbeat every 45 seconds. Tokens are generated with browser cryptographic randomness and stored only in the tab's session storage; the database retains a salted hash. Single-tab controls are best-effort—not identity verification or robust anti-cheating.
3. Save the exam. **New policy settings apply to active attempts when they next contact the server**; notify students before enabling mid-exam.
4. Test the exam in a normal browser, second tab, private window, and phone. Some browsers copy sessionStorage into duplicated tabs; the control cannot guarantee one physical device.

### Security events and private exports

Open **Smart MCQ → Exam Security** as an administrator (`manage_options`). Filter by exam ID, WordPress user ID, and event type. Reports display at most 25 events/page and you may export up to 5,000 events as UTF-8 CSV. Both report and export are administrator-only; export requires a nonce. No security log includes answer keys or question text.

**Privacy and fairness:** Visibility events are indicators for review, **not proof of cheating**. They never automatically penalize, disqualify, or alter results. Users should be informed before participating in monitored exams. WordPress privacy export/erase includes security events and leases. Automatic cleanup is scheduled daily for events older than 30 days; delays are possible when WP-Cron does not execute. Events are capped at 200 per attempt and the same event type is throttled to once every 5 seconds. Session leases are also cleared on successful submission and during stale-data cleanup.

### Existing protections retained

- Exam timer and submission deadlines are server-enforced.
- Only the logged-in attempt owner may fetch or change their attempt.
- Correct-answer keys and explanations are not sent to the frontend before a valid submission.
- Optimistic answer-version checks prevent overlapping writes and the `active_key` UNIQUE constraint prevents duplicate concurrent attempts for a single exam/student.
- Question and option randomization are already available in exam settings. All questions, marks and categories are frozen at attempt creation.

### Important limits

Browser activity signals are imperfect: tab changes may be triggered by accessibility software, device notifications, or connection events and can be suppressed by the browser. Monitoring cannot detect another device or prove that a student cheated. Single-tab restrictions are not a substitute for strong user authentication, offline proctoring, or formal examination supervision. Timeouts are still lazily finalized when the server next accesses the attempt. This is **not** yet a verified high-stakes examination platform.

## Installation

1. Back up your WordPress database and existing plugin. Test in staging.
2. In WordPress **Plugins → Add New Plugin → Upload Plugin**, upload `smart-mcq-manager-step10-v1.0.0.zip` and select **Replace current with uploaded**. Do **not** uninstall or delete data.
3. Edit one test exam and enable monitoring + single-tab editing. Publish it, create a page with `[smcq_exam id="123"]` (use your exam ID), and complete an attempt as a student.
4. Verify that opening a second tab triggers a friendly conflict and **Continue this attempt here**. Confirm the first tab can no longer save once another session takes over. Test countdown, answer autosave, timeout and submission.
5. Open **Smart MCQ → Exam Security** as an administrator. Check the event timeline and CSV export. Confirm anonymous visitors and students cannot access admin reports.
6. Confirm the student sees the monitoring notice before starting. If using a managed cache, explicitly exclude the exam and student pages.

### Student shortcodes

```text
[smcq_exam id="123"]          Authenticated MCQ examination
[smcq_history]                Personal attempt history and answer review
[smcq_dashboard]              Personal analytics and result exports
[smcq_my_report]              Personal reports
[smcq_practice]               Wrong-answer, bookmark and weak-topic revision
[smcq_leaderboard id="123"]   Optional anonymous leaderboard after closing
```

## Automated tests

```bash
find . -name '*.php' -print0 | xargs -0 -n1 php -l
for test in tests/test-*-cli.php; do php "$test"; done
for js in assets/*.js; do node --check "$js"; done
```

The new Step 10 tests exercise allowed event names, strict client identifiers, lease decisions, expiration and takeover. The existing import, exam builder, player, grading, analytics, practice and reporting suites still pass. Full live REST/database/JS/browser integration testing is not simulated by these CLI tests.

## Upgrade/data notes

- Version `1.0.0` keeps the existing WordPress plugin name, post types, answers, attempts, grading and practice data.
- Creates two new WordPress-prefixed tables: `smcq_security_events`, `smcq_security_leases` (no destructive migration).
- Adds two exam settings: `_smcq_exam_monitor_activity`, `_smcq_exam_single_tab` (both default off).
- A daily WordPress cleanup hook removes old security events. The plugin does not uninstall older student data on upgrade.

GPL-2.0-or-later.