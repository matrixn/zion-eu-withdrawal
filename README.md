# Zion EU Withdrawal

Professional foundation for the EU consumer withdrawal workflow in WooCommerce.

Phase 0 and Phase 1 provide legal traceability and the stable plugin skeleton. Phase 2 and Phase 3 add the public withdrawal page, order lookup, review and confirmed server-side submission flow. Phase 4 and Phase 5 add account history, administrative request management and expiring guest access links.

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
