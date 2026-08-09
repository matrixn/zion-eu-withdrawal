<?php

declare(strict_types=1);

namespace Zion\EuWithdrawalPro\Delivery;

use Zion\EuWithdrawalPro\Infrastructure\ProSettings;

final class DeliveryIntegration
{
    public function __construct(private readonly ProSettings $settings)
    {
    }

    public function register_hooks(): void
    {
        add_filter('zion_eu_withdrawal_delivery_date_meta_key', [$this, 'meta_key']);
        add_filter('zion_eu_withdrawal_delivery_date', [$this, 'status_fallback'], 20, 2);
    }

    public function meta_key(string $key): string
    {
        return (string) $this->settings->get('delivery_meta_key', $key);
    }

    public function status_fallback(?\DateTimeImmutable $date, mixed $order): ?\DateTimeImmutable
    {
        if ($date !== null || $this->settings->get('delivery_provider', 'order_meta') !== 'status_fallback' || ! is_object($order)) {
            return $date;
        }

        $status = method_exists($order, 'get_status') ? (string) $order->get_status() : '';
        $delivered = sanitize_key((string) $this->settings->get('delivered_status', 'completed'));
        if ($status !== $delivered || ! method_exists($order, 'get_date_completed')) {
            return null;
        }

        $completed = $order->get_date_completed();
        return $completed instanceof \WC_DateTime
            ? new \DateTimeImmutable($completed->date('Y-m-d H:i:s'), new \DateTimeZone('UTC'))
            : null;
    }
}
