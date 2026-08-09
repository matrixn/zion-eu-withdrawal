<?php

declare(strict_types=1);

/** Run with: wp eval-file tests/smoke/phase610-contract.php */

if (! defined('ABSPATH')) {
    exit("Run this file through WP-CLI.\n");
}

global $wpdb;

$tables = (new Zion\EuWithdrawal\Infrastructure\Database())->table_names();
foreach ($tables as $name => $table) {
    if ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table)) !== $table) {
        WP_CLI::error("Missing {$name} table: {$table}");
    }
}

$settings = (array) get_option('zion_eu_withdrawal_settings', []);
foreach (['checkout_disclosure_enabled', 'default_eligibility_rule', 'send_consumer_email', 'send_admin_email'] as $key) {
    if (! array_key_exists($key, $settings)) {
        WP_CLI::warning("Setting is using defaults or is not yet persisted: {$key}");
    }
}

WP_CLI::success('Phase 6-10 schema and settings contract are ready.');
