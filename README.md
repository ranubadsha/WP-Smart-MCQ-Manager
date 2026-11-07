# Smart MCQ Manager v1.4.3 — Translator comment correction

This patch places the `translators:` comment directly above the `__()` call for the administrative exam pagination label (`Page %1$d of %2$d`) in `includes/class-smcq-analytics.php`. The original v1.4.2 Plugin Check remediation notes are retained below. **Official Plugin Check must still be rerun on the installed v1.4.3 package; this is not WordPress.org approval.**

# Smart MCQ Manager v1.4.2 — Plugin Check remediation

Version 1.4.2 addresses the 110 errors and 235 warnings reported by the user against v1.4.1, with specific code changes described in `directory-submission/PLUGIN-CHECK-FIX-REPORT.md`. Its WordPress.org submission status is **pending**: official Plugin Check must be rerun on v1.4.2 and the site must be integration tested. Requires WordPress 6.2+ for `%i` table-name placeholders.

# Previous submission preparation — v1.4.1

The WordPress.org packaging preparation is available in the `readme.txt`, `LICENSE.txt`, and `languages/smart-mcq-manager.pot` files and the `directory-submission/` documents. This is **not a claim of WordPress.org approval**; Plugin Check and live-site tests remain outstanding.

# Smart MCQ Manager v1.4.0 — Advanced workflow upgrade

**Status: development / pre-production.** Upgrades v1.3.0, preserving existing question, exam, attempt, report, practice, and WooCommerce records. **Do not launch high-stakes or paid exams until staging, checkout, load and security tests pass.**

## Added in v1.4.0

### Question bank and editorial management
- Separate **MCQ Question Author** and **MCQ Question Reviewer** roles with custom post-type capabilities. Authors can submit questions for review; authorized reviewers can publish. Existing administrators and WordPress editors receive the new question capabilities. Question author is not permitted to publish. Test role migrations in staging before deploying.
- **Question Version History** box stores 25 recent answer-and-metadata snapshots, with reviewer-controlled restore, separate from WordPress title revisions. Restoring a version creates a checkpoint. Existing in-progress and submitted attempts retain their original frozen question snapshots.
- Private **Smart MCQ → Duplicate Review** screen groups normalized question titles from the latest 1,000 questions. No automatic deletions; it is a human review aid, not semantic deduplication.
- **DOCX table import:** Word files containing a first table with a column header row and up to 500 question rows can now be previewed, mapped and imported. Requires PHP ZIP + DOM. Embedded images are **not** extracted. Complex document formatting and non-table questions are not supported. This is an optional source format, not an AI document parser.
- **Multiple-answer, True/False, Passage, Math, Single** import columns: `question_type` and `passage`, with `correct` accepting `A,C` for multiple-correct. Four options are still required for all types except True/False (A/B). Image/audio imports remain manual with WordPress Media Library IDs.
- Existing CSV, XLSX, JSON imports, draft/publish import controls, duplicate checks and 40-row commit batches continue to work.

### Exams, learning, monitoring
- **Daily and Weekly Quiz** modes alongside existing practice and wrong-answer revision. Deterministic selections are taken only from practice-enabled published questions, not from unreleased exams.
- **Custom Practice**: students can select subject, difficulty and question count; a server-side eligibility check filters every question before sharing it. Sessions are stored separately from official exams. These are *practice sessions*, not administrator-certified exams.
- In-app published **Upcoming Exams** schedule shortcode and admin-only **Live Monitor** attempt-count report. The monitor is a manually refreshed aggregate; it does not prove cheating or finalize overdue submissions.
- **Student Achievements** shortcode computes five progress badges from the user's 500 most recent submitted attempts, without exposing other users' data.

### Optional AI-assisted drafting
- **Smart MCQ → AI Drafts** creates up to five new, **unpublished** single-answer MCQ draft questions via the OpenAI Responses API. Opt-in only: define `SMCQ_OPENAI_API_KEY` in `wp-config.php`. Optionally set `SMCQ_OPENAI_MODEL`. No API key is saved in WordPress database or included in the ZIP; only admin-entered topic/language/count are sent to the provider. Costs depend on your API account and model. AI output is untrusted, validated, saved only as drafts, and MUST be checked by a human before publication. AI calls were not exercised in this offline build.

### QA and production readiness
- New PHP CLI tests for editorial duplicates, roles definitions, AI-output validation, daily question selection, custom-import validation, badges and live monitor aggregates.
- GitHub Actions **WordPress + MySQL integration smoke-test workflow** and a `tests/integration-wordpress.php` WP-CLI script for activation, schema/roles, shortcodes, and question eligibility. **This workflow was added but has not been run in the local environment.**

## Shortcodes

```text
[smcq_exam id="123"]
[smcq_history]
[smcq_dashboard]
[smcq_my_report]
[smcq_practice]              # Includes Daily, Weekly, Custom and revision modes
[smcq_daily_quiz]            # Uses the practice UI
[smcq_weekly_quiz]           # Uses the practice UI
[smcq_custom_practice]       # Uses the practice UI
[smcq_leaderboard id="123"]
[smcq_certificate attempt_id="456"]
[smcq_upcoming_exams]
[smcq_badges]
```

## Upgrade procedure

1. Back up your database, plugin files, and any custom integrations. Upgrade a staging copy before production.
2. Upload the ZIP through **Plugins → Add New → Upload Plugin → Replace current with uploaded**.
3. Verify questions are visible to an administrator, new author/reviewer roles work, and reviewers can publish without permitting authors to publish.
4. Check **Smart MCQ → Duplicate Review** and a test question's Version History. Restore a test version, not live exam content.
5. Upload CSV, DOCX and XLSX examples on a host with ZIP/DOM/XML; validate your Bangla text, options, answer keys and source metadata. Imported questions default to draft.
6. Create a practice-enabled test question, run Daily, Weekly and Custom modes from a student account. Confirm unreleased exam questions are not accessible.
7. Test `wp smcq doctor`, exam start/autosave/timeout, grading, reports, refunds, private record export/erase, and tablet/mobile rendering.
8. For AI, configure your own provider key only in the **server configuration**; test on a staging website and review every generated answer.
9. Run the new GitHub Actions integration workflow and manually verify results. Run high-concurrency database and payment tests before opening a public exam.

## Known limitations / unverified requirements

- **Not yet fully implemented:** interactive matching questions, shared editable passage groups, arbitrary answer counts, per-option partial credit, bundled/offline LaTeX rendering, multi-page PDF certificates and signed public certificate validation, email notification subscriptions and automated proctoring, advanced teacher-classroom assignments, and fully localized Bangla admin screens.
- **Not verified here:** real WordPress/WooCommerce activation and payment, GitHub Actions result, live AI Responses API, DOCX/XLSX runtime parsing in PHP without ZIP/DOM/XML, external security review, and real high-concurrency load tests.
- User attempts use the established server-side timed exam system. Practice selection uses no confidential exam questions, because only explicitly practice-enabled, published questions qualify.
- Existing exams and questions are not rewritten in a new database schema. WordPress native `pending` status supports reviewer approval on the updated custom question post type.
- CI smoke tests are a starting point, **not** a security or production certification.

## Standalone tests

```bash
find . -name '*.php' -print0 | xargs -0 -n1 php -l
for f in tests/test-*-cli.php; do php "$f"; done
find assets -name '*.js' -print0 | xargs -0 -n1 node --check
```

## License
GPL-2.0-or-later.