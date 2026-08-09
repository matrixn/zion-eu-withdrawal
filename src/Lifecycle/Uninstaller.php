<?php

declare(strict_types=1);

namespace Zion\EuWithdrawal\Lifecycle;

use Zion\EuWithdrawal\Infrastructure\Database;

final class Uninstaller
{
    public static function uninstall(): void
    {
        $settings = (array) get_option('zion_eu_withdrawal_settings', []);

        if ((int) ($settings['delete_data_on_uninstall'] ?? 0) !== 1) {
            return;
        }

        global $wpdb;
        $tables = (new Database())->table_names();

        foreach (array_reverse($tables) as $table) {
            $wpdb->query("DROP TABLE IF EXISTS `{$table}`"); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        }

        delete_option('zion_eu_withdrawal_settings');
        delete_option('zion_eu_withdrawal_db_version');
    }
}
