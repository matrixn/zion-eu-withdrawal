<?php

declare(strict_types=1);

namespace Zion\EuWithdrawalPro;

use Zion\EuWithdrawal\Infrastructure\Database;
use Zion\EuWithdrawal\Infrastructure\WithdrawalRepository;
use Zion\EuWithdrawalPro\Admin\ProAdminPage;
use Zion\EuWithdrawalPro\Api\RestApi;
use Zion\EuWithdrawalPro\Delivery\DeliveryIntegration;
use Zion\EuWithdrawalPro\Infrastructure\ProSettings;
use Zion\EuWithdrawalPro\Rules\AdvancedRulesEngine;
use Zion\EuWithdrawalPro\Webhooks\WebhookDispatcher;
use Zion\EuWithdrawalPro\Frontend\VisualCustomization;

final class ProPlugin
{
    private static ?self $instance = null;

    private function __construct()
    {
        $settings = new ProSettings();
        $repository = new WithdrawalRepository(new Database());
        (new AdvancedRulesEngine($settings))->register_hooks();
        (new DeliveryIntegration($settings))->register_hooks();
        (new VisualCustomization($settings))->register_hooks();
        (new WebhookDispatcher($settings))->register_hooks();
        (new RestApi($settings, $repository))->register_hooks();

        if (is_admin()) {
            (new ProAdminPage($settings, $repository))->register_hooks();
        }
    }

    public static function boot(): ?self
    {
        if (! class_exists('Zion\\EuWithdrawal\\Plugin')) {
            return null;
        }

        return self::$instance ??= new self();
    }

    public static function activate(): void
    {
        if (! current_user_can('activate_plugins')) {
            return;
        }

        if (! class_exists('Zion\\EuWithdrawal\\Plugin')) {
            deactivate_plugins(plugin_basename(ZION_EU_WITHDRAWAL_PRO_FILE));
            wp_die(esc_html__('Zion EU Withdrawal Pro necesita pluginul core Zion EU Withdrawal activ.', 'zion-eu-withdrawal-pro'));
        }

        add_option(ProSettings::OPTION, ProSettings::defaults(), '', false);
    }

    public static function deactivate(): void
    {
        wp_clear_scheduled_hook('zion_eu_withdrawal_pro_deadline_scan');
    }
}
