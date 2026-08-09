<?php

declare(strict_types=1);

namespace Zion\EuWithdrawal\Integration;

use Zion\EuWithdrawal\Frontend\PageManager;
use Zion\EuWithdrawal\Internationalization\LocaleManager;

if (interface_exists('Automattic\\WooCommerce\\Blocks\\Integrations\\IntegrationInterface')) {
    final class WooCommerceCheckoutBlocksIntegration implements \Automattic\WooCommerce\Blocks\Integrations\IntegrationInterface
    {
        public function __construct(
            private readonly LocaleManager $locale,
            private readonly PageManager $pages
        ) {
        }

        public function get_name()
        {
            return 'zion-eu-withdrawal';
        }

        public function initialize()
        {
            wp_register_script(
                'zion-eu-withdrawal-checkout-block',
                ZION_EU_WITHDRAWAL_URL . 'assets/checkout-block.js',
                ['wp-plugins', 'wp-element', 'wp-i18n', 'wc-settings', 'wc-blocks-checkout'],
                ZION_EU_WITHDRAWAL_VERSION,
                true
            );
            wp_enqueue_style('zion-eu-withdrawal-checkout-block', ZION_EU_WITHDRAWAL_URL . 'assets/public-phase.css', [], ZION_EU_WITHDRAWAL_VERSION);
        }

        public function get_script_handles()
        {
            return ['zion-eu-withdrawal-checkout-block'];
        }

        public function get_editor_script_handles()
        {
            return ['zion-eu-withdrawal-checkout-block'];
        }

        public function get_script_data()
        {
            $settings = (array) get_option('zion_eu_withdrawal_settings', []);

            return [
                'enabled' => ! empty($settings['checkout_disclosure_enabled']),
                'title' => (string) ($settings['checkout_disclosure_title'] ?? $this->locale->text('Dreptul de retragere', 'Right of withdrawal')),
                'text' => (string) ($settings['checkout_disclosure_text'] ?? $this->locale->text('Informatii privind exercitarea dreptului de retragere si functia online dedicata sunt disponibile aici.', 'Information about exercising the right of withdrawal and the dedicated online function is available here.')),
                'url' => $this->pages->public_url(),
                'linkLabel' => $this->locale->text('Vezi functia online', 'View the online function'),
            ];
        }
    }
}
