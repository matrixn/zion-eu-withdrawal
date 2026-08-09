<?php

declare(strict_types=1);

namespace Zion\EuWithdrawalPro\Frontend;

use Zion\EuWithdrawal\Internationalization\LocaleManager;
use Zion\EuWithdrawalPro\Infrastructure\ProSettings;

final class VisualCustomization
{
    public function __construct(private readonly ProSettings $settings)
    {
    }

    public function register_hooks(): void
    {
        add_action('wp_enqueue_scripts', [$this, 'enqueue_assets']);
        add_action('wp_footer', [$this, 'render_button'], 30);
        add_filter('zion_eu_withdrawal_checkout_settings', [$this, 'checkout_settings'], 20);
        add_filter('zion_eu_withdrawal_checkout_position', [$this, 'checkout_position'], 20);
    }

    public function enqueue_assets(): void
    {
        if (! $this->settings->get('visual_enabled', 0)) {
            return;
        }

        wp_enqueue_style('zion-eu-withdrawal-pro-public', ZION_EU_WITHDRAWAL_PRO_URL . 'assets/pro-public.css', [], ZION_EU_WITHDRAWAL_PRO_VERSION);
        $background = sanitize_hex_color((string) $this->settings->get('button_background', '#f97316')) ?: '#f97316';
        $text = sanitize_hex_color((string) $this->settings->get('button_text_color', '#ffffff')) ?: '#ffffff';
        $radius = max(0, min(40, (int) $this->settings->get('button_radius', 14)));
        wp_add_inline_style('zion-eu-withdrawal-pro-public', ':root{--zion-pro-button-bg:' . esc_html($background) . ';--zion-pro-button-text:' . esc_html($text) . ';--zion-pro-button-radius:' . $radius . 'px}' . wp_strip_all_tags((string) $this->settings->get('custom_css', '')));
    }

    public function render_button(): void
    {
        if (is_admin() || ! $this->settings->get('visual_enabled', 0) || ! function_exists('get_option')) {
            return;
        }

        $core_settings = (array) get_option('zion_eu_withdrawal_settings', []);
        $page_id = absint($core_settings['withdrawal_page_id'] ?? 0);
        $url = $page_id > 0 ? get_permalink($page_id) : home_url('/retragere-contract/');
        $locale = new LocaleManager();
        $label = $locale->language() === 'en'
            ? (string) $this->settings->get('button_label_en', 'Online withdrawal')
            : (string) $this->settings->get('button_label_ro', 'Retragere online');
        $position = sanitize_key((string) $this->settings->get('floating_position', 'bottom-right'));
        $brand = $this->settings->get('white_label', 0) ? '' : ' <small>Zion3D Pro</small>';

        echo '<a class="zion-eu-pro-floating-button zion-eu-pro-floating-button--' . esc_attr($position) . '" href="' . esc_url($url) . '" aria-label="' . esc_attr($label) . '"><span>' . esc_html($label) . '</span>' . $brand . '</a>';
    }

    /** @param mixed $settings @return array<string, mixed> */
    public function checkout_settings(mixed $settings): array
    {
        $settings = is_array($settings) ? $settings : [];
        if (! $this->settings->get('customize_checkout', 0)) {
            return $settings;
        }

        $locale = new LocaleManager();
        $english = $locale->language() === 'en';
        $settings['checkout_disclosure_title'] = (string) $this->settings->get($english ? 'checkout_title_en' : 'checkout_title_ro', $settings['checkout_disclosure_title'] ?? 'Dreptul de retragere');
        $settings['checkout_disclosure_text'] = (string) $this->settings->get($english ? 'checkout_text_en' : 'checkout_text_ro', $settings['checkout_disclosure_text'] ?? '');
        return $settings;
    }

    public function checkout_position(string $position): string
    {
        return $this->settings->get('customize_checkout', 0)
            ? (string) $this->settings->get('checkout_position', $position)
            : $position;
    }
}
