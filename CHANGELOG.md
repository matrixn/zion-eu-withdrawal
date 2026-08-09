# Changelog

## 1.1.0 — Phase 11–12 Pro add-on

- Added the Pro add-on architecture over the existing Free/core plugin and database.
- Added visual customization, floating placement, bilingual checkout copy and controlled custom CSS.
- Added the Pro Rules Engine with product/category/tag/order/shipping/country conditions and indicative mixed-order evaluation.
- Added delivery-date provider controls, delivered-status fallback, deadline dashboard, CSV export and signed withdrawal webhooks.
- Added protected REST endpoints for health, withdrawal reporting and delivery-date updates.
- Added agency/white-label controls and Pro smoke coverage.

## 1.0.2 — WooCommerce Checkout Blocks compatibility

- Added official WooCommerce Checkout Blocks integration for the withdrawal disclosure.
- Added runtime smoke coverage for block registration and rendered checkout output.
- Added WooCommerce cart/checkout blocks compatibility declaration.

## 1.0.1 — Simplified WordPress admin navigation

- Removed the redundant plugin header navigation visually from all admin pages; WordPress core menu remains the single navigation source.

## 1.0.0 — Phase 6-10 operational Free release

- Added consumer and merchant confirmation e-mails with exact immutable snapshots.
- Added notification delivery logs, failure status, resend actions and request audit history.
- Added item-level EligibilityEngine with Romanian profile exception candidates, delivery-date provider abstraction and do-not-block fallback.
- Added WooCommerce product and category eligibility overrides.
- Added configurable checkout disclosure, thank-you-page link and WooCommerce customer e-mail link.
- Added request search, source/status filters, CSV export, internal notes and operational status management.
- Added the Phase 10 QA matrix and WP-CLI schema/settings smoke contract.

## 0.5.0 — Phase 4–5 account and guest access

- Added the WooCommerce My Account `Withdrawals` endpoint with history and detail views.
- Added the admin Withdrawal Requests register with request details and immutable snapshots.
- Added expiring guest access tokens, token revocation/regeneration and secure e-mail links.
- Added generic guest-link responses to reduce order enumeration risk.
- Replaced overview feature cards with live withdrawal statistics.
- Added the operational How it works guide and administration checklist.

## 0.3.0 — Phase 2–3 withdrawal flow

- Added an automatically created withdrawal page with shortcode and Gutenberg block.
- Added responsive, accessible public withdrawal interface with standard legal content.
- Added logged-in WooCommerce order buttons and secure guest-capable order identification.
- Added AJAX lookup, statement review, explicit confirmation and success screen.
- Added server-side timestamps, unique withdrawal IDs, immutable statement snapshots and item records.
- Added rate limiting and generic lookup errors to reduce order enumeration risk.

## 0.1.0 — Phase 0–1 foundation

- Added the versioned Romanian legal profile `RO-2026.06.19-v1`.
- Documented the 14-day standard, start-date rules, mandatory evidence and EXC-A → EXC-M mappings.
- Added plugin bootstrap, activation/deactivation/uninstall lifecycle and idempotent database upgrades.
- Added HPOS-compatible foundation tables for withdrawals and withdrawal items.
- Added bilingual Romanian/English admin presentation and settings experience.
- Added AJAX autosave with per-setting feedback, security defaults and WooCommerce dependency status.
