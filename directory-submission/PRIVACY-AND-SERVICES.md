# Privacy and external services — administrator checklist

## WordPress site records

The plugin maintains exam attempts, submitted answers, grading snapshots, practice session history, bookmarks, and optional anti-cheating activity logs in site tables. WooCommerce maintains purchases separately. Inform students in your site's privacy notice and configure applicable deletion/retention practices.

## OpenAI (optional, manual)

When explicitly triggered by an administrator with an API key configured, sends question topic, language and quantity to the API to draft unpublished questions. API key in wp-config.php, never place credentials in code or support tickets. See https://openai.com/policies/privacy-policy/ and https://openai.com/policies/terms-of-use/ and https://platform.openai.com/docs/guides/your-data.

## WooCommerce (optional)

Product IDs and order validity are checked through WooCommerce APIs to authorize premium exam access. Payment data and gateway terms are managed by WooCommerce and installed payment plugins. See https://woocommerce.com/privacy-policy/.

## Security events

Monitoring is off by default. A tab-switch event is a review signal only, not proof of misconduct. Review retention, access controls, and whether students are informed before enabling monitoring.