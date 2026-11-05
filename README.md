# Smart MCQ Manager — Step 2 (v0.2.0)

A private, admin-only WordPress MCQ question bank for Bangla and English educational sites. **Not yet a student exam delivery system.**

## Requirements

- WordPress 6.0+
- PHP 7.4+

## What Step 2 adds

- Four private answer choices (A–D), correct-answer key and rich-text explanations.
- Hierarchical **Subjects**, **Topics**, **Exam Sources**, and non-hierarchical **Question Tags**.
- **Difficulty** (Easy/Medium/Hard), **Language** (Bangla/English/Mixed), **Exam Year**, **Reference**, **Reference URL**, **Marks**, **Negative Marks**.
- Admin list filters for taxonomy, difficulty, language and year.
- Question ID, **Ready/Incomplete** status, answer and additional columns; exam year can be sorted.
- Safe validation, escaping, save permission and nonce checks.

## Upgrade from Step 1

Upload the ZIP through **Plugins → Add New Plugin → Upload Plugin** and select **Replace current with uploaded**, or copy these files over the existing plugin. Back up the site first. **Do not deactivate/delete the existing plugin just to upgrade.**

Existing WordPress `smcq_question` posts and `smcq_subject` / `smcq_topic` terms are preserved. The original meta keys (`_smcq_options`, `_smcq_correct`, `_smcq_explanation`) are unchanged. Older questions receive default difficulty/language when they are saved in the new editor. Question completeness can be seen from the **Ready** column; incomplete records are **not** automatically unpublished.

## Editors

Open **Smart MCQ → Add MCQ Question**, write the question in the Title field, enter A–D choices, select the correct option and enter an explanation. Add question details and organize it using the category panels.

## Security & limitations

- Questions and answer keys are **private** (`public` and `show_in_rest` are disabled).
- WordPress permissions and save nonces are verified. Input is validated and output escaped.
- No frontend quiz, grading, enrollment, or public REST endpoint exists in this version.
- Marks fields are stored for future functionality; they do not grade an exam yet.
- A green **Ready** means the required text fields exist, not that the question was academically checked.
- Install on a staging site and verify compatibility with the site's other plugins before using in production.

## Lint

```bash
find . -name '*.php' -print0 | xargs -0 -n1 php -l
```

## Roadmap

**Step 3:** Validated CSV/XLSX bulk import with preview, duplicates and batch reporting.
**Step 4:** Exam builder and question selection rules.
**Step 5+:** Student frontend, timing, attempts and server-side grading.

License: GPL-2.0-or-later.