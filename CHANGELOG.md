# Changelog

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
