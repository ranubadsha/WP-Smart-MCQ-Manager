# Smart MCQ Manager — v1.3.0 (Step 13 and partial follow-on work)

This is a **development release**, not a declaration that Steps 13–17 or the original full feature specification are complete. It upgrades v1.2.0 without resetting questions, exams, saved attempts, subscriptions or reports.

## Step 13: additional question formats (implemented)

Seven editor presentation/answer types are now available in **Smart MCQ → Questions → Add New**:

| Question type | Options and grading | Extra field |
| --- | --- | --- |
| Single correct answer | Four options, one correct (legacy-compatible default) | — |
| Multiple correct answers | Four options; select 2–4 correct; exact-set matching only | Answer checkboxes |
| True/False | A/B only; one correct | Put True / False in A and B |
| Passage-based | Four options, one correct | Reading passage text |
| Image-based | Four options, one correct | WordPress image attachment ID |
| Audio-based | Four options, one correct | WordPress audio attachment ID |
| Mathematics / formula | Four options, one correct | Unicode/plain-text math only |

The exam player and practice interface support these types, including checkbox answer selection for multiple-answer MCQs and contextual media. Results, negative marking, answer reviews, and subject/topic analytics have been adjusted for multiple-answer responses.

**Important limitations:** Mathematics content is Unicode/plain text, not rendered LaTeX; question media must already exist in the WordPress Media Library and media files may be publicly accessible. Multiple-correct questions use **all-or-nothing grading**, not partial credit. Dedicated matching questions, interactive equation editors, and a shared multi-question passage component are not yet included.

## Step 14: selected question-management improvements (partially implemented)

- **Structured JSON import**: The Bulk Import page accepts `.json` using the same preview, mapping, duplicate checks and validation as CSV/XLSX. Use a top-level array of objects or `{ "questions": [ ... ] }`, with keys `question`, `option_a`, `option_b`, `option_c`, `option_d`, `correct`, and optional fields matching the CSV template. Imported questions are **single-answer**; multi-answer JSON import is not yet supported.
- **Student question issue reports**: After a submitted examination, a student may report a question from the detailed review screen. The server verifies the attempt is submitted, belongs to that student, and contains the question. Reports are private administrator-only WordPress records under **Smart MCQ → Question Issue Reports**. Duplicates and daily submissions are rate-limited; reports are included in WordPress privacy export/erasure integrations.
- **Basic post revisions** for question titles via WordPress revisions support. Full question-meta revision restoration and an editorial approval workflow are **not implemented**.

## Step 16: selected learning/presentation extras (partially implemented)

- **Gutenberg dynamic blocks** for Exam, Student Dashboard, Practice, and History. These wrap existing, access-controlled shortcodes and do not expose correct answers through the editor.
- **Printable completion certificate** shortcode, restricted to the logged-in owner of a *passed*, submitted attempt: `[smcq_certificate attempt_id="123"]`. Browser Print → Save as PDF; no independently verifiable certificate issuing or digital signatures.

## Still planned — not complete

- **Step 13 remaining:** full MathJax/KaTeX LaTeX, matching questions, arbitrary option counts, reusable passage groups, partial credit.
- **Step 14 remaining:** Word DOCX import, multi-answer importing, approval/reviewer workflow, rich question-meta revisions, interactive duplicate review.
- **Step 15:** Custom student exam builder, daily/weekly quiz scheduler, comprehensive live exam monitoring, notifications.
- **Step 16 remaining:** AI-assisted question drafting and explanations, dedicated role/capability system, rich certificates, badges and rewards.
- **Step 17:** Live WordPress integration, real payment tests, concurrent load testing, external security assessment, and production launch sign-off.

## Upgrade and test

1. Back up your WordPress database and plugin directory; upgrade a **staging copy** before production.
2. Upload the plugin ZIP via **Plugins → Add New → Upload Plugin → Replace current with uploaded**.
3. Create one of each question type, publish an exam using these questions, and run a **student-account** attempt, including switching tabs and finishing it.
4. Confirm that answer keys cannot be fetched while active and the completed attempt shows the right result and explanation. Also test `[smcq_practice]` with explicitly practice-enabled questions.
5. Upload the bundled `examples/smart-mcq-import-template.csv` or a structured JSON file and inspect the imported records.
6. Try the issue-report button after completing an exam; confirm only administrators can read the saved reports.
7. For Gutenberg, add a **Smart MCQ Exam** block, provide the numeric exam ID, and preview the published page.
8. Create a certificate page with `[smcq_certificate attempt_id="123"]`; only the owning student with a passing result should be able to see it.

Run standalone tests (no WordPress install needed):

```bash
find . -name '*.php' -print0 | xargs -0 -n1 php -l
for f in tests/test-*-cli.php; do php "$f"; done
for f in assets/*.js; do node --check "$f"; done
```

The automated tests are **PHP CLI helper and parser tests**, not a substitute for end-to-end WordPress deployment tests. XLSX depends on PHP ZIP and SimpleXML extensions; the local CLI environment used for packaging lacks them. Real WooCommerce checkout remains untested here.

## Shortcodes

```text
[smcq_exam id="123"]
[smcq_history]
[smcq_dashboard]
[smcq_my_report]
[smcq_practice]
[smcq_leaderboard id="123"]
[smcq_certificate attempt_id="456"]
```

License: GPL-2.0-or-later.