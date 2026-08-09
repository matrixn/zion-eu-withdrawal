<?php

/** Run with: wp eval-file tests/smoke/phase610-runtime.php */

if (! defined('ABSPATH')) {
    exit("Run this file through WP-CLI.\n");
}

if (! class_exists('WooCommerce') || ! function_exists('wc_create_order')) {
    WP_CLI::error('WooCommerce runtime is not available.');
}

$product = new WC_Product_Simple();
$product->set_name('Zion QA withdrawal product');
$product->set_regular_price('10');
$product->set_status('publish');
$product->save();

$order = wc_create_order(['customer_id' => 1]);
$order->set_billing_first_name('Zion');
$order->set_billing_last_name('QA');
$order->set_billing_email('qa@example.test');
$order->add_product($product, 1);
$order->calculate_totals();
$order->save();

$lookup = new Zion\EuWithdrawal\Frontend\OrderLookup();
$items = $lookup->items($order);
$engine = new Zion\EuWithdrawal\Legal\EligibilityEngine(
    new Zion\EuWithdrawal\Legal\ROLegalProfile(),
    new Zion\EuWithdrawal\Infrastructure\WooCommerceDeliveryDateProvider()
);
$evaluation = $engine->evaluate_order($order, $items);

if (($evaluation['do_not_block'] ?? false) !== true || ($evaluation['overall'] ?? '') !== 'unknown') {
    WP_CLI::error('Unknown delivery date did not produce a non-blocking unknown evaluation: ' . (string) wp_json_encode($evaluation));
}

update_post_meta($product->get_id(), '_zion_eu_withdrawal_exception_code', 'EXC-C');
$exception_evaluation = $engine->evaluate_order($order, $items);
if (($exception_evaluation['items'][0]['exception_code'] ?? '') !== 'EXC-C') {
    WP_CLI::error('Product-level exception override was not evaluated.');
}

$settings = (array) get_option('zion_eu_withdrawal_settings', []);
$page = ! empty($settings['withdrawal_page_id']) ? get_post((int) $settings['withdrawal_page_id']) : null;
if (! $page || ! has_shortcode((string) $page->post_content, 'zion_eu_withdrawal') || ! shortcode_exists('zion_eu_withdrawal')) {
    WP_CLI::error('Withdrawal page and shortcode are not available.');
}

$checkout_output = '';
ob_start();
do_action('woocommerce_review_order_before_submit');
$checkout_output = (string) ob_get_clean();
if (strpos($checkout_output, 'zion-eu-checkout-disclosure') === false) {
    WP_CLI::error('Checkout disclosure did not render at the configured position.');
}

$account_menu = apply_filters('woocommerce_account_menu_items', ['customer-logout' => 'Log out']);
if (! array_key_exists('retrageri', $account_menu)) {
    WP_CLI::error('My Account withdrawal endpoint menu item is missing.');
}

if (! has_action('woocommerce_email_order_meta') || ! has_action('woocommerce_before_checkout_form') || ! has_action('woocommerce_blocks_checkout_block_registration')) {
    WP_CLI::error('Phase 8 WooCommerce hooks are not registered.');
}

$registry = new class {
    public array $integrations = [];
    public function register(mixed $integration): void { $this->integrations[] = $integration; }
};
do_action('woocommerce_blocks_checkout_block_registration', $registry);
if (count($registry->integrations) !== 1 || ! method_exists($registry->integrations[0], 'get_script_handles')) {
    WP_CLI::error('Checkout Blocks integration was not registered.');
}

WP_CLI::success('Phase 6-10 runtime contract passed for WooCommerce order, eligibility, checkout disclosure, shortcode and account endpoint.');
