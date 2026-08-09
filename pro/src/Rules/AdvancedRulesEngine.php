<?php

declare(strict_types=1);

namespace Zion\EuWithdrawalPro\Rules;

use Zion\EuWithdrawalPro\Infrastructure\ProSettings;

final class AdvancedRulesEngine
{
    public function __construct(private readonly ProSettings $settings)
    {
    }

    public function register_hooks(): void
    {
        add_filter('zion_eu_withdrawal_eligibility_evaluation', [$this, 'evaluate'], 20, 3);
    }

    /** @param array<string, mixed> $evaluation @param array<int, array<string, mixed>> $items @return array<string, mixed> */
    public function evaluate(array $evaluation, mixed $order, array $items): array
    {
        $rules = json_decode((string) $this->settings->get('advanced_rules', '[]'), true);
        if (! is_array($rules) || $rules === []) {
            return $evaluation;
        }

        foreach ($evaluation['items'] ?? [] as $index => $item) {
            foreach ($rules as $rule) {
                if (! is_array($rule) || ! $this->matches($rule['when'] ?? [], $item, $order)) {
                    continue;
                }

                $evaluation['items'][$index]['eligibility'] = in_array($rule['state'] ?? '', ['standard', 'potential_exception', 'unknown'], true) ? $rule['state'] : 'potential_exception';
                $evaluation['items'][$index]['exception_code'] = ($rule['exception_code'] ?? '') !== '' ? strtoupper((string) $rule['exception_code']) : null;
                $evaluation['items'][$index]['eligibility_reason'] = (string) ($rule['reason_ro'] ?? 'Regula Pro aplicata; verificarea comerciantului ramane necesara.');
                break;
            }
        }

        $states = array_column($evaluation['items'] ?? [], 'eligibility');
        $evaluation['overall'] = in_array('potential_exception', $states, true)
            ? 'potential_exception'
            : (in_array('unknown', $states, true) || empty($evaluation['delivery_date']) ? 'unknown' : 'standard');
        $evaluation['engine'] = 'EligibilityEngine+ProRules';
        $evaluation['do_not_block'] = true;
        return $evaluation;
    }

    /** @param mixed $when @param array<string, mixed> $item */
    private function matches(mixed $when, array $item, mixed $order): bool
    {
        if (! is_array($when) || $when === []) {
            return false;
        }

        $product = function_exists('wc_get_product') ? wc_get_product(absint($item['product_id'] ?? 0)) : null;
        foreach ($when as $condition => $expected) {
            $expected = strtolower(trim((string) $expected));
            if ($expected === '') {
                continue;
            }

            $actual = '';
            if ($condition === 'category' && $product) {
                $terms = wp_get_post_terms($product->get_id(), 'product_cat', ['fields' => 'slugs']);
                $slugs = is_array($terms) ? array_map('strtolower', $terms) : [];
                $actual = in_array($expected, $slugs, true) ? $expected : '';
            } elseif ($condition === 'tag' && $product) {
                $terms = wp_get_post_terms($product->get_id(), 'product_tag', ['fields' => 'slugs']);
                $slugs = is_array($terms) ? array_map('strtolower', $terms) : [];
                $actual = in_array($expected, $slugs, true) ? $expected : '';
            } elseif ($condition === 'product_type' && $product && method_exists($product, 'get_type')) {
                $actual = strtolower((string) $product->get_type());
            } elseif ($condition === 'product_id') {
                $actual = (string) absint($item['product_id'] ?? 0);
            } elseif ($condition === 'order_status' && is_object($order) && method_exists($order, 'get_status')) {
                $actual = strtolower((string) $order->get_status());
            } elseif ($condition === 'country' && is_object($order) && method_exists($order, 'get_billing_country')) {
                $actual = strtolower((string) $order->get_billing_country());
            } elseif ($condition === 'shipping_method' && is_object($order) && method_exists($order, 'get_shipping_method')) {
                $actual = strtolower((string) $order->get_shipping_method());
            }

            if ($actual === '' || ! str_contains($actual, $expected)) {
                return false;
            }
        }

        return true;
    }
}
