# Phase 10 — QA matrix

This matrix is the release checklist for the Free plugin. It must be run in a WordPress + WooCommerce environment before a public release.

| Scenario | Expected result |
| --- | --- |
| Logged-in customer | My Account → order → withdrawal page → review → explicit confirmation; account history contains the withdrawal. |
| Guest customer | Generic lookup response; secure expiring link; no order details are exposed before token verification. |
| Standard physical product | Submission remains available and item is classified `standard` when the delivery date is known. |
| Personalised product | Product override or category rule produces `potential_exception` with `EXC-C`; submission is not blocked. |
| Perishable / hygiene / digital / service | Product or category rule produces the configured candidate exception; merchant review remains required. |
| Mixed order | Each line keeps its own item-level classification and exception code. |
| Order under / over 14 days | Deadline is calculated from the configured delivery date, never from the order date. |
| Unknown delivery date | Deadline is `unknown` and the form remains usable; no automatic refusal is generated. |
| Invalid or expired token | Generic/expired response; no order data is returned. |
| Multiple attempts | Rate limit applies to public lookup and guest-link requests; a new guest link revokes the previous token. |
| Consumer/admin e-mail succeeds | Both messages contain withdrawal ID, UTC timestamp, order reference and the exact snapshot. |
| E-mail fails | Withdrawal remains saved, status becomes `notification_failed`, the failure is logged and admin can resend. |
| WooCommerce HPOS | Order identification uses WooCommerce order APIs, not direct order-table queries. |
| Classic checkout | Configured disclosure appears in the selected hook position and on the thank-you/e-mail surfaces. |
| Checkout Blocks | Verify the store theme/provider renders the configured disclosure; keep the public withdrawal page link available as fallback. |
| Admin operations | Search, status/source filters, CSV export, internal notes, status changes, notification resend and audit history work. |
| Responsive/accessibility | Keyboard navigation, focus visibility, live AJAX feedback and mobile layout remain usable. |
| Caching | Sensitive withdrawal pages are excluded from page cache according to store configuration. |
| Multilingual site | Romanian is the default; English strings are available and legal profile/version remains visible. |
| Timezone handling | Evidence uses server-side UTC timestamps; merchant-facing local formatting must not alter the stored snapshot. |

## Validation boundary

The unit suite and build checks prove syntax, pure legal-profile rules and packaging. They do not replace this matrix in a running WooCommerce environment. The local `wp-env` integration run is required when the bundled environment is available.
