# Smart MCQ Manager — Step 11 (v1.1.0)

WordPress MCQ question bank and examination plugin with WooCommerce-based premium access. This is an upgrade from v1.0.0; **existing questions, exam definitions, student answers, results, practice sessions and reports remain in place**.

## New in Step 11: paid exams, bundles and subscriptions

- Configure each **Exam** as **Free** (default) or **Premium** from the **Premium Access & WooCommerce** meta box.
- Assign one or more **published WooCommerce product IDs** to a premium exam. Purchasing **any one** of these products grants access. Use **the same product ID on multiple exams** to sell a multi-exam package.
- Optional **access duration in days** per exam. `0` grants indefinite access while the purchase remains valid. A new qualifying purchase renews access from the newer payment date.
- Purchases are verified **server-side** using WooCommerce order APIs, compatible with High-Performance Order Storage (HPOS). Only orders with status **Processing** or **Completed**, attached to the current logged-in WordPress account, count. **Pending, On Hold, Cancelled and Refunded** orders do not count. Fully refunded order line items do not count even if the order remains Completed.
- Optional compatibility with the **official WooCommerce Subscriptions extension**: a subscription-type product only grants access while `wcs_user_has_subscription()` reports an **active** subscription. Cancelled or expired subscriptions cannot grant access via an earlier paid order. **Recurring billing and subscription plans are not provided by this plugin**; they require the WooCommerce Subscriptions extension or another compatible product.
- The `[smcq_exam id="123"]` shortcode displays product purchase links for an authenticated student without access. Students must be logged in to participate.
- Exam **state**, **start**, **answer**, and **submit** REST routes all re-check entitlement. Previously completed results/history remain available only to their original owner, including if access was later revoked.
- **No card details, payment tokens, payment records, gateway code or billing information** are stored by Smart MCQ Manager. WooCommerce handles the checkout, receipts, refunds, payment providers, orders and coupon usage.
- WooCommerce is an **optional dependency**: the plugin and free exams work without it; premium exams fail closed if WooCommerce is missing/inactive or product IDs are invalid.

### Set up your first paid exam

1. Install and configure **WooCommerce**, including a supported payment gateway. In WooCommerce settings, require customers to log in or create an account for checkout. **Guest orders are intentionally not recognized for exam access**, even if the email address matches.
2. Create a **published simple, virtual WooCommerce product** (e.g. `BCS English Model Test`). Enter its price; note its product ID. For an exam package (e.g. `5 BCS Model Tests`), create one product and add that **same product ID** to the five exams.
3. Open **Smart MCQ → Exams → Edit Exam → Premium Access & WooCommerce**, choose **Premium**, enter one or more product IDs and set **Access validity (days)**. Save/Update the exam. Premium + no valid product IDs **remains locked**; it does not silently turn into a free exam.
4. Place `[smcq_exam id="123"]` on a WordPress page (replace `123` with the exam ID). Exclude it and all user-specific routes from full-page/CDN caching.
5. In a **staging** site, create two test accounts. Confirm an unpaid student sees the purchase message and **cannot start an attempt via a direct REST call**. Buy the product using the buyer account and confirm access works; test expiry, refunds, paid order status, and order ownership.
6. If testing subscriptions, use WooCommerce Subscriptions to sell an actual subscription-type product; confirm active access and that expiry/cancellation removes access.

### Admin notes & limitations

- The existing exam duration, attempt limit, exam schedule, randomization, grading and security-monitoring settings continue to apply. Premium access is *in addition to* those rules.
- Purchase access is tied to the **WooCommerce order customer ID** (WordPress user ID). An order completed as a guest, even with a matching email, does not grant access. Migrate guest orders to registered customers through approved WooCommerce administration before access can be recognized.
- One product can represent multiple exams; this is a **bundle** through the exam access mapping, not a separate inventory or WooCommerce bundle-product integration.
- `Days` are calculated in UTC as 24-hour periods from WooCommerce's paid date (or completion date for manually completed orders without a paid date). All qualifying orders remain checked directly, so refund and access revocation takes effect without a custom synchronization job.
- For safety, order lookup searches the **latest 2,000 paid orders per customer** (40 pages × 50) and fails closed if an eligible purchase is older. At large scale, replace this bounded query with a WooCommerce-synchronized, indexed entitlement ledger. This release does **not** claim to support enterprise volumes.
- Paid access is implemented for **published WooCommerce products**. The supported setup is simple/virtual products and active WooCommerce Subscriptions products; variation handling, composite products, partial monetary refunds without quantity refunds, gateway-specific subscription plugins, multi-site sharing, and checkout customization are **not guaranteed**.
- For an active attempt, an expired/refunded purchase disables further answer and submit requests. The server can still finalize a timed-out attempt when it is next accessed. Completed result and review endpoints remain owner-restricted.
- WooCommerce and the optional subscription extension must be maintained separately. **No built-in payment gateway and no direct bKash/Nagad integration** are included; use a compatible WooCommerce payment plugin.
- CLI unit tests exercise the WooCommerce access policy with mocks, **not live payment processing or full WordPress/WooCommerce integration**. Always stage-test checkout/HPOS and cancellation/refunds before launching paid exams.

### Existing shortcodes

```text
[smcq_exam id="123"]          Free or premium exam player and paywall
[smcq_history]                Personal exam history
[smcq_dashboard]              Personal performance dashboard
[smcq_my_report]              Personal report exports
[smcq_practice]               Smart revision
[smcq_leaderboard id="123"]   Opt-in anonymous leaderboard after closing
```

### Developer smoke tests

```bash
find . -name '*.php' -print0 | xargs -0 -n1 php -l
for f in tests/test-*-cli.php; do php "$f"; done
for f in assets/*.js; do node --check "$f"; done
```

The Step 11 suite covers product-ID validation, duration boundaries, paid/refunded/on-hold orders, ownership, missing configurations, shared package products, and subscription status. Existing tests are retained.

### Upgrade & data retention

- Upgrade **over the current plugin** via the WordPress upload replacement workflow; back up your database first. Do not delete the plugin and its data to update.
- This version adds exam post metadata (`_smcq_exam_access`, `_smcq_exam_products`, `_smcq_exam_access_days`) and no new database tables. All previously created exams default to **Free**.
- WooCommerce stores payments and order data. WordPress/WooCommerce privacy retention policies continue to apply; Smart MCQ does not copy billing details.

GPL-2.0-or-later.