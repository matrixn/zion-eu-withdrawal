<?php

declare(strict_types=1);

namespace Zion\EuWithdrawal\Infrastructure;

final class Database
{
    public const DB_VERSION = '0.1.0';

    public function maybe_upgrade(): void
    {
        $installed = (string) get_option('zion_eu_withdrawal_db_version', '');

        if ($installed !== self::DB_VERSION) {
            $this->create_tables();
            update_option('zion_eu_withdrawal_db_version', self::DB_VERSION, false);
        }
    }

    public function create_tables(): void
    {
        global $wpdb;

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        $charset = $wpdb->get_charset_collate();
        $withdrawals = $wpdb->prefix . 'zion_eu_withdrawals';
        $items = $wpdb->prefix . 'zion_eu_withdrawal_items';

        dbDelta("CREATE TABLE {$withdrawals} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            withdrawal_id varchar(40) NOT NULL,
            order_id bigint(20) unsigned NULL,
            user_id bigint(20) unsigned NULL,
            customer_email varchar(190) NOT NULL,
            customer_name varchar(190) NOT NULL DEFAULT '',
            status varchar(32) NOT NULL DEFAULT 'submitted',
            legal_profile varchar(64) NOT NULL,
            legal_profile_version varchar(64) NOT NULL,
            statement_content longtext NOT NULL,
            source varchar(32) NOT NULL DEFAULT 'public',
            start_date datetime NULL,
            deadline_date datetime NULL,
            confirmation_token_hash varchar(255) NULL,
            merchant_notes longtext NULL,
            created_at datetime NOT NULL,
            updated_at datetime NOT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY withdrawal_id (withdrawal_id),
            KEY order_id (order_id),
            KEY customer_email (customer_email),
            KEY status (status),
            KEY created_at (created_at)
        ) {$charset};");

        dbDelta("CREATE TABLE {$items} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            withdrawal_id bigint(20) unsigned NOT NULL,
            order_item_id bigint(20) unsigned NULL,
            product_id bigint(20) unsigned NULL,
            quantity decimal(20,6) NOT NULL DEFAULT 1,
            eligibility varchar(32) NOT NULL DEFAULT 'standard',
            exception_code varchar(16) NULL,
            created_at datetime NOT NULL,
            PRIMARY KEY  (id),
            KEY withdrawal_id (withdrawal_id),
            KEY product_id (product_id)
        ) {$charset};");
    }

    /** @return array<string, string> */
    public function table_names(): array
    {
        global $wpdb;

        return [
            'withdrawals' => $wpdb->prefix . 'zion_eu_withdrawals',
            'items' => $wpdb->prefix . 'zion_eu_withdrawal_items',
        ];
    }
}
