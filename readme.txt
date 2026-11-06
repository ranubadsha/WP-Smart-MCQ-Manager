=== Smart MCQ Manager ===
Contributors: smart-mcq
Requires at least: 6.0
Requires PHP: 7.4
Stable tag: 1.1.0
License: GPLv2 or later

Private MCQ question bank, bulk import, exams, student player, grading, dashboards, practice, reports, security and optional WooCommerce premium access.

== New in 1.1.0 (Step 11) ==
- Free or premium exam access configured per exam.
- One or more WooCommerce product IDs unlock each exam; a product can unlock multiple exams (packages).
- Time-limited access in days, optional lifetime pass.
- Live paid-order / account ownership / refund verification, HPOS-friendly WooCommerce CRUD.
- Optional WooCommerce Subscriptions: active official subscriptions only.
- Premium shortcode paywall and server-side REST admission checks.
- No direct payment processing or storage of payment details.

== Requirements ==
WordPress 6.0+, PHP 7.4+.
WooCommerce is OPTIONAL for free exams, REQUIRED for premium exams.
For recurring subscription payments, also install and configure WooCommerce Subscriptions.

== Installation ==
1. Back up the database and test on a staging site.
2. Install v1.1.0 by replacing the current Smart MCQ Manager plugin ZIP (do not delete old data).
3. Configure WooCommerce checkout with registered customers (guest orders do not qualify).
4. Create published simple/virtual products in WooCommerce.
5. In Smart MCQ -> Exams, open an exam and set Premium Access & WooCommerce options.
6. Publish the exam and use [smcq_exam id="123"] on a page.
7. Test paid/unpaid/refunded scenarios with separate student accounts.

== Shortcodes ==
[smcq_exam id="123"]
[smcq_history]
[smcq_dashboard]
[smcq_my_report]
[smcq_practice]
[smcq_leaderboard id="123"]

== Security ==
Premium access is checked by server-side order data, not client flags. Order status must be Processing or Completed, bound to the current logged-in customer and not fully quantity-refunded. Cancelled, refunded, unpaid and expired subscriptions are denied. Completed results remain visible only to the student who submitted them. Page caching must exclude private exam pages.

== Known limitations ==
Native checkout, monthly billing and payment gateways are provided by WooCommerce and its extensions, not this plugin. Access searches the latest 2,000 paid orders for a customer; a qualifying older purchase can fail closed. Native subscriptions require WooCommerce Subscriptions. Partial amount-only refunds may need manual access management. No live WordPress/WooCommerce integration test has been run.

== Changelog ==
= 1.1.0 =
Step 11: WooCommerce products, paid exam eligibility, packages, duration, subscription checks, refund/ownership enforcement.
= 1.0.0 =
Step 10: optional security events, single active editing session and admin security report.