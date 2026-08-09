<?php

declare(strict_types=1);

namespace Zion\EuWithdrawal\Frontend;

final class OrderLookup
{
    /**
     * Returnează o comandă doar când există o dovadă de asociere suficientă.
     * Mesajul public rămâne generic pentru a evita enumerarea comenzilor.
     */
    public function find(string $reference, string $email, ?int $user_id = null): mixed
    {
        if (! function_exists('wc_get_order')) {
            return null;
        }

        $order_id = absint(preg_replace('/[^0-9]/', '', $reference));
        if ($order_id < 1) {
            return null;
        }

        $order = wc_get_order($order_id);
        if (! $order || ! is_object($order)) {
            return null;
        }

        $email_matches = strtolower((string) $order->get_billing_email()) === strtolower($email);
        $owner_matches = $user_id !== null && $user_id > 0 && (int) $order->get_user_id() === $user_id;

        return ($email_matches || $owner_matches) ? $order : null;
    }

    /** @return array<int, array<string, mixed>> */
    public function items(mixed $order): array
    {
        $items = [];

        if (! is_object($order) || ! method_exists($order, 'get_items')) {
            return $items;
        }

        foreach ($order->get_items() as $item_id => $item) {
            $items[] = [
                'order_item_id' => (int) $item_id,
                'product_id' => method_exists($item, 'get_product_id') ? (int) $item->get_product_id() : 0,
                'quantity' => method_exists($item, 'get_quantity') ? (float) $item->get_quantity() : 1,
                'name' => method_exists($item, 'get_name') ? (string) $item->get_name() : '',
            ];
        }

        return $items;
    }
}
