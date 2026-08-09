<?php

declare(strict_types=1);

namespace Zion\EuWithdrawal\Legal;

use Zion\EuWithdrawal\Infrastructure\DeliveryDateProvider;

final class EligibilityEngine
{
    public function __construct(
        private readonly LegalProfile $profile,
        private readonly DeliveryDateProvider $delivery_dates
    ) {
    }

    /** @param array<int, array<string, mixed>> $items @return array<string, mixed> */
    public function evaluate_order(mixed $order, array $items): array
    {
        $settings = (array) get_option('zion_eu_withdrawal_settings', []);
        $delivery = $this->delivery_dates->get_delivery_date($order);
        $start = $delivery;
        $period = max(1, (int) ($settings['withdrawal_period_days'] ?? $this->profile->standard_period_days()));
        $deadline = $start?->modify('+' . $period . ' days');
        $evaluated = [];

        foreach ($items as $item) {
            $rule = $this->rule_for_item($item, $settings);
            $classification = $this->classify_item($rule);
            $evaluated[] = array_merge($item, $classification, [
                'delivery_date' => $delivery?->format('Y-m-d H:i:s'),
                'period_start' => $start?->format('Y-m-d H:i:s'),
                'estimated_deadline' => $deadline?->format('Y-m-d H:i:s'),
            ]);
        }

        $states = array_column($evaluated, 'eligibility');
        $overall = in_array('potential_exception', $states, true)
            ? 'potential_exception'
            : (in_array('unknown', $states, true) || $delivery === null ? 'unknown' : 'standard');

        return apply_filters('zion_eu_withdrawal_eligibility_evaluation', [
            'engine' => 'EligibilityEngine',
            'provider' => $this->delivery_dates->id(),
            'legal_profile' => $this->profile->version(),
            'overall' => $overall,
            'delivery_date' => $delivery?->format('Y-m-d H:i:s'),
            'period_start' => $start?->format('Y-m-d H:i:s'),
            'estimated_deadline' => $deadline?->format('Y-m-d H:i:s'),
            'do_not_block' => true,
            'items' => $evaluated,
        ], $order, $items);
    }

    /** @param array<string, mixed> $rule @return array<string, string|null> */
    public function classify_item(array $rule): array
    {
        $state = (string) ($rule['state'] ?? 'unknown');
        if (! in_array($state, ['standard', 'potential_exception', 'unknown'], true)) {
            $state = 'unknown';
        }

        $code = (string) ($rule['exception_code'] ?? '');
        $exceptions = $this->profile->exceptions();
        if ($state === 'potential_exception' && ! isset($exceptions[$code])) {
            $code = '';
        }

        $reason = (string) ($rule['reason'] ?? '');
        if ($reason === '' && $code !== '' && isset($exceptions[$code])) {
            $reason = (string) ($exceptions[$code]['ro'] ?? '');
        }
        if ($reason === '') {
            $reason = $state === 'standard'
                ? 'Regula standard; nu a fost identificat automat un motiv de excludere.'
                : 'Informațiile disponibile nu permit o concluzie automată; verificarea comerciantului rămâne necesară.';
        }

        return [
            'eligibility' => $state,
            'exception_code' => $code !== '' ? $code : null,
            'eligibility_reason' => $reason,
        ];
    }

    /** @param array<string, mixed> $item @param array<string, mixed> $settings @return array<string, string> */
    private function rule_for_item(array $item, array $settings): array
    {
        $default = (string) ($settings['default_eligibility_rule'] ?? 'standard');
        $rule = ['state' => $default, 'exception_code' => '', 'reason' => ''];
        $product = function_exists('wc_get_product') ? wc_get_product((int) ($item['product_id'] ?? 0)) : null;

        if ($product && method_exists($product, 'get_meta')) {
            $product_rule = (string) $product->get_meta('_zion_eu_withdrawal_rule', true);
            $product_code = strtoupper((string) $product->get_meta('_zion_eu_withdrawal_exception_code', true));
            if ($product_rule !== '') {
                $rule['state'] = $product_rule;
            }
            if ($product_code !== '') {
                $rule['exception_code'] = $product_code;
                $rule['state'] = 'potential_exception';
            }

            if ($product_rule === '' && method_exists($product, 'is_downloadable') && $product->is_downloadable()) {
                $rule = ['state' => 'potential_exception', 'exception_code' => 'EXC-M', 'reason' => 'Conținutul descărcabil poate necesita verificarea condițiilor de consimțământ și executare.'];
            } elseif ($product_rule === '' && method_exists($product, 'is_virtual') && $product->is_virtual()) {
                $rule = ['state' => 'potential_exception', 'exception_code' => 'EXC-A', 'reason' => 'Serviciile virtuale pot necesita verificarea stadiului executării și a consimțământului.'];
            }

            if ($rule['state'] === $default && function_exists('wp_get_post_terms')) {
                $terms = wp_get_post_terms((int) $product->get_id(), 'product_cat');
                if (is_wp_error($terms) || ! is_array($terms)) {
                    $terms = [];
                }
                foreach ($terms as $term) {
                    $category_rule = (string) get_term_meta((int) $term->term_id, '_zion_eu_withdrawal_rule', true);
                    $category_code = strtoupper((string) get_term_meta((int) $term->term_id, '_zion_eu_withdrawal_exception_code', true));
                    if ($category_rule !== '') {
                        $rule['state'] = $category_rule;
                    }
                    if ($category_code !== '') {
                        $rule['exception_code'] = $category_code;
                        $rule['state'] = 'potential_exception';
                    }
                    if ($category_rule !== '' || $category_code !== '') {
                        break;
                    }
                }
            }
        }

        return $rule;
    }
}
