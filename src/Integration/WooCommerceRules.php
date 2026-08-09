<?php

declare(strict_types=1);

namespace Zion\EuWithdrawal\Integration;

use Zion\EuWithdrawal\Internationalization\LocaleManager;
use Zion\EuWithdrawal\Legal\LegalProfile;

final class WooCommerceRules
{
    public function __construct(
        private readonly LocaleManager $locale,
        private readonly LegalProfile $profile
    ) {
    }

    public function register_hooks(): void
    {
        add_action('woocommerce_product_options_general_product_data', [$this, 'product_fields']);
        add_action('woocommerce_process_product_meta', [$this, 'save_product_fields']);
        add_action('product_cat_add_form_fields', [$this, 'category_add_fields']);
        add_action('product_cat_edit_form_fields', [$this, 'category_edit_fields']);
        add_action('created_product_cat', [$this, 'save_category_fields']);
        add_action('edited_product_cat', [$this, 'save_category_fields']);
    }

    public function product_fields(): void
    {
        if (! function_exists('woocommerce_wp_select')) {
            return;
        }

        echo '<div class="options_group"><p class="form-field"><strong>' . esc_html($this->locale->text('Eligibilitate retragere', 'Withdrawal eligibility')) . '</strong><br><span class="description">' . esc_html($this->locale->text('Alege o clasificare orientativă pentru motorul de eligibilitate. Pluginul nu blochează automat retragerea.', 'Choose an indicative classification for the eligibility engine. The plugin never blocks a withdrawal automatically.')) . '</span></p>';
        woocommerce_wp_select([
            'id' => '_zion_eu_withdrawal_rule',
            'label' => $this->locale->text('Regulă implicită', 'Default rule'),
            'description' => $this->locale->text('Standard, posibilă excepție sau necunoscut. Suprascrie regula categoriei.', 'Standard, potential exception or unknown. Overrides the category rule.'),
            'desc_tip' => true,
            'options' => [
                '' => $this->locale->text('Folosește categoria / setarea implicită', 'Use category / default setting'),
                'standard' => $this->locale->text('Regulă standard', 'Standard rule'),
                'potential_exception' => $this->locale->text('Posibilă excepție legală', 'Potential statutory exception'),
                'unknown' => $this->locale->text('Necunoscut - verificare manuală', 'Unknown - manual review'),
            ],
        ]);
        woocommerce_wp_select([
            'id' => '_zion_eu_withdrawal_exception_code',
            'label' => $this->locale->text('Motiv art. 16', 'Article 16 reason'),
            'description' => $this->locale->text('Este un motiv candidat pentru verificare, nu o decizie juridică automată.', 'This is a candidate reason for review, not an automatic legal decision.'),
            'desc_tip' => true,
            'options' => array_merge(['' => $this->locale->text('Fără motiv specific', 'No specific reason')], array_map(fn (array $label): string => $label['ro'], $this->profile->exceptions())),
        ]);
        echo '</div>';
    }

    public function save_product_fields(int $product_id): void
    {
        if (! current_user_can('edit_post', $product_id)) {
            return;
        }

        update_post_meta($product_id, '_zion_eu_withdrawal_rule', sanitize_key((string) wp_unslash($_POST['_zion_eu_withdrawal_rule'] ?? '')));
        update_post_meta($product_id, '_zion_eu_withdrawal_exception_code', strtoupper(sanitize_key((string) wp_unslash($_POST['_zion_eu_withdrawal_exception_code'] ?? ''))));
    }

    public function category_add_fields(): void
    {
        $this->category_fields();
    }

    public function category_edit_fields(\WP_Term $term): void
    {
        $this->category_fields($term);
    }

    public function save_category_fields(int $term_id): void
    {
        if (! current_user_can('manage_product_terms')) {
            return;
        }

        update_term_meta($term_id, '_zion_eu_withdrawal_rule', sanitize_key((string) wp_unslash($_POST['_zion_eu_withdrawal_rule'] ?? '')));
        update_term_meta($term_id, '_zion_eu_withdrawal_exception_code', strtoupper(sanitize_key((string) wp_unslash($_POST['_zion_eu_withdrawal_exception_code'] ?? ''))));
    }

    private function category_fields(?\WP_Term $term = null): void
    {
        $rule = $term ? (string) get_term_meta($term->term_id, '_zion_eu_withdrawal_rule', true) : '';
        $code = $term ? (string) get_term_meta($term->term_id, '_zion_eu_withdrawal_exception_code', true) : '';
        $options = ['' => $this->locale->text('Folosește setarea implicită', 'Use default setting'), 'standard' => $this->locale->text('Regulă standard', 'Standard rule'), 'potential_exception' => $this->locale->text('Posibilă excepție legală', 'Potential statutory exception'), 'unknown' => $this->locale->text('Necunoscut - verificare manuală', 'Unknown - manual review')];
        echo '<div class="form-field"><label for="zion-eu-category-rule">' . esc_html($this->locale->text('Regulă retragere', 'Withdrawal rule')) . '</label><select id="zion-eu-category-rule" name="_zion_eu_withdrawal_rule">';
        foreach ($options as $key => $label) {
            echo '<option value="' . esc_attr($key) . '" ' . selected($rule, $key, false) . '>' . esc_html($label) . '</option>';
        }
        echo '</select><p class="description">' . esc_html($this->locale->text('Regula se aplică produselor din categorie dacă produsul nu are o suprascriere.', 'Applied to products in this category when the product has no override.')) . '</p></div><div class="form-field"><label for="zion-eu-category-exception">' . esc_html($this->locale->text('Motiv art. 16', 'Article 16 reason')) . '</label><select id="zion-eu-category-exception" name="_zion_eu_withdrawal_exception_code"><option value="">' . esc_html($this->locale->text('Fără motiv specific', 'No specific reason')) . '</option>';
        foreach ($this->profile->exceptions() as $key => $label) {
            echo '<option value="' . esc_attr($key) . '" ' . selected($code, $key, false) . '>' . esc_html($key . ' - ' . $label['ro']) . '</option>';
        }
        echo '</select><p class="description">' . esc_html($this->locale->text('Folosește doar ca semnal de verificare, nu ca refuz automat.', 'Use only as a review signal, never as an automatic refusal.')) . '</p></div>';
    }
}
