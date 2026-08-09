<?php
/**
 * Plugin Name: Zion EU Withdrawal Pro
 * Description: Pro add-on for Zion EU Withdrawal: visual controls, advanced rules, delivery integrations, dashboard, API and agency features.
 * Version: 0.1.0
 * Author: Zion3D
 * Author URI: https://www.zion3d.ro
 * License: GPL-2.0-or-later
 * Text Domain: zion-eu-withdrawal-pro
 */

declare(strict_types=1);

if (! defined('ABSPATH')) {
    exit;
}

define('ZION_EU_WITHDRAWAL_PRO_VERSION', '0.1.0');
define('ZION_EU_WITHDRAWAL_PRO_FILE', __FILE__);
define('ZION_EU_WITHDRAWAL_PRO_DIR', plugin_dir_path(__FILE__) . 'pro/');
define('ZION_EU_WITHDRAWAL_PRO_URL', plugin_dir_url(__FILE__) . 'pro/');

spl_autoload_register(static function (string $class): void {
    $prefix = 'Zion\\EuWithdrawalPro\\';
    if (strpos($class, $prefix) !== 0) {
        return;
    }

    $relative = str_replace('\\', '/', substr($class, strlen($prefix)));
    $file = ZION_EU_WITHDRAWAL_PRO_DIR . 'src/' . $relative . '.php';
    if (is_readable($file)) {
        require_once $file;
    }
});

require_once ZION_EU_WITHDRAWAL_PRO_DIR . 'src/ProPlugin.php';

register_activation_hook(__FILE__, ['Zion\\EuWithdrawalPro\\ProPlugin', 'activate']);
register_deactivation_hook(__FILE__, ['Zion\\EuWithdrawalPro\\ProPlugin', 'deactivate']);

add_action('plugins_loaded', static function (): void {
    \Zion\EuWithdrawalPro\ProPlugin::boot();
}, 20);
