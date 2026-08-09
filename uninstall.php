<?php

declare(strict_types=1);

if (! defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

require_once __DIR__ . '/src/Lifecycle/Uninstaller.php';
\Zion\EuWithdrawal\Lifecycle\Uninstaller::uninstall();
