# Smart MCQ Manager — Step 3 (v0.3.0)

A WordPress **private question bank**, now with a controlled bulk importer for UTF-8 CSV and Excel `.xlsx`. The plugin is **not yet a student examination system**.

## Requirements

- WordPress 6.0 or later; PHP 7.4 or later.
- An admin account (`manage_options`) to run imports.
- PHP ZIP and SimpleXML extensions **only** if importing Excel `.xlsx`; CSV does not need them.
- A staging-site backup before upgrading an existing production installation.

## Step 3: Bulk Import

1. Log in as an administrator and open **Smart MCQ → Bulk Import**.
2. Download the **Sample CSV Template**, or prepare an `.xlsx` worksheet with one header row.
3. Upload a `.csv` / `.xlsx` file, then inspect the first **five rows**.
4. Map CSV/Excel columns to `question`, `option_a`, `option_b`, `option_c`, `option_d`, `correct` (these **six** are required). Map optional columns, or set them to **Ignore**.
5. Choose **Draft** (recommended) or **Published**. Note: published question *posts* are still a private WordPress post type, not public exam questions.
6. Start the import. Process **40 questions per request** via the **Continue Next Batch** button until complete. Review the counts and errors.

**File limits:** up to 500 data rows, 32 columns, and 3 MiB per upload (and 2 MiB for parsed preview data). For larger imports, split into multiple files. XLSX reads the **first worksheet** and does not calculate spreadsheet formulas (formula cells are rejected). CSV should be saved as **UTF-8** to preserve Bangla.

### Fields

| Column | Allowed examples | Default |
| --- | --- | --- |
| `question` | বাংলা / English question | required |
| `option_a` – `option_d` | Answer option text | required |
| `correct` | A, B, C, D | required |
| `explanation` | Text/limited safe HTML | blank |
| `subject` | General Knowledge | blank |
| `topic` | Bangladesh | blank |
| `exam_source` | BCS | blank |
| `tags` | `Bangladesh\|Geography` | blank |
| `difficulty` | easy, medium, hard | easy |
| `language` | bn, en, mixed | bn |
| `exam_year` | 2026 (1900–2100) | blank |
| `reference` | Book title | blank |
| `reference_url` | https://example.org/ | blank |
| `marks` | 1 | 1 |
| `negative_marks` | 0.25 | 0 |

### Duplicate handling and validation

- The importer **never overwrites** existing questions.
- A SHA-256 hash of case-normalized, whitespace-normalized question text is stored as `_smcq_question_hash` for imported questions; future imports skip matches to that hash. For older Step 1/2 questions, identical original titles are also checked.
- Similar questions with different wording, punctuation, or casing not caught by the legacy exact-title fallback may still need manual review. This is not semantic duplicate detection.
- All four options and a valid A–D key are required. Invalid questions are **skipped**, counted and reported (up to 15 specific row errors).
- Optional classifications are created as WordPress taxonomy terms (subject, topic, exam source, tags).
- Uploads are parsed from temporary PHP upload files; no original upload is placed in a publicly reachable directory. Parsed preview rows live in a **per-admin WordPress transient** for up to 30 minutes, refreshed after each batch and deleted after successful completion.
- **Cancel** stops remaining imports; it does **not** undo questions already inserted. Site backups are recommended before large imports.

## Upgrade from Step 2

WordPress → Plugins → Add New Plugin → Upload Plugin → choose the v0.3.0 ZIP → **Replace current with uploaded**. Keep the same plugin directory `smart-mcq-manager`. **Do not delete or uninstall** the existing plugin while upgrading. Original posts, taxonomies and option/explanation metadata from earlier steps are retained. Take a database backup first.

## Security

The question post type remains private with REST disabled. All import mutations require `manage_options` and WordPress nonces. Field mapping is checked server-side; input and output are validated/escaped. Import logic refuses formula cells in XLSX and enforces compressed and uncompressed size ceilings.

## Development checks

```bash
find . -name '*.php' -print0 | xargs -0 -n1 php -l
php tests/test-importer-cli.php
```

**Limitations:** The importer is for administrator use and is not a publicly exposed REST API. Performance depends on the site's WordPress/database configuration. Test the preview with a small CSV on staging before importing hundreds of questions.

**Next:** Step 4 — Exam builder, question selection, and scheduled exam configuration.

License: GPL-2.0-or-later.