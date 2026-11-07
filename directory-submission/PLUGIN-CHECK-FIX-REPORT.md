# Smart MCQ Manager 1.4.2 — Plugin Check repair report

**Input:** Plugin Check CSV uploaded on November 7, 2026, containing **345 findings (110 errors and 235 warnings)** from version 1.4.1.

## Code changes

- SQL identifier escaping: bound plugin-owned custom table names with WordPress **`%i` identifier placeholders** in `$wpdb->prepare()`. WordPress **6.2+ is now required** (older releases do not support `%i`).
- Replaced dynamic SQL fragments in administrative report and security-event lookups with static prepared statements whose user-filter values are independently bound.
- Removed direct `fopen`/`fwrite`/`fclose`/`readfile`/`unlink` calls **in distributed plugin code**; updated CSV handling to PHP SPL streams and WordPress filesystem helpers for temporary XLSX output.
- Added missing translator notes and corrected numbered translation placeholders.
- Removed `suppress_filters => true` query configuration in the exam builder.
- Added WordPress.org **Tested up to** metadata, with conservative compatibility target **6.2**; this is **not a claim of staging verification**. Maintained GPL license and packaging structure.
- Sanitized additional export and paid-access inputs; retained privacy and request permission checks.
- Updated isolated health-check fixture to understand `%i` escaped table names.

## Validation performed

- PHP syntax checked across plugin PHP files and the included local CLI tests.
- JavaScript syntax checked with Node.js.
- **12 CLI regression suites passed**; DOCX/XML and XLSX PHP runtime parts skipped because local CLI lacks DOM/ZIP/SimpleXML.
- Plugin ZIP integrity and release version consistency checked.
- WordPress database integration, real payment flows, and **actual official Plugin Check on this new ZIP have not been run here**.

## Remaining warnings and required actions

The original report included **62 direct-database-query warnings, 59 no-caching warnings, and 60 nonce-related warnings**. Custom MCQ tables legitimately use SQL for attempts and snapshots, and many read-only admin filters are GET requests. These may still appear during the **new** Plugin Check; they must be evaluated rather than suppressed indiscriminately.

**To verify:** Install v1.4.2 on a WordPress 6.2+ staging site, run the latest Plugin Check plugin against Smart MCQ Manager with all checks enabled, export the *new* CSV, resolve any remaining errors, and do not submit to WordPress.org until that recheck and functional validation pass. The original scan cannot establish a new pass result.

## Release artifacts

- `smart-mcq-manager-v1.4.2-plugin-check-fixes.zip` — installable plugin directory ZIP.
- `wordpress-org-svn/trunk/` and `wordpress-org-svn/tags/1.4.2/` — SVN source layout.
- `wordpress-org-svn/assets/` — WordPress.org icons and banners (not bundled in the install ZIP).
- Branding and documentation carried forward from v1.4.1.