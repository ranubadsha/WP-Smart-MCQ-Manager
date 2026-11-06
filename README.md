# Smart MCQ Manager — Step 5 (v0.5.0)

A WordPress MCQ question bank with CSV/XLSX import, a private exam builder, and **authenticated student exam delivery**. This is a development release: **automatic grading and student results are not yet implemented**.

## Requirements

- WordPress 6.0+, PHP 7.4+, MySQL/MariaDB (WordPress supported versions).
- For XLSX import only: PHP ZIP and SimpleXML extensions.
- Logged-in WordPress users (including Subscriber role) can sit published exams.
- A staging-site backup before upgrading, and a page that is **excluded from full-page caching** when it contains personalized examination content.
- HTTPS is strongly recommended for authentication and exam data.

## Student exam: Step 5

1. Visit **Smart MCQ → Exams** in WordPress admin. Create or edit an exam, fill in the exam instructions and settings, assign complete questions, then **Publish** it.
2. Find the shortcode in the **Student Exam Page** panel (for example `[smcq_exam id="123"]`).
3. Create a normal WordPress page and paste the shortcode into a Shortcode block. Publish that page and share its URL.
4. The student logs in to WordPress and opens the page. Starting an attempt saves a **private server-side snapshot** of the questions, scoring policy, shuffled order, and answer key.
5. Students see only question text and choice text, never the correct answer or explanation. The numbered navigator allows jumping between questions; answer choices are saved immediately using the authenticated WordPress REST API.
6. Countdown time is controlled by the **server**, using the shorter of the exam duration and scheduled end. The browser shows a countdown, but changing browser time cannot extend a server deadline.
7. Submit records the final answers. After timeout, already-saved answers are finalized at the first subsequent server request, even if the browser was closed. **No score is calculated yet.**
8. A student can reopen the same exam page to resume an active attempt. Only one active attempt is allowed per user per exam. New attempts respect the configured attempt limit (0 = unlimited).

### New shortcode

`[smcq_exam id="123"]` — renders an authenticated, responsive MCQ exam UI for a **published** exam. The exam itself is stored in a private WordPress post type. Explanations and answers remain private; do not put answer keys into a public WordPress page.

### Data model and privacy

Activation / upgrading creates a dedicated `{$wpdb->prefix}smcq_attempts` table with a **unique active attempt key** so simultaneous tabs cannot create two active attempts for one user/exam. It stores the owner user ID, exam ID, answer JSON, private exam snapshot, timestamps, status, completion reason, and a version number to prevent stale saves overwriting newer answers. Existing questions and exam metadata from Steps 1–4 are not modified.

A student's REST endpoints require WordPress login and the REST nonce. Every attempt read/write also checks the logged-in **owner**. Answer submissions are strictly validated against the frozen question set, and saved via a version-checked SQL update. Server-side deadline checks reject late writes. Exam question posts and correct answers are still excluded from public REST routes. A privacy exporter and eraser are registered for recorded attempts.

**Current limitations:** No grading, score reports, answer review, public leaderboard, paid exam controls, anti-cheating surveillance, or offline queued saving yet. Students must have a connection to save each answer. A failed save shows an error; retry the answer before submitting. Timeout finalization is **lazy** (on the next API request); no background cron job is required. Page/cache plugins should exclude exam pages and `/wp-json/smcq/v1/` endpoints. The admin exam builder still limits question sets to 200 and the import tool still caps uploads at 500 rows. Test real concurrent users and caching on staging before production use.

## Earlier features retained

- Private question bank with A/B/C/D, correct answer, rich explanation, subject/topic/exam source, tags, difficulty, language, years, references, and marks.
- Bulk CSV/XLSX importer: column mapping, five-row preview, validation, duplicate checks, 40-row batches; 500-row / 3 MiB per-file cap. XLSX first-worksheet formulas are rejected.
- Exam builder: manual question selection and filtered generator by subject/topic/source/difficulty; 200-question maximum. Duration (1–600 minutes), schedule, attempt limits, positive/negative marks, passing percentage, question shuffle, option shuffle.

## Upgrade from Step 4

Back up the site database. In **Plugins → Add New → Upload Plugin**, choose `smart-mcq-manager-step5-v0.5.0.zip` and **Replace current with uploaded**. The installed directory should remain `smart-mcq-manager`. Do not uninstall the prior plugin; this is an in-place update. Ensure the DB user has permission to create tables on activation/upgrade. Verify the table was created, publish a sample exam, and test as a Subscriber on staging.

## Development checks

```bash
find . -name '*.php' -print0 | xargs -0 -n1 php -l
php tests/test-importer-cli.php
php tests/test-exams-cli.php
php tests/test-player-cli.php
node --check assets/player.js
```

`test-player-cli.php` covers timezone conversion, deadline precedence, snapshot answer-key redaction, shuffled choice keys, and invalid answer rejection. The GitHub Actions workflow runs checks across PHP 7.4 / 8.2 / 8.3. These are **not** a replacement for WordPress integration/browser tests.

## Step 6 (planned)

Add automatic grading, negative marking, results, answer review, and student exam history based on the immutable Step 5 attempt snapshots.

License: GPL-2.0-or-later.