# Smart MCQ Manager — Step 9 (v0.9.0)

A WordPress MCQ question bank, import workflow, exam builder, authenticated student exam player, grading, dashboards, analytics, and personal practice. **Step 9 adds private, filterable result reports with CSV, Excel, and print-to-PDF exports.**

> Development release. Back up the database and test on a staging installation before upgrading. CLI helper tests do not cover WordPress integration or load behavior.

## Requirements

- WordPress 6.0+ and PHP 7.4+.
- ZIP and SimpleXML PHP extensions for existing Excel **imports**; PHP ZIP extension for Step 9 Excel **exports**. CSV does not require ZIP.
- HTTPS. Pages containing student shortcodes and `/wp-json/smcq/v1/` must not be cached for other users.

## Step 9: Advanced reports and exports

**Administrator report:** Open **Smart MCQ → Exam Reports**. Filter by examination, WordPress student user ID, submitted date range (UTC), and pass/fail status. View completed attempts, a recent 500-attempt sample of average score, pass rate, highest/lowest percentage, and subject/topic accuracy based on frozen question snapshots. The result table is paginated (25 rows/page).

**Exports:** Click **Download CSV**, **Download Excel (.xlsx)**, or **Print / Save as PDF**. CSV includes UTF-8 BOM for Excel compatibility and neutralizes spreadsheet-formula injection. XLSX uses Unicode-safe inline strings and styled columns/headers (requires `ZipArchive`); neither format contains answers or question keys. Print opens a private HTML report in a new tab; use the browser's **Print → Save as PDF** option. This is not a server-generated PDF download.

**Student self-service:** On `[smcq_dashboard]`, logged-in students can export *only their own* submitted examination results, in the same formats. Alternatively place `[smcq_my_report]` on a page for a dedicated download panel. Students cannot select another student's ID. Do not cache these pages or their export responses.

**Security:** Nonce-validated `admin-post.php` links, `manage_options` permission for administrator exports, logged-in user ownership for personal exports, server-side database scoping and no answer-key export. Report output is non-cacheable. Users can only access their own data. Names/email addresses appear in administrator exports; handle the files as private educational records.

**Limits and caveats:** Exports include at most **5,000** matching submitted attempts (latest first). When filtering by pass/fail, the initial scan is capped at 25,000 submitted attempts, so exceptionally large, sparse datasets can produce partial results. Summary/category metrics sample at most **500** matching attempts, while the total counter is **before the pass/fail filter**. Prior-to-Step-7 attempts lack frozen category labels, so they cannot contribute to accurate historical subject/topic analysis. WordPress uses lazy attempt finalization, so expired offline attempts may not appear until finalized; this is not yet an audited high-stakes examination register. All dates shown in report filters are UTC. Exporting 5,000 rows is intentionally capped to reduce memory usage.

## Installation

1. Back up your WordPress database. Use staging first.
2. In **Plugins → Add New → Upload Plugin**, upload `smart-mcq-manager-step9-v0.9.0.zip` and choose **Replace current with uploaded**. **Do not uninstall** the previous version.
3. Open **Smart MCQ → Exam Reports** in WordPress administration; choose an exam, dates and optional student/pass status, then click Apply filters. Test CSV, Excel, and Print/Save as PDF.
4. Open your dashboard page with `[smcq_dashboard]` as a logged-in student. Confirm self-service exports contain **only that student's attempts**. Optionally add `[smcq_my_report]` to a separate page.
5. Test both print output and UTF-8 names/exam titles with Bangla samples. Validate that another student or a logged-out visitor cannot read private reports.

### Supported shortcodes

```text
[smcq_exam id="123"]          Student exam with countdown timer
[smcq_history]                Student attempt history and answer review
[smcq_dashboard]              Personal performance + exports
[smcq_my_report]              Dedicated student self-service report links (new)
[smcq_practice]               Personal smart practice and bookmarks
[smcq_leaderboard id="123"]   Anonymous leaderboard after closing (opt-in)
```

## Previous releases retained

- **Step 1/2:** Private question bank with answers, explanations, subjects, topics, exam sources, difficulty, marking configuration.
- **Step 3:** CSV/XLSX questions import with mapping, validation, previews and duplicate checks.
- **Step 4:** Administrative exam creator, manual/automatic selection, rules and scheduling.
- **Step 5:** Logged-in exam frontend, answer autosave, timeouts and saved attempts.
- **Step 6:** Frozen server-side grading, detailed answer review, results and history.
- **Step 7:** Student analytics, administrator overview, optional anonymous leaderboard.
- **Step 8:** Explicitly released practice questions, wrong-answer/weak-topic/bookmark practice and progress.

No question, examination, attempt, or practice data migrations are required by Step 9; existing schemas and plugin IDs are unchanged.

## Automated checks

```bash
find . -name '*.php' -print0 | xargs -0 -n1 php -l
for test in tests/test-*-cli.php; do php "$test"; done
for js in assets/*.js; do node --check "$js"; done
```

The Step 9 report tests cover filter validation, user isolation, formula-injection safety, snapshot-grade export rows, UTF-8 XML escaping, and XLSX sheet generation (with an in-memory ZIP substitute when the extension is absent). They do not simulate actual WordPress SQL/REST integrations. Test real XLSX generation on the target host with PHP ZIP enabled.

GPL-2.0-or-later.