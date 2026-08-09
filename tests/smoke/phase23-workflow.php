<?php

declare(strict_types=1);

/**
 * Run with: wp eval-file tests/smoke/phase23-workflow.php
 * The script is intentionally read-only; it checks the activation/page/DB contract.
 */

if (! defined('ABSPATH')) {
    exit("Run this file through WP-CLI.\n");
}

global $wpdb;

$settings = (array) get_option('zion_eu_withdrawal_settings', []);
$page = ! empty($settings['withdrawal_page_id']) ? get_post((int) $settings['withdrawal_page_id']) : null;
$tables = [
    $wpdb->prefix . 'zion_eu_withdrawals',
    $wpdb->prefix . 'zion_eu_withdrawal_items',
];

foreach ($tables as $table) {
    if ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table)) !== $table) {
        WP_CLI::error("Missing table: {$table}");
    }
}

if (! $page || ! has_shortcode((string) $page->post_content, 'zion_eu_withdrawal')) {
    WP_CLI::error('The withdrawal page or shortcode is missing.');
}

if (! shortcode_exists('zion_eu_withdrawal')) {
    WP_CLI::error('The withdrawal shortcode is not registered.');
}

WP_CLI::success('Phase 2–3 page, shortcode and database contract are ready.');
