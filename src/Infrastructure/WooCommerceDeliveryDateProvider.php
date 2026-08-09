<?php

declare(strict_types=1);

namespace Zion\EuWithdrawal\Infrastructure;

final class WooCommerceDeliveryDateProvider implements DeliveryDateProvider
{
    public function id(): string
    {
        return 'woocommerce-order-meta';
    }

    public function get_delivery_date(mixed $order): ?\DateTimeImmutable
    {
        if (! is_object($order) || ! method_exists($order, 'get_meta')) {
            return null;
        }

        $settings = (array) get_option('zion_eu_withdrawal_settings', []);
        $key = sanitize_key((string) ($settings['delivery_date_meta_key'] ?? '_zion_delivery_date'));
        $keys = array_values(array_unique(array_filter([$key, '_zion_delivery_date', '_delivery_date', 'delivery_date'])));

        foreach ($keys as $meta_key) {
            $value = (string) $order->get_meta($meta_key, true);
            if ($value === '') {
                continue;
            }

            try {
                return new \DateTimeImmutable($value, new \DateTimeZone('UTC'));
            } catch (\Exception) {
                continue;
            }
        }

        return null;
    }
}
