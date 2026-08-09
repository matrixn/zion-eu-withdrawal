# Zion EU Withdrawal

Professional foundation for the EU consumer withdrawal workflow in WooCommerce.

Phase 0 and Phase 1 provide legal traceability and the stable plugin skeleton. Phase 2 and Phase 3 add the public withdrawal page, order lookup, review and confirmed server-side submission flow. Phase 4 and Phase 5 add account history, administrative request management and expiring guest access links. Phase 6-10 add immutable notifications, indicative eligibility, checkout disclosure, operational administration and the Free QA matrix.

## Requirements

- WordPress 6.4+
- WooCommerce (integration status is shown in the admin until it is active)
- PHP 8.1+

## Development

```bash
composer install
composer check-syntax --no-interaction
```

Git operations and deployment are run from Windows. Composer and WordPress tooling are run from WSL.

## Phase 6-10 operations

- Consumer and merchant confirmation e-mails include the withdrawal ID, UTC timestamp, order reference and exact submission snapshot.
- Delivery failures never delete the withdrawal; they create a `notification_failed` status and a resendable notification log.
- Eligibility is an indicative, item-level signal based on delivery-date metadata, product overrides, category rules and the Romanian legal profile. Unknown information never blocks submission.
- Checkout disclosure can be enabled, positioned and edited from the AJAX settings page. The online withdrawal link is also available on the thank-you page and WooCommerce customer e-mails.
- Administrators can search, filter, export CSV, update operational status, add internal notes, inspect audit history and resend notifications.
- The release checklist is in `tests/QA/PHASE-10-MATRIX.md`.
