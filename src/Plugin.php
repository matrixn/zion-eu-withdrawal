<?php

declare(strict_types=1);

namespace Zion\EuWithdrawal;

use Zion\EuWithdrawal\Admin\SettingsPage;
use Zion\EuWithdrawal\Infrastructure\Database;
use Zion\EuWithdrawal\Integration\WooCommerceDetector;
use Zion\EuWithdrawal\Internationalization\LocaleManager;
use Zion\EuWithdrawal\Legal\ROLegalProfile;

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
        add_action('admin_init', [$this, 'maybe_upgrade']);

        if (is_admin()) {
            $settings = new SettingsPage(new Database(), new ROLegalProfile(), new WooCommerceDetector(), new LocaleManager());
            add_action('admin_menu', [$settings, 'register_menu']);
            add_action('admin_enqueue_scripts', [$settings, 'enqueue_assets']);
            add_action('wp_ajax_zion_eu_save_setting', [$settings, 'ajax_save_setting']);
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
        if (! current_user_can('manage_options')) {
            return;
        }

        $database = new Database();
        $database->maybe_upgrade();
    }
}
