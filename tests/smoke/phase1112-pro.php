<?php

/** Run with: wp eval-file tests/smoke/phase1112-pro.php --user=1 */

if (! defined('ABSPATH')) {
    exit("Run this file through WP-CLI.\n");
}

if (! class_exists('Zion\\EuWithdrawalPro\\ProPlugin') || ! defined('ZION_EU_WITHDRAWAL_PRO_VERSION')) {
    WP_CLI::error('The Pro add-on is not loaded.');
}

$settings = new Zion\EuWithdrawalPro\Infrastructure\ProSettings();
if (! is_array($settings->all()) || ! isset($settings->all()['advanced_rules'])) {
    WP_CLI::error('Pro settings defaults are not available.');
}

$checkout = apply_filters('zion_eu_withdrawal_checkout_settings', ['checkout_disclosure_title' => 'Core title']);
if (! in_array(($checkout['checkout_disclosure_title'] ?? ''), ['Dreptul de retragere', 'Right of withdrawal'], true)) {
    WP_CLI::error('Pro checkout settings filter did not provide the Romanian title.');
}

$position = apply_filters('zion_eu_withdrawal_checkout_position', 'before_submit');
if ($position !== 'before_submit') {
    WP_CLI::error('Pro checkout position changed unexpectedly.');
}

$routes = rest_get_server()->get_routes();
foreach (['/zion-eu-withdrawal/v1/health', '/zion-eu-withdrawal/v1/withdrawals', '/zion-eu-withdrawal/v1/delivery/(?P<order_id>\\d+)'] as $route) {
    if (! isset($routes[$route])) {
        WP_CLI::error('Missing Pro REST route: ' . $route);
    }
}

$evaluation = apply_filters('zion_eu_withdrawal_eligibility_evaluation', [
    'engine' => 'EligibilityEngine',
    'overall' => 'unknown',
    'do_not_block' => true,
    'items' => [],
], null, []);
if (($evaluation['do_not_block'] ?? false) !== true) {
    WP_CLI::error('Pro evaluation contract must never block submission.');
}

if (class_exists('WC_Product_Simple') && function_exists('wc_create_order')) {
    $term = term_exists('personalizate', 'product_cat');
    if (! $term) {
        $term = wp_insert_term('Personalizate', 'product_cat', ['slug' => 'personalizate']);
    }
    $term_id = is_array($term) ? (int) ($term['term_id'] ?? 0) : (int) $term;
    $product = new WC_Product_Simple();
    $product->set_name('Zion Pro rules QA product');
    $product->set_regular_price('9');
    $product->set_status('publish');
    $product->save();
    if ($term_id > 0) {
        wp_set_object_terms($product->get_id(), [$term_id], 'product_cat');
    }
    $order = wc_create_order();
    $order->add_product($product, 1);
    $order->calculate_totals();
    $items = (new Zion\EuWithdrawal\Frontend\OrderLookup())->items($order);
    $rule_evaluation = (new Zion\EuWithdrawal\Legal\EligibilityEngine(
        new Zion\EuWithdrawal\Legal\ROLegalProfile(),
        new Zion\EuWithdrawal\Infrastructure\WooCommerceDeliveryDateProvider()
    ))->evaluate_order($order, $items);
    if (($rule_evaluation['items'][0]['exception_code'] ?? '') !== 'EXC-C' || ($rule_evaluation['overall'] ?? '') !== 'potential_exception') {
        WP_CLI::error('The Pro category rule did not classify the QA product.');
    }
}

WP_CLI::success('Phase 11-12 Pro foundation, settings, filters and REST routes are ready.');
