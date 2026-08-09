<?php

declare(strict_types=1);

namespace Zion\EuWithdrawal\Frontend;

use Zion\EuWithdrawal\Internationalization\LocaleManager;

final class CheckoutDisclosure
{
    private bool $rendered = false;

    public function __construct(
        private readonly PageManager $pages,
        private readonly LocaleManager $locale
    ) {
    }

    public function register_hooks(): void
    {
        add_action('woocommerce_before_checkout_form', [$this, 'before_form'], 12);
        add_action('woocommerce_review_order_before_submit', [$this, 'before_submit'], 12);
        add_action('woocommerce_after_order_notes', [$this, 'after_order_notes'], 12);
        add_action('woocommerce_thankyou', [$this, 'thankyou'], 12);
        add_action('woocommerce_email_order_meta', [$this, 'email_order_meta'], 20, 4);
    }

    public function before_form(): void
    {
        $this->render_if_position('before_form');
    }

    public function before_submit(): void
    {
        $this->render_if_position('before_submit');
    }

    public function after_order_notes(): void
    {
        $this->render_if_position('after_notes');
    }

    public function thankyou(int $order_id): void
    {
        $settings = (array) get_option('zion_eu_withdrawal_settings', []);
        if (empty($settings['order_confirmation_disclosure']) || $order_id < 1) {
            return;
        }

        echo '<div class="zion-eu-checkout-disclosure zion-eu-checkout-disclosure--confirmation"><strong>' . esc_html($this->locale->text('Dreptul de retragere', 'Right of withdrawal')) . '</strong><p>' . esc_html($this->locale->text('Poți transmite declarația online folosind pagina dedicată.', 'You can submit your statement online using the dedicated page.')) . ' <a href="' . esc_url($this->pages->public_url()) . '">' . esc_html($this->locale->text('Deschide pagina de retragere', 'Open withdrawal page')) . '</a></p></div>';
    }

    public function email_order_meta(mixed $order, bool $sent_to_admin, bool $plain_text, mixed $email): void
    {
        $settings = (array) get_option('zion_eu_withdrawal_settings', []);
        if ($sent_to_admin || empty($settings['order_confirmation_disclosure']) || ! is_object($order)) {
            return;
        }

        $url = $this->pages->public_url();
        if ($plain_text) {
            echo "\n" . $this->locale->text('Dreptul de retragere: ', 'Right of withdrawal: ') . $url . "\n";
            return;
        }

        echo '<p><strong>' . esc_html($this->locale->text('Dreptul de retragere', 'Right of withdrawal')) . '</strong><br>' . esc_html($this->locale->text('Poți folosi funcția online dedicată pentru a transmite o declarație neechivocă:', 'You can use the dedicated online function to submit an unambiguous statement:')) . ' <a href="' . esc_url($url) . '">' . esc_html($url) . '</a></p>';
    }

    private function render_if_position(string $position): void
    {
        $settings = (array) get_option('zion_eu_withdrawal_settings', []);
        if ($this->rendered || empty($settings['checkout_disclosure_enabled']) || (string) ($settings['checkout_disclosure_position'] ?? 'before_submit') !== $position) {
            return;
        }

        $this->rendered = true;
        $title = (string) ($settings['checkout_disclosure_title'] ?? 'Dreptul de retragere');
        $description = (string) ($settings['checkout_disclosure_text'] ?? 'Informații privind exercitarea dreptului de retragere și funcția online dedicată sunt disponibile aici.');
        echo '<div class="zion-eu-checkout-disclosure"><strong>' . esc_html($title) . '</strong><p>' . esc_html($description) . ' <a href="' . esc_url($this->pages->public_url()) . '">' . esc_html($this->locale->text('Vezi funcția online', 'View the online function')) . ' →</a></p></div>';
    }
}
