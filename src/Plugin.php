<?php

declare(strict_types=1);

namespace Zion\EuWithdrawal;

use Zion\EuWithdrawal\Admin\SettingsPage;
use Zion\EuWithdrawal\Admin\GuidePage;
use Zion\EuWithdrawal\Admin\WithdrawalsPage;
use Zion\EuWithdrawal\Admin\NotificationsPage;
use Zion\EuWithdrawal\Infrastructure\Database;
use Zion\EuWithdrawal\Infrastructure\AuditRepository;
use Zion\EuWithdrawal\Infrastructure\GuestTokenRepository;
use Zion\EuWithdrawal\Infrastructure\NotificationService;
use Zion\EuWithdrawal\Infrastructure\WithdrawalRepository;
use Zion\EuWithdrawal\Infrastructure\WooCommerceDeliveryDateProvider;
use Zion\EuWithdrawal\Frontend\OrderLookup;
use Zion\EuWithdrawal\Frontend\AccountArea;
use Zion\EuWithdrawal\Frontend\CheckoutDisclosure;
use Zion\EuWithdrawal\Frontend\PageManager;
use Zion\EuWithdrawal\Frontend\WithdrawalFlow;
use Zion\EuWithdrawal\Integration\WooCommerceDetector;
use Zion\EuWithdrawal\Internationalization\LocaleManager;
use Zion\EuWithdrawal\Legal\ROLegalProfile;
use Zion\EuWithdrawal\Legal\EligibilityEngine;
use Zion\EuWithdrawal\Integration\WooCommerceRules;

final class Plugin
{
    private static ?self $instance = null;

    private function __construct()
    {
        $this->register_hooks();
    }

    public static function boot(): self
    {
        return self::$instance ??= new self();
    }

    private function register_hooks(): void
    {
        add_action('plugins_loaded', [$this, 'load_textdomain']);
        add_action('init', [$this, 'maybe_upgrade'], 1);

        $database = new Database();
        $profile = new ROLegalProfile();
        $locale = new LocaleManager();
        $repository = new WithdrawalRepository($database);
        $guest_tokens = new GuestTokenRepository($database);
        $audit = new AuditRepository($database);
        $notifications = new NotificationService($database, $audit, $locale);
        $page_manager = new PageManager($locale, $profile, $guest_tokens);
        $page_manager->register_hooks();

        $eligibility = new EligibilityEngine($profile, new WooCommerceDeliveryDateProvider());
        $flow = new WithdrawalFlow(new OrderLookup(), $repository, $guest_tokens, $page_manager, $profile, $locale, $eligibility, $notifications);
        $flow->register_hooks();
        (new AccountArea($repository, $locale))->register_hooks();
        (new CheckoutDisclosure($page_manager, $locale))->register_hooks();
        (new WooCommerceRules($locale, $profile))->register_hooks();

        if (is_admin()) {
            $settings = new SettingsPage($database, $profile, new WooCommerceDetector(), $locale, $repository);
            add_action('admin_menu', [$settings, 'register_menu']);
            add_action('admin_enqueue_scripts', [$settings, 'enqueue_assets']);
            add_action('wp_ajax_zion_eu_save_setting', [$settings, 'ajax_save_setting']);
            $withdrawals_page = new WithdrawalsPage($repository, $locale, $notifications, $audit);
            add_action('admin_menu', [$withdrawals_page, 'register_menu']);
            add_action('wp_ajax_zion_eu_update_withdrawal', [$withdrawals_page, 'ajax_update_withdrawal']);
            add_action('wp_ajax_zion_eu_resend_notification', [$withdrawals_page, 'ajax_resend_notification']);
            add_action('admin_post_zion_eu_export_withdrawals', [$withdrawals_page, 'export_csv']);
            add_action('admin_menu', [new NotificationsPage($notifications, $repository, $locale), 'register_menu']);
            add_action('admin_menu', [new GuidePage($locale, $profile), 'register_menu']);
        }
    }

    public function load_textdomain(): void
    {
        load_plugin_textdomain(
            ZION_EU_WITHDRAWAL_TEXT_DOMAIN,
            false,
            dirname(plugin_basename(ZION_EU_WITHDRAWAL_FILE)) . '/languages'
        );
    }

    public function maybe_upgrade(): void
    {
        $database = new Database();
        $database->maybe_upgrade();
    }
}
