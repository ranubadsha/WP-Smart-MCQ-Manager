=== Smart MCQ Manager ===
Contributors: ranubadsha
Tags: exams, quiz, mcq, education, assessment
Requires at least: 6.2
Requires PHP: 7.4
Tested up to: 6.2
Stable tag: 1.4.2
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/old-licenses/gpl-2.0.html

Create and manage MCQ question banks, timed online exams, grading, student practice, results and private reports.

== Description ==

Smart MCQ Manager helps site administrators run online multiple-choice exams using WordPress.

**Features**

* Private question bank with subjects, topics, difficulty and exam source classifications.
* Single-answer, multiple-answer and true/false questions, with passage or media context.
* CSV, XLSX, JSON and table-based DOCX imports with preview and validation (server PHP extensions required for some formats).
* Exam builder, time limits, attempt limits, shuffled questions and answer order, automatic grading and negative marking.
* Student history, personal analytics, bookmarks, revision practice, daily/weekly practice and anonymous opt-in leaderboards.
* Administrator reporting, CSV/XLSX exports, editorial review and question issue reporting.
* Optional WooCommerce exam purchase authorization and opt-in security events.
* Optional AI-generated **unpublished draft questions** initiated by an administrator only.
* Gutenberg blocks and shortcodes for examinations and student pages.

**Important:** No live WordPress integration, payment gateway, or concurrent-load certification is claimed by this listing. Test on staging before using for high-stakes examinations.

= Optional third-party services =

**OpenAI API (optional):** Only when an authorized site administrator configures the `SMCQ_OPENAI_API_KEY` constant and clicks Generate in Smart MCQ > AI Drafts, the plugin sends the **topic, requested language, quantity and formatting instructions** to the OpenAI API to produce draft MCQs. It does **not** send student exam records, student names, or private question banks. The service may charge for API usage. AI-generated output is saved in **draft** status and requires human review. No API request occurs simply because this plugin was installed.

Service: https://openai.com/api/
Terms: https://openai.com/policies/terms-of-use/
Privacy: https://openai.com/policies/privacy-policy/
API data usage: https://platform.openai.com/docs/guides/your-data

**WooCommerce (optional):** WooCommerce controls payment processing, customer orders and any configured payment gateways; Smart MCQ Manager checks eligible purchased order items to authorize premium exams. No independent credit-card collection or payment processing is provided by this plugin. WooCommerce is not required for free examinations.

WooCommerce service and documentation: https://woocommerce.com/
WooCommerce privacy: https://woocommerce.com/privacy-policy/

= Privacy =

For logged-in students, the plugin stores question responses, exam attempts, scores and selected activity needed for grading and reporting in your site's WordPress database. Students can view their own results. Authorized staff can view examination and security records. Practice bookmarks and revision history are stored separately.

Optional exam activity monitoring is disabled by default and can record events such as switching tabs. No IP addresses or raw session tokens are intentionally stored in its monitoring logs. Security-log cleanup is scheduled, and WordPress personal-data export and erasure integrations are provided. Configure your site's privacy policy and retention requirements, including any WooCommerce data policies, before deployment.

See the source and documentation: https://github.com/ranubadsha/WP-Smart-MCQ-Manager

== Installation ==

1. Back up your WordPress site and install the plugin ZIP via Plugins > Add New > Upload Plugin.
2. Activate **Smart MCQ Manager** and open **Smart MCQ** to add your first question.
3. Create and publish an exam with valid questions and marking rules.
4. Add `[smcq_exam id="123"]` to a page, substituting the exam post ID.
5. Create separate pages using `[smcq_dashboard]`, `[smcq_history]`, `[smcq_practice]`, and `[smcq_my_report]` if desired.
6. Use **Smart MCQ > System Status** to verify table indexes, PHP extensions and required runtime settings.
7. Test as a student on staging before making exams available to users.

**Optional:** WooCommerce must be installed for premium exams. The OpenAI feature requires an administrator-provided API key in `wp-config.php`; API access is never automatic.

== Frequently Asked Questions ==

= Do students need accounts? =

Yes. The examination player and personal dashboard require a logged-in WordPress user.

= Does the plugin send exam results to the OpenAI API? =

No. The optional AI drafting tool sends an administrator-entered topic, language and requested count only after an explicit button click, when an API key is configured.

= Are payments required? =

No. Free exams do not require WooCommerce. Paid exams use WooCommerce orders and configured third-party payment services.

= Can students resume examinations? =

The exam player supports automatic saved answers and resuming eligible active attempts, subject to configured time and attempt limits.

= Does switching tabs automatically fail a student? =

No. Optional monitoring is advisory and does not prove misconduct.

= Does uninstall remove student data? =

This release does not automatically delete examination and practice records on deactivation. Review privacy controls and back up data before any removal or migration.

= Are DOCX and Excel imports available on every host? =

They depend on PHP XML/DOM and ZIP support. Check **Smart MCQ > System Status** for server requirements.

== Changelog ==

= 1.4.2 =
* Use WordPress 6.2+ identifier placeholders for custom-table queries.
* Refactor CSV import/export and temporary Excel file cleanup to reduce prohibited direct filesystem calls.
* Improve readme metadata, translation comments, and request input handling.
* Preserve existing exam attempts and stored data during upgrade.


= 1.4.1 =
* Prepare WordPress.org-compliant listing and plugin header metadata.
* Add a license file, translation template and clearer external-service and privacy disclosures.
* Remove development-only files from the distributable ZIP. No new exam logic is introduced.

= 1.4.0 =
* Add editorial question review, duplicate review, DOCX table imports, custom practice, activity overview, achievements and optional AI draft questions.

== Upgrade Notice ==

= 1.4.1 =
A documentation and distribution update to v1.4.0. Back up the database and verify exam access and marking on staging first.