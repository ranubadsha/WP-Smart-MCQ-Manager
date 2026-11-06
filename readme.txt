=== Smart MCQ Manager ===
Contributors: smart-mcq
Tags: mcq, question bank, csv import, xlsx import, bangla
Requires at least: 6.0
Requires PHP: 7.4
Stable tag: 0.3.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Private question-bank plugin with CSV/XLSX bulk imports, question explanations and classifications.

== Description ==

Step 3 includes:
* Private admin-only MCQ question bank with four choices and rich explanations.
* Subject, Topic, Exam Source, and Tag taxonomies.
* Difficulty, Language, Exam Year, References, Marks and Negative Marks.
* Admin question filters, identifiers, and completeness indicators.
* UTF-8 CSV / first-sheet Excel XLSX bulk import with column mapping and five-row preview.
* Validates rows, skips exact duplicates, imports in 40-question batches, and reports errors.

This is **not yet** a frontend examination system.

== Installation ==

1. Back up WordPress before upgrading.
2. Install or replace via Plugins > Add New Plugin > Upload Plugin.
3. Open Smart MCQ > Bulk Import to upload and preview questions.
4. Confirm the column mapping, then continue each import batch until completion.

== Frequently Asked Questions ==

= Can I import Bangla? =
Yes, save your CSV as UTF-8. Excel .xlsx also supports Unicode.

= Do I need a PHP extension for XLSX? =
Yes, PHP ZIP and SimpleXML. CSV does not require either.

= What is the import limit? =
500 data rows and 3 MiB per upload. Split larger datasets into separate files.

= Can I undo an import? =
Cancel stops the unprocessed batches but does not remove questions that were already added. Back up your database before large imports.

= Are questions publicly accessible? =
No. The question post type is private and its REST API disabled.

== Changelog ==

= 0.3.0 =
* Add private CSV/XLSX bulk import, mapping, preview, validation, deduplication and batch summaries.

= 0.2.0 =
* Add advanced question classifications, metadata and admin filters.

= 0.1.0 =
* Initial private question bank.