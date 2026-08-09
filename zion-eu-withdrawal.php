<?php
/**
 * Plugin Name: Zion EU Withdrawal
 * Plugin URI: https://www.zion3d.ro
 * Description: Fundație profesională pentru gestionarea dreptului de retragere în magazine WooCommerce, cu profil juridic românesc.
 * Version: 1.0.2
 * Author: Zion3D
 * Author URI: https://www.zion3d.ro
 * License: GPL-2.0-or-later
 * Text Domain: zion-eu-withdrawal
 * Domain Path: /languages
 * Requires at least: 6.4
 * Requires PHP: 8.1
 * Requires Plugins: woocommerce
 */

declare(strict_types=1);

if (! defined('ABSPATH')) {
    exit;
}

define('ZION_EU_WITHDRAWAL_VERSION', '1.0.2');
define('ZION_EU_WITHDRAWAL_FILE', __FILE__);
define('ZION_EU_WITHDRAWAL_DIR', plugin_dir_path(__FILE__));
define('ZION_EU_WITHDRAWAL_URL', plugin_dir_url(__FILE__));
define('ZION_EU_WITHDRAWAL_TEXT_DOMAIN', 'zion-eu-withdrawal');

add_action('before_woocommerce_init', static function (): void {
    if (class_exists(\Automattic\WooCommerce\Utilities\FeaturesUtil::class)) {
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('cart_checkout_blocks', __FILE__, true);
    }
});

spl_autoload_register(static function (string $class): void {
    $prefix = 'Zion\\EuWithdrawal\\';

    if (strpos($class, $prefix) !== 0) {
        return;
    }

    $relative = str_replace('\\', '/', substr($class, strlen($prefix)));
    $file = ZION_EU_WITHDRAWAL_DIR . 'src/' . $relative . '.php';

    if (is_readable($file)) {
        require_once $file;
    }
});

require_once ZION_EU_WITHDRAWAL_DIR . 'src/Lifecycle/Activator.php';
require_once ZION_EU_WITHDRAWAL_DIR . 'src/Lifecycle/Deactivator.php';
require_once ZION_EU_WITHDRAWAL_DIR . 'src/Plugin.php';

register_activation_hook(__FILE__, ['Zion\\EuWithdrawal\\Lifecycle\\Activator', 'activate']);
register_deactivation_hook(__FILE__, ['Zion\\EuWithdrawal\\Lifecycle\\Deactivator', 'deactivate']);

\Zion\EuWithdrawal\Plugin::boot();
