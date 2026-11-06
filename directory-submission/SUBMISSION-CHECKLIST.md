# WordPress.org submission go/no-go checklist

## Packaged and checked
- [x] WordPress-style plugin root, single main PHP plugin header, correct plugin ZIP layout.
- [x] WordPress.org readme.txt containing description, FAQ, installation, privacy/third-party disclosure and changelog.
- [x] Stable tag and PHP plugin version both `1.4.1`.
- [x] GPLv2 license file, license headers and original graphic asset licensing note.
- [x] Four correctly sized WordPress.org banner/icon PNG files, separately staged for SVN.
- [x] Human-readable source included (no external compressed remote libraries).
- [x] POT translation template with extracted static English strings (coverage needs review).
- [x] ZIP integrity, PHP syntax, JavaScript syntax and existing CLI regression suite.

## Required before responsible submission/public use
- [ ] WordPress.org contributor username and rights confirmed (currently placeholder `ranubadsha`, assumed from GitHub).
- [ ] WordPress.org slug availability and naming/trademark check.
- [ ] Plugin Check (PCP) report run on a real WordPress staging installation; issues resolved.
- [ ] WordPress.org readme validator result checked; actual `Tested up to` version set after testing.
- [ ] Real WordPress/MySQL integration and migration verification on supported WP+PHP combinations.
- [ ] All REST, AJAX and admin actions manually security-reviewed and exercised with role matrix.
- [ ] DOCX/XLSX importer verified with PHP ZIP/DOM/SimpleXML, Unicode, malformed documents and edge cases.
- [ ] WooCommerce paid order, refund, expiration, subscriptions and HPOS behavior confirmed where enabled.
- [ ] OpenAI option exercised explicitly, API error paths and cost/privacy notices checked.
- [ ] 100–1,000+ concurrent exam sessions load-tested for race conditions, autosave and timeouts.
- [ ] Personal data retention, export/erasure, policy and uninstall behavior reviewed.
- [ ] Professional testers approve responsive accessibility and actual staging screenshots (optional).
- [ ] Owner approval of name, logo, image licensing, author/contact/support commitments.

The ZIP is a candidate artifact, not WordPress.org approval or a guarantee of acceptance.