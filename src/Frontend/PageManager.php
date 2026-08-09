<?php

declare(strict_types=1);

namespace Zion\EuWithdrawal\Frontend;

use Zion\EuWithdrawal\Internationalization\LocaleManager;
use Zion\EuWithdrawal\Legal\LegalProfile;

final class PageManager
{
    public function __construct(
        private readonly LocaleManager $locale,
        private readonly LegalProfile $profile
    ) {
    }

    public function register_hooks(): void
    {
        add_action('init', [$this, 'maybe_create_page'], 20);
        add_action('init', [$this, 'register_block']);
        add_shortcode('zion_eu_withdrawal', [$this, 'shortcode']);
        add_action('wp_enqueue_scripts', [$this, 'enqueue_assets']);
        add_action('woocommerce_view_order', [$this, 'render_order_button'], 30);
        add_action('woocommerce_order_details_after_order_table', [$this, 'render_order_button'], 30);
    }

    public function maybe_create_page(): void
    {
        $settings = (array) get_option('zion_eu_withdrawal_settings', []);
        if (empty($settings['auto_create_page']) || ! empty($settings['withdrawal_page_id'])) {
            return;
        }

        $slug = sanitize_title((string) ($settings['withdrawal_page_slug'] ?? 'retragere-contract'));
        $existing = get_page_by_path($slug, OBJECT, 'page');
        $page_id = $existing ? (int) $existing->ID : (int) wp_insert_post([
            'post_title' => $this->locale->text('Retragere din contract', 'Withdrawal from contract'),
            'post_name' => $slug,
            'post_content' => '<!-- wp:shortcode -->[zion_eu_withdrawal]<!-- /wp:shortcode -->',
            'post_status' => 'publish',
            'post_type' => 'page',
        ], true);

        if ($page_id > 0 && ! is_wp_error($page_id)) {
            $settings['withdrawal_page_id'] = $page_id;
            update_option('zion_eu_withdrawal_settings', $settings, false);
        }
    }

    public function register_block(): void
    {
        if (function_exists('register_block_type')) {
            register_block_type('zion-eu/withdrawal', [
                'api_version' => 3,
                'title' => $this->locale->text('Retragere UE', 'EU Withdrawal'),
                'description' => $this->locale->text('Formularul public Zion pentru retragerea din contract.', 'Zion public contract withdrawal form.'),
                'category' => 'widgets',
                'icon' => 'undo',
                'render_callback' => [$this, 'shortcode'],
                'supports' => ['html' => false],
            ]);
        }
    }

