<?php

declare(strict_types=1);

namespace Zion\EuWithdrawal\Lifecycle;

use Zion\EuWithdrawal\Infrastructure\Database;

final class Activator
{
    public static function activate(): void
    {
        if (! current_user_can('activate_plugins')) {
            return;
        }

        $database = new Database();
        $database->create_tables();
        update_option('zion_eu_withdrawal_db_version', Database::DB_VERSION, false);
        add_option('zion_eu_withdrawal_settings', self::defaults(), '', false);
        add_rewrite_endpoint('retrageri', EP_ROOT | EP_PAGES);
        flush_rewrite_rules();
    }

    /** @return array<string, mixed> */
    public static function defaults(): array
    {
        return [
            'legal_profile' => 'RO-2026.06.19-v1',
            'withdrawal_period_days' => 14,
            'extended_period_days' => 30,
            'return_period_days' => 14,
            'language' => 'site',
            'withdrawal_page_slug' => 'retragere-contract',
            'withdrawal_page_id' => 0,
            'auto_create_page' => 1,
            'guest_withdrawal' => 1,
            'allow_partial_withdrawal' => 0,
            'rate_limit_per_hour' => 5,
            'no_cache_sensitive_pages' => 1,
            'captcha_mode' => 'none',
            'guest_link_ttl_minutes' => 30,
            'send_consumer_email' => 1,
            'send_admin_email' => 1,
            'admin_email' => '',
            'guest_email_subject' => 'Link securizat pentru retragerea din contract',
            'consumer_email_subject' => 'Confirmare retragere din contract - {withdrawal_id}',
            'admin_email_subject' => 'Retragere noua primita - {withdrawal_id}',
            'admin_recipients' => '',
            'notification_log_retention_days' => 365,
            'default_eligibility_rule' => 'standard',
            'unknown_delivery_behavior' => 'record_unknown',
            'delivery_date_meta_key' => '_zion_delivery_date',
            'checkout_disclosure_enabled' => 1,
            'checkout_disclosure_position' => 'before_submit',
            'checkout_disclosure_title' => 'Dreptul de retragere',
            'checkout_disclosure_text' => 'Informatii privind exercitarea dreptului de retragere si functia online dedicata sunt disponibile aici.',
            'order_confirmation_disclosure' => 1,
            'accent_color' => '#f97316',
            'delete_data_on_uninstall' => 0,
        ];
    }
}