    public function enqueue_assets(): void
    {
        if (! is_singular() || ! $this->page_contains_shortcode()) {
            return;
        }

        wp_enqueue_style('zion-eu-withdrawal-public', ZION_EU_WITHDRAWAL_URL . 'assets/public.css', [], ZION_EU_WITHDRAWAL_VERSION);
        wp_enqueue_script('zion-eu-withdrawal-public', ZION_EU_WITHDRAWAL_URL . 'assets/public.js', [], ZION_EU_WITHDRAWAL_VERSION, true);
        wp_localize_script('zion-eu-withdrawal-public', 'ZionEuWithdrawalPublic', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('zion_eu_public_withdrawal'),
            'strings' => $this->strings(),
        ]);
    }

    /** @return string */
    public function shortcode(): string
    {
        $settings = (array) get_option('zion_eu_withdrawal_settings', []);
        $prefill = '';
        $order_id = absint(wp_unslash($_GET['order_id'] ?? 0));
        $token = sanitize_text_field(wp_unslash($_GET['zion_token'] ?? ''));

        if ($order_id > 0 && $token !== '' && wp_verify_nonce($token, 'zion_eu_order_' . $order_id)) {
            $prefill = (string) $order_id;
        }

        ob_start();
        ?>
        <section class="zion-eu-withdrawal-app" data-zion-withdrawal-app data-prefill-order="<?php echo esc_attr($prefill); ?>" aria-labelledby="zion-eu-withdrawal-title">
            <div class="zion-eu-withdrawal-brand"><span class="zion-eu-withdrawal-mark">Z</span><span><strong><?php echo esc_html($this->locale->text('Retragere UE', 'EU Withdrawal')); ?></strong><small><?php echo esc_html($this->profile->version()); ?></small></span></div>
            <div class="zion-eu-withdrawal-progress" aria-label="<?php echo esc_attr($this->locale->text('Pașii retragerii', 'Withdrawal steps')); ?>"><span class="is-active" data-progress="1">01</span><i></i><span data-progress="2">02</span><i></i><span data-progress="3">03</span></div>
            <header class="zion-eu-withdrawal-intro"><span class="zion-eu-withdrawal-eyebrow"><?php echo esc_html($this->locale->text('Dreptul tău, transmis clar', 'Your right, clearly submitted')); ?></span><h1 id="zion-eu-withdrawal-title"><?php echo esc_html($this->locale->text('Retragere din contract', 'Withdrawal from a contract')); ?></h1><p><?php echo esc_html($this->locale->text('Folosește acest formular pentru a transmite comerciantului o declarație neechivocă de retragere. Nu trebuie să explici motivul.', 'Use this form to send the merchant an unambiguous withdrawal statement. You do not need to give a reason.')); ?></p></header>
            <div class="zion-eu-withdrawal-legal-note"><span>i</span><p><?php echo esc_html($this->locale->text('Termenul standard este de 14 zile, cu regulile de început și excepțiile prevăzute în profilul juridic RO. Trimiterea nu este o cerere care necesită aprobare.', 'The standard period is 14 days, subject to the start-date rules and exceptions in the RO legal profile. Submission is not a request requiring approval.')); ?></p></div>
            <div class="zion-eu-withdrawal-step is-active" data-step="1"><div class="zion-eu-step-heading"><span>01</span><div><h2><?php echo esc_html($this->locale->text('Identifică persoana și contractul', 'Identify the person and contract')); ?></h2><p><?php echo esc_html($this->locale->text('Folosim aceste date doar pentru a găsi în siguranță contractul indicat.', 'We use these details only to securely locate the indicated contract.')); ?></p></div></div><form data-zion-form="identify"><div class="zion-eu-form-grid"><label><span><?php echo esc_html($this->locale->text('Nume complet', 'Full name')); ?> *</span><small><?php echo esc_html($this->locale->text('Numele consumatorului din contract.', 'Consumer name on the contract.')); ?></small><input name="customer_name" required autocomplete="name"></label><label><span><?php echo esc_html($this->locale->text('E-mail', 'E-mail')); ?> *</span><small><?php echo esc_html($this->locale->text('Adresa asociată comenzii.', 'Address associated with the order.')); ?></small><input name="customer_email" type="email" required autocomplete="email"></label><label><span><?php echo esc_html($this->locale->text('Telefon', 'Phone')); ?></span><small><?php echo esc_html($this->locale->text('Opțional, pentru referință.', 'Optional, for reference.')); ?></small><input name="customer_phone" type="tel" autocomplete="tel"></label><label><span><?php echo esc_html($this->locale->text('Număr comandă / contract', 'Order / contract number')); ?> *</span><small><?php echo esc_html($this->locale->text('Nu folosi datele cardului sau parola.', 'Do not use card details or a password.')); ?></small><input name="order_reference" required inputmode="numeric"></label></div><button class="zion-eu-withdrawal-button" type="submit"><?php echo esc_html($this->locale->text('Verifică și continuă', 'Verify and continue')); ?><span>→</span></button><div class="zion-eu-withdrawal-feedback" data-feedback="identify" role="alert" aria-live="polite"></div></form></div>
            <div class="zion-eu-withdrawal-step" data-step="2" hidden><div class="zion-eu-step-heading"><span>02</span><div><h2><?php echo esc_html($this->locale->text('Formulează declarația', 'Write the statement')); ?></h2><p><?php echo esc_html($this->locale->text('Motivul este opțional. Declarația de retragere este ceea ce contează.', 'A reason is optional. The withdrawal statement is what matters.')); ?></p></div></div><div class="zion-eu-order-summary" data-order-summary></div><form data-zion-form="statement"><label class="zion-eu-full-field"><span><?php echo esc_html($this->locale->text('Observații pentru comerciant', 'Notes for the merchant')); ?></span><small><?php echo esc_html($this->locale->text('Opțional. Nu este necesar să justifici decizia.', 'Optional. You do not need to justify the decision.')); ?></small><textarea name="statement" rows="5" maxlength="2000"></textarea></label><button class="zion-eu-withdrawal-button" type="submit"><?php echo esc_html($this->locale->text('Pregătește revizuirea', 'Prepare review')); ?><span>→</span></button><div class="zion-eu-withdrawal-feedback" data-feedback="statement" role="alert" aria-live="polite"></div></form></div>
            <div class="zion-eu-withdrawal-step" data-step="3" hidden><div class="zion-eu-step-heading"><span>03</span><div><h2><?php echo esc_html($this->locale->text('Revizuiește și confirmă', 'Review and confirm')); ?></h2><p><?php echo esc_html($this->locale->text('Verifică exact ce vom salva ca dovadă, apoi confirmă transmiterea.', 'Check exactly what we will save as evidence, then confirm submission.')); ?></p></div></div><div class="zion-eu-review-card" data-review-card></div><form data-zion-form="confirm"><label class="zion-eu-confirmation"><input name="confirmation" type="checkbox" value="1" required><span><?php echo esc_html($this->locale->text('Confirm că această declarație este transmisă de mine și că datele afișate sunt corecte.', 'I confirm that this statement is submitted by me and that the displayed details are correct.')); ?></span></label><button class="zion-eu-withdrawal-button zion-eu-withdrawal-button--confirm" type="submit"><?php echo esc_html($this->locale->text('CONFIRMĂ RETRAGEREA', 'CONFIRM WITHDRAWAL')); ?><span>✓</span></button><div class="zion-eu-withdrawal-feedback" data-feedback="confirm" role="alert" aria-live="polite"></div></form></div>
            <div class="zion-eu-withdrawal-success" data-step="success" hidden><span class="zion-eu-success-mark">✓</span><span class="zion-eu-withdrawal-eyebrow"><?php echo esc_html($this->locale->text('Declarație transmisă', 'Statement submitted')); ?></span><h2><?php echo esc_html($this->locale->text('Am păstrat dovada transmiterii.', 'We kept proof of your submission.')); ?></h2><p><?php echo esc_html($this->locale->text('Comerciantul poate folosi ID-ul de mai jos pentru a identifica declarația. Păstrează-l pentru referință.', 'The merchant can use the ID below to identify the statement. Keep it for your records.')); ?></p><strong class="zion-eu-success-id" data-withdrawal-id></strong><div class="zion-eu-success-meta" data-success-meta></div></div>
            <footer class="zion-eu-withdrawal-footer"><span><?php echo esc_html($this->locale->text('Profil juridic activ:', 'Active legal profile:')); ?> <strong><?php echo esc_html($this->profile->version()); ?></strong></span><span><?php echo esc_html($this->locale->text('Datele sunt transmise securizat și salvate cu timestamp server-side.', 'Data is submitted securely and saved with a server-side timestamp.')); ?></span></footer>
        </section>
        <?php
        return (string) ob_get_clean();
    }

    public function render_order_button(mixed $order_or_id): void
    {
        $order_id = is_object($order_or_id) && method_exists($order_or_id, 'get_id')
            ? (int) $order_or_id->get_id()
            : absint($order_or_id);
        $order = function_exists('wc_get_order') ? wc_get_order($order_id) : null;
        if (! $order || ! is_user_logged_in() || (int) $order->get_user_id() !== get_current_user_id()) {
            return;
        }

        $url = add_query_arg(['order_id' => $order_id, 'zion_token' => wp_create_nonce('zion_eu_order_' . $order_id)], $this->page_url());
        echo '<p class="zion-eu-store-button"><a href="' . esc_url($url) . '">' . esc_html($this->locale->text('Retragere din contract', 'Withdraw from contract')) . ' <span>→</span></a></p>';
    }

    private function page_contains_shortcode(): bool
    {
        global $post;
        return $post instanceof \WP_Post && (has_shortcode((string) $post->post_content, 'zion_eu_withdrawal') || has_block('zion-eu/withdrawal', $post));
    }

    private function page_url(): string
    {
        $settings = (array) get_option('zion_eu_withdrawal_settings', []);
        $url = ! empty($settings['withdrawal_page_id']) ? get_permalink((int) $settings['withdrawal_page_id']) : home_url('/' . sanitize_title((string) ($settings['withdrawal_page_slug'] ?? 'retragere-contract')) . '/');
        return is_string($url) ? $url : home_url('/');
    }

    /** @return array<string, string> */
    private function strings(): array
    {
        return [
            'loading' => $this->locale->text('Se verifică…', 'Checking…'),
            'saving' => $this->locale->text('Se pregătește…', 'Preparing…'),
            'confirming' => $this->locale->text('Se transmite și se salvează…', 'Submitting and saving…'),
            'genericError' => $this->locale->text('Nu am putut procesa cererea. Verifică datele și încearcă din nou.', 'We could not process the request. Check the details and try again.'),
            'orderNotFound' => $this->locale->text('Nu am putut confirma combinația de date. Verifică numărul comenzii și e-mailul.', 'We could not confirm this combination of details. Check the order number and e-mail.'),
            'orderLabel' => $this->locale->text('Comandă / contract', 'Order / contract'),
            'declarationLabel' => $this->locale->text('Declarație neechivocă', 'Unambiguous declaration'),
            'declaration' => $this->locale->text('Comunic decizia mea neechivocă de a mă retrage din contractul identificat mai sus.', 'I communicate my unambiguous decision to withdraw from the contract identified above.'),
            'noNotes' => $this->locale->text('Fără observații suplimentare.', 'No additional notes.'),
            'serverProof' => $this->locale->text('Timestamp server-side va fi salvat după confirmare.', 'A server-side timestamp will be saved after confirmation.'),
            'contractIdentified' => $this->locale->text('Contract identificat', 'Contract identified'),
            'submittedAt' => $this->locale->text('Transmis la (UTC)', 'Submitted at (UTC)'),
        ];
    }
}
