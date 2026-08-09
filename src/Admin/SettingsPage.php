<?php

declare(strict_types=1);

namespace Zion\EuWithdrawal\Admin;

use Zion\EuWithdrawal\Integration\WooCommerceDetector;
use Zion\EuWithdrawal\Internationalization\LocaleManager;
use Zion\EuWithdrawal\Legal\LegalProfile;

final class SettingsPage
{
    private const OPTION = 'zion_eu_withdrawal_settings';

    public function __construct(
        private readonly object $database,
        private readonly LegalProfile $profile,
        private readonly WooCommerceDetector $woocommerce,
        private readonly LocaleManager $locale
    ) {
    }

    public function register_menu(): void
    {
        $capability = 'manage_options';
        $title = $this->locale->text('Retragere UE', 'EU Withdrawal');

        add_menu_page(
            $title,
            $title,
            $capability,
            'zion-eu-withdrawal',
            [$this, 'render_presentation'],
            'dashicons-undo',
            58
        );

        add_submenu_page('zion-eu-withdrawal', $this->locale->text('Prezentare', 'Overview'), $this->locale->text('Prezentare', 'Overview'), $capability, 'zion-eu-withdrawal', [$this, 'render_presentation']);
        add_submenu_page('zion-eu-withdrawal', $this->locale->text('Setări', 'Settings'), $this->locale->text('Setări', 'Settings'), $capability, 'zion-eu-withdrawal-settings', [$this, 'render_settings']);
    }

    public function enqueue_assets(string $hook): void
    {
        if (! str_contains($hook, 'zion-eu-withdrawal')) {
            return;
        }

        wp_enqueue_style('zion-eu-withdrawal-admin', ZION_EU_WITHDRAWAL_URL . 'assets/admin.css', [], ZION_EU_WITHDRAWAL_VERSION);
        wp_enqueue_script('zion-eu-withdrawal-admin', ZION_EU_WITHDRAWAL_URL . 'assets/admin.js', ['jquery'], ZION_EU_WITHDRAWAL_VERSION, true);
        wp_localize_script('zion-eu-withdrawal-admin', 'ZionEuWithdrawalAdmin', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('zion_eu_withdrawal_settings'),
            'saving' => $this->locale->text('Se salvează…', 'Saving…'),
            'saved' => $this->locale->text('Salvat automat', 'Saved automatically'),
            'error' => $this->locale->text('Nu s-a putut salva. Verifică permisiunile și încearcă din nou.', 'Could not save. Check permissions and try again.'),
        ]);
    }

    public function ajax_save_setting(): void
    {
        check_ajax_referer('zion_eu_withdrawal_settings', 'nonce');

        if (! current_user_can('manage_options')) {
            wp_send_json_error(['message' => $this->locale->text('Nu ai permisiunea necesară.', 'You do not have the required permission.')], 403);
        }

        $key = sanitize_key((string) wp_unslash($_POST['key'] ?? ''));
        $schema = $this->schema();

        if (! isset($schema[$key])) {
            wp_send_json_error(['message' => $this->locale->text('Setarea nu este recunoscută.', 'This setting is not recognised.')], 400);
        }

        $settings = $this->settings();
        $field = $schema[$key];
        $raw = wp_unslash($_POST['value'] ?? '');
        $settings[$key] = $this->sanitize_value($raw, $field);
        update_option(self::OPTION, $settings, false);

        wp_send_json_success([
            'key' => $key,
            'value' => $settings[$key],
            'message' => $this->locale->text('Salvat automat', 'Saved automatically'),
        ]);
    }

    public function render_presentation(): void
    {
        $this->render_header('overview');
        $lang = $this->locale->language();
        ?>
        <div class="zion-eu-grid zion-eu-grid--overview">
            <section class="zion-eu-hero zion-eu-card">
                <div class="zion-eu-eyebrow">ZION3D / WITHDRAWAL FOUNDATION 0.1</div>
                <h1><?php echo esc_html($this->locale->text('Retrageri clare. Încredere mai mare.', 'Clear withdrawals. Greater trust.')); ?></h1>
                <p><?php echo esc_html($this->locale->text('O fundație WooCommerce construită în jurul unei experiențe calme pentru client și a unei evidențe juridice verificabile pentru comerciant.', 'A WooCommerce foundation built around a calm customer experience and verifiable legal evidence for the merchant.')); ?></p>
                <div class="zion-eu-hero-actions">
                    <a class="zion-eu-button zion-eu-button--primary" href="<?php echo esc_url(admin_url('admin.php?page=zion-eu-withdrawal-settings')); ?>"><?php echo esc_html($this->locale->text('Configurează fundația', 'Configure the foundation')); ?></a>
                    <span class="zion-eu-status-dot"><span></span><?php echo esc_html($this->locale->text('Autosave AJAX activ', 'AJAX autosave active')); ?></span>
                </div>
            </section>

            <section class="zion-eu-card zion-eu-card--accent">
                <div class="zion-eu-card-heading"><span class="zion-eu-icon">◈</span><h2><?php echo esc_html($this->locale->text('Stare integrare', 'Integration status')); ?></h2></div>
                <div class="zion-eu-integration-state <?php echo $this->woocommerce->is_available() ? 'is-ready' : 'is-waiting'; ?>">
                    <span class="zion-eu-state-mark"><?php echo $this->woocommerce->is_available() ? '✓' : '!'; ?></span>
                    <div><strong><?php echo esc_html($this->woocommerce->is_available() ? $this->locale->text('WooCommerce este pregătit', 'WooCommerce is ready') : $this->locale->text('Așteaptă WooCommerce', 'Waiting for WooCommerce')); ?></strong><p><?php echo esc_html($this->woocommerce->message($lang)); ?></p></div>
                </div>
                <div class="zion-eu-mini-meta"><span><?php echo esc_html($this->locale->text('Profil juridic', 'Legal profile')); ?></span><strong><?php echo esc_html($this->profile->version()); ?></strong></div>
            </section>
        </div>

        <div class="zion-eu-section-title"><div><span class="zion-eu-eyebrow"><?php echo esc_html($this->locale->text('Impact măsurabil', 'Measurable impact')); ?></span><h2><?php echo esc_html($this->locale->text('Un start proiectat pentru operațiuni reale', 'A start designed for real operations')); ?></h2></div></div>
        <div class="zion-eu-stats">
            <?php foreach ($this->overview_stats() as $stat) : ?>
                <div class="zion-eu-stat zion-eu-card"><span class="zion-eu-stat-value"><?php echo esc_html($stat['value']); ?></span><strong><?php echo esc_html($stat['label']); ?></strong><p><?php echo esc_html($stat['description']); ?></p></div>
            <?php endforeach; ?>
        </div>

        <div class="zion-eu-grid zion-eu-grid--lower">
            <section class="zion-eu-card">
                <div class="zion-eu-card-heading"><span class="zion-eu-icon">✦</span><h2><?php echo esc_html($this->locale->text('Ce aduce pluginul', 'What the plugin brings')); ?></h2></div>
                <div class="zion-eu-feature-list">
                    <?php foreach ($this->features() as $feature) : ?><div class="zion-eu-feature"><span>✓</span><div><strong><?php echo esc_html($feature['title']); ?></strong><p><?php echo esc_html($feature['description']); ?></p></div></div><?php endforeach; ?>
                </div>
            </section>
            <section class="zion-eu-card zion-eu-card--support">
                <div class="zion-eu-card-heading"><span class="zion-eu-icon">↗</span><h2><?php echo esc_html($this->locale->text('Ai nevoie de ajutor?', 'Need a hand?')); ?></h2></div>
                <p><?php echo esc_html($this->locale->text('Găsești informații, suport și contact direct în cardul nostru de suport.', 'Find information, support and direct contact in our support card.')); ?></p>
                <div class="zion-eu-support-links"><a href="https://www.zion3d.ro" target="_blank" rel="noopener">zion3d.ro <span>↗</span></a><a href="https://support.zion3d.ro" target="_blank" rel="noopener">support.zion3d.ro <span>↗</span></a><a href="mailto:contact@zion3d.ro">contact@zion3d.ro <span>↗</span></a></div>
                <div class="zion-eu-support-note"><?php echo esc_html($this->locale->text('Răspundem cu soluții documentate, nu cu presupuneri.', 'We respond with documented solutions, not guesses.')); ?></div>
            </section>
        </div>
        <?php
        $this->render_footer();
    }

    public function render_settings(): void
    {
        $this->render_header('settings');
        ?>
        <section class="zion-eu-settings-intro zion-eu-card">
            <div><span class="zion-eu-eyebrow"><?php echo esc_html($this->locale->text('Centrul de control', 'Control centre')); ?></span><h1><?php echo esc_html($this->locale->text('Setări care se salvează singure', 'Settings that save themselves')); ?></h1><p><?php echo esc_html($this->locale->text('Modifică o opțiune și vei primi confirmarea lângă ea. Nu există un buton global de salvare care să te lase să ghicești dacă ai pierdut ceva.', 'Change an option and you will receive confirmation next to it. There is no global save button leaving you to wonder whether something was lost.')); ?></p></div>
            <div class="zion-eu-autosave-badge"><span>●</span><?php echo esc_html($this->locale->text('Salvare automată', 'Automatic saving')); ?><small>AJAX</small></div>
        </section>
        <div class="zion-eu-settings-layout">
            <div class="zion-eu-settings-main">
                <?php foreach ($this->sections() as $section_key => $section) : ?>
                    <section class="zion-eu-card zion-eu-settings-section"><div class="zion-eu-section-head"><div><span class="zion-eu-section-number"><?php echo esc_html($section['number']); ?></span><h2><?php echo esc_html($this->locale->text($section['ro'], $section['en'])); ?></h2><p><?php echo esc_html($this->locale->text($section['description_ro'], $section['description_en'])); ?></p></div></div><div class="zion-eu-fields">
                        <?php foreach ($this->schema() as $key => $field) { if ($field['section'] === $section_key) { $this->render_field($key, $field); } } ?>
                    </div></section>
                <?php endforeach; ?>
            </div>
            <aside class="zion-eu-settings-aside"><div class="zion-eu-card zion-eu-side-card"><span class="zion-eu-eyebrow"><?php echo esc_html($this->locale->text('Principiu juridic', 'Legal principle')); ?></span><h2><?php echo esc_html($this->locale->text('Nu blocăm clientul când informația este incompletă.', 'Do not block the customer when information is incomplete.')); ?></h2><p><?php echo esc_html($this->locale->text('Fundația documentează starea și lasă decizia comerciantului acolo unde o excepție depinde de fapte ulterioare livrării.', 'The foundation records the state and leaves the merchant decision where an exception depends on facts after delivery.')); ?></p></div><div class="zion-eu-card zion-eu-side-card zion-eu-side-card--dark"><strong><?php echo esc_html($this->locale->text('Profil activ', 'Active profile')); ?></strong><span><?php echo esc_html($this->profile->version()); ?></span><p><?php echo esc_html($this->locale->text('13 motive EXC-A – EXC-M sunt pregătite pentru motorul de eligibilitate din fazele următoare.', '13 EXC-A – EXC-M reasons are ready for the eligibility engine in later phases.')); ?></p></div></aside>
        </div>
        <?php
        $this->render_footer();
    }

    /** @return array<string, mixed> */
    private function settings(): array
    {
        return array_merge(\Zion\EuWithdrawal\Lifecycle\Activator::defaults(), (array) get_option(self::OPTION, []));
    }

    /** @return array<string, array<string, mixed>> */
    private function schema(): array
    {
        return [
            'legal_profile' => ['section' => 'legal', 'type' => 'text', 'readonly' => true, 'ro' => 'Profil juridic activ', 'en' => 'Active legal profile', 'description_ro' => 'Versiunea de reguli juridice folosită pentru această instalare. Este read-only pentru a păstra trasabilitatea documentelor.', 'description_en' => 'The legal ruleset version used by this installation. Read-only to preserve document traceability.'],
            'withdrawal_period_days' => ['section' => 'legal', 'type' => 'number', 'min' => 1, 'max' => 365, 'ro' => 'Termen standard de retragere (zile)', 'en' => 'Standard withdrawal period (days)', 'description_ro' => 'Numărul de zile pentru dreptul standard de retragere, calculat din data de început prevăzută în profilul juridic.', 'description_en' => 'Number of days for the standard withdrawal right, calculated from the start date defined by the legal profile.'],
            'extended_period_days' => ['section' => 'legal', 'type' => 'number', 'min' => 1, 'max' => 365, 'ro' => 'Termen extins pentru situații speciale (zile)', 'en' => 'Extended period for special situations (days)', 'description_ro' => 'Valoarea de rezervă pentru situațiile în care legislația prevede un termen extins; nu modifică automat clasificarea contractului.', 'description_en' => 'Fallback value for situations where legislation provides an extended period; it does not automatically classify the contract.'],
            'return_period_days' => ['section' => 'legal', 'type' => 'number', 'min' => 1, 'max' => 365, 'ro' => 'Termen pentru expedierea bunurilor după retragere (zile)', 'en' => 'Return shipping period after withdrawal (days)', 'description_ro' => 'Termenul operațional afișat comerciantului și clientului pentru expedierea bunurilor după transmiterea retragerii.', 'description_en' => 'Operational deadline shown to the merchant and customer for shipping goods after withdrawal is communicated.'],
            'language' => ['section' => 'experience', 'type' => 'select', 'options' => ['site' => ['ro' => 'Limba site-ului', 'en' => 'Site language'], 'ro' => ['ro' => 'Română', 'en' => 'Romanian'], 'en' => ['ro' => 'Engleză', 'en' => 'English']], 'ro' => 'Limba interfeței', 'en' => 'Interface language', 'description_ro' => 'Alege limba pluginului. „Limba site-ului” folosește limba WordPress; sunt pregătite româna și engleza.', 'description_en' => 'Choose the plugin language. “Site language” follows WordPress; Romanian and English are supported.'],
            'withdrawal_page_slug' => ['section' => 'experience', 'type' => 'text', 'ro' => 'Slug pagină pentru retragere', 'en' => 'Withdrawal page slug', 'description_ro' => 'Slug-ul paginii publice pe care o vom folosi în Phase 2 pentru fluxul de retragere.', 'description_en' => 'The public page slug to be used by the withdrawal flow in Phase 2.'],
            'auto_create_page' => ['section' => 'experience', 'type' => 'checkbox', 'ro' => 'Pregătește automat pagina de retragere', 'en' => 'Prepare the withdrawal page automatically', 'description_ro' => 'Păstrează activă intenția de a crea pagina publică și shortcode-ul în Phase 2, fără să modifice conținutul acum.', 'description_en' => 'Keeps the intent to create the public page and shortcode in Phase 2, without changing content now.'],
            'guest_withdrawal' => ['section' => 'experience', 'type' => 'checkbox', 'ro' => 'Permite fluxul pentru clienți fără cont', 'en' => 'Allow guest customer flow', 'description_ro' => 'Pregătește identificarea securizată pentru clienții care au comandat fără cont, fără expunerea datelor comenzii.', 'description_en' => 'Prepares secure identification for customers who ordered as guests, without exposing order data.'],
            'allow_partial_withdrawal' => ['section' => 'experience', 'type' => 'checkbox', 'ro' => 'Permite cererea de retragere parțială', 'en' => 'Allow partial withdrawal requests', 'description_ro' => 'Permite activarea ulterioară a selectării liniilor de comandă; decizia nu este tratată ca o excludere juridică automată.', 'description_en' => 'Enables the future line-item selection flow; it is not treated as an automatic legal exclusion.'],
            'rate_limit_per_hour' => ['section' => 'security', 'type' => 'number', 'min' => 1, 'max' => 100, 'ro' => 'Limită cereri per e-mail / oră', 'en' => 'Requests per e-mail / hour', 'description_ro' => 'Limită de protecție împotriva enumerării comenzilor și spamului pentru fluxurile publice.', 'description_en' => 'Protection limit against order enumeration and spam in public flows.'],
            'no_cache_sensitive_pages' => ['section' => 'security', 'type' => 'checkbox', 'ro' => 'Marchează paginile sensibile ca no-cache', 'en' => 'Mark sensitive pages as no-cache', 'description_ro' => 'Solicită excluderea din cache pentru paginile care vor conține date despre retrageri.', 'description_en' => 'Requests cache exclusion for pages that will contain withdrawal data.'],
            'captcha_mode' => ['section' => 'security', 'type' => 'select', 'options' => ['none' => ['ro' => 'Dezactivat', 'en' => 'Disabled'], 'native' => ['ro' => 'Pregătit pentru CAPTCHA', 'en' => 'CAPTCHA-ready']], 'ro' => 'Protecție anti-spam', 'en' => 'Anti-spam protection', 'description_ro' => 'Controlează integrarea anti-spam. Providerul CAPTCHA nu este inclus în Phase 1, pentru a evita o dependență externă inutilă.', 'description_en' => 'Controls anti-spam integration. A CAPTCHA provider is not bundled in Phase 1 to avoid an unnecessary external dependency.'],
            'send_consumer_email' => ['section' => 'notifications', 'type' => 'checkbox', 'ro' => 'Confirmare e-mail către client', 'en' => 'Consumer confirmation e-mail', 'description_ro' => 'Pregătește trimiterea unei dovezi către client după transmiterea retragerii, cu snapshot-ul conținutului.', 'description_en' => 'Prepares a customer proof message after submission, with a content snapshot.'],
            'send_admin_email' => ['section' => 'notifications', 'type' => 'checkbox', 'ro' => 'Notificare e-mail către comerciant', 'en' => 'Merchant notification e-mail', 'description_ro' => 'Pregătește notificarea internă cu ID-ul retragerii, data, ora și comanda identificată.', 'description_en' => 'Prepares an internal notification with withdrawal ID, date, time and identified order.'],
            'admin_email' => ['section' => 'notifications', 'type' => 'email', 'ro' => 'E-mail administrator pentru notificări', 'en' => 'Administrator e-mail for notifications', 'description_ro' => 'Adresa destinatarului intern. Dacă este goală, Phase 3 va folosi adresa de administrare WordPress.', 'description_en' => 'Internal recipient address. If empty, Phase 3 will use the WordPress administration address.'],
            'accent_color' => ['section' => 'appearance', 'type' => 'color', 'ro' => 'Culoare accent portocaliu', 'en' => 'Orange accent colour', 'description_ro' => 'Culoarea folosită pentru acțiuni, indicatori de stare și elementele de orientare vizuală.', 'description_en' => 'Colour used for actions, status indicators and visual orientation elements.'],
            'delete_data_on_uninstall' => ['section' => 'appearance', 'type' => 'checkbox', 'ro' => 'Șterge datele la dezinstalare', 'en' => 'Delete data on uninstall', 'description_ro' => 'Implicit dezactivat. Activează doar dacă vrei ca tabelele și setările să fie șterse la dezinstalarea pluginului.', 'description_en' => 'Disabled by default. Enable only if tables and settings should be removed during uninstall.'],
        ];
    }

    /** @return array<string, array<string, string>> */
    private function sections(): array
    {
        return [
            'legal' => ['number' => '01', 'ro' => 'Profil juridic', 'en' => 'Legal profile', 'description_ro' => 'Parametri expliciți, versionați și ușor de verificat.', 'description_en' => 'Explicit, versioned and verifiable parameters.'],
            'experience' => ['number' => '02', 'ro' => 'Experiență și flux', 'en' => 'Experience and flow', 'description_ro' => 'Baza pentru un parcurs clar, accesibil și pregătit pentru Phase 2.', 'description_en' => 'The basis for a clear, accessible journey ready for Phase 2.'],
            'security' => ['number' => '03', 'ro' => 'Securitate și confidențialitate', 'en' => 'Security and privacy', 'description_ro' => 'Protecții implicite pentru fluxuri publice și date sensibile.', 'description_en' => 'Default safeguards for public flows and sensitive data.'],
            'notifications' => ['number' => '04', 'ro' => 'Notificări', 'en' => 'Notifications', 'description_ro' => 'Preferințe pentru dovezi și notificări, implementate în fazele de flux.', 'description_en' => 'Proof and notification preferences implemented in the flow phases.'],
            'appearance' => ['number' => '05', 'ro' => 'Aspect și date', 'en' => 'Appearance and data', 'description_ro' => 'Identitate vizuală și control explicit asupra păstrării datelor.', 'description_en' => 'Visual identity and explicit data-retention control.'],
        ];
    }

    /** @param array<string, mixed> $field */
    private function render_field(string $key, array $field): void
    {
        $value = $this->settings()[$key] ?? '';
        $label = $this->locale->text($field['ro'], $field['en']);
        $description = $this->locale->text($field['description_ro'], $field['description_en']);
        $type = $field['type'];
        $field_id = 'zion-eu-setting-' . $key;
        ?>
        <div class="zion-eu-field zion-eu-field--<?php echo esc_attr($type); ?>" data-setting-row="<?php echo esc_attr($key); ?>">
            <div class="zion-eu-field-copy"><label for="<?php echo esc_attr($field_id); ?>"><?php echo esc_html($label); ?></label><p><?php echo esc_html($description); ?></p></div>
            <div class="zion-eu-field-control">
                <?php if ($type === 'checkbox') : ?><label class="zion-eu-switch"><input id="<?php echo esc_attr($field_id); ?>" type="checkbox" data-zion-setting="<?php echo esc_attr($key); ?>" value="1" <?php checked((int) $value, 1); ?>><span class="zion-eu-switch-track"></span></label>
                <?php elseif ($type === 'select') : ?><select id="<?php echo esc_attr($field_id); ?>" data-zion-setting="<?php echo esc_attr($key); ?>"><?php foreach ($field['options'] as $option_key => $option) : ?><option value="<?php echo esc_attr($option_key); ?>" <?php selected((string) $value, $option_key); ?>><?php echo esc_html($this->locale->text($option['ro'], $option['en'])); ?></option><?php endforeach; ?></select>
                <?php else : ?><input id="<?php echo esc_attr($field_id); ?>" type="<?php echo esc_attr($type); ?>" data-zion-setting="<?php echo esc_attr($key); ?>" value="<?php echo esc_attr((string) $value); ?>" <?php echo ! empty($field['readonly']) ? 'readonly' : ''; ?> <?php echo isset($field['min']) ? 'min="' . esc_attr((string) $field['min']) . '"' : ''; ?> <?php echo isset($field['max']) ? 'max="' . esc_attr((string) $field['max']) . '"' : ''; ?>><?php endif; ?>
                <span class="zion-eu-save-status" aria-live="polite"></span>
            </div>
        </div>
        <?php
    }

    /** @param array<string, mixed> $field */
    private function sanitize_value(mixed $value, array $field): mixed
    {
        return match ($field['type']) {
            'checkbox' => empty($value) ? 0 : 1,
            'number' => max((int) ($field['min'] ?? 0), min((int) ($field['max'] ?? PHP_INT_MAX), absint($value))),
            'email' => sanitize_email((string) $value),
            'color' => sanitize_hex_color((string) $value) ?: '#f97316',
            'select' => array_key_exists((string) $value, $field['options']) ? (string) $value : array_key_first($field['options']),
            default => sanitize_text_field((string) $value),
        };
    }

    private function render_header(string $active): void
    {
        $overview = admin_url('admin.php?page=zion-eu-withdrawal');
        $settings = admin_url('admin.php?page=zion-eu-withdrawal-settings');
        ?>
        <div class="wrap zion-eu-admin" data-zion-language="<?php echo esc_attr($this->locale->language()); ?>"><div class="zion-eu-topbar"><a class="zion-eu-brand" href="<?php echo esc_url($overview); ?>"><span class="zion-eu-brand-mark">Z</span><span><strong>Zion</strong><small>EU Withdrawal</small></span></a><nav><a class="<?php echo $active === 'overview' ? 'is-active' : ''; ?>" href="<?php echo esc_url($overview); ?>"><?php echo esc_html($this->locale->text('Prezentare', 'Overview')); ?></a><a class="<?php echo $active === 'settings' ? 'is-active' : ''; ?>" href="<?php echo esc_url($settings); ?>"><?php echo esc_html($this->locale->text('Setări', 'Settings')); ?></a></nav><span class="zion-eu-version">v<?php echo esc_html(ZION_EU_WITHDRAWAL_VERSION); ?></span></div>
        <?php if (! $this->woocommerce->is_available()) : ?><div class="zion-eu-alert zion-eu-alert--warning"><span>!</span><?php echo esc_html($this->woocommerce->message($this->locale->language())); ?></div><?php endif; ?><main class="zion-eu-content">
        <?php
    }

    private function render_footer(): void
    {
        ?></main><footer class="zion-eu-footer"><span>© <?php echo esc_html((string) gmdate('Y')); ?> Zion3D</span><span><?php echo esc_html($this->locale->text('Profil RO verificat • Fundație Phase 0–1', 'Verified RO profile • Phase 0–1 foundation')); ?></span></footer></div><?php
    }

    /** @return array<int, array<string, string>> */
    private function overview_stats(): array
    {
        return [
            ['value' => '14', 'label' => $this->locale->text('zile standard', 'standard days'), 'description' => $this->locale->text('Termen explicit în profilul RO.', 'Explicit in the RO profile.')],
            ['value' => '13', 'label' => $this->locale->text('excepții documentate', 'documented exceptions'), 'description' => $this->locale->text('EXC-A până la EXC-M, fără scurtături.', 'EXC-A through EXC-M, no shortcuts.')],
            ['value' => '100%', 'label' => 'AJAX / autosave', 'description' => $this->locale->text('Setările oferă feedback local.', 'Settings provide local feedback.')],
            ['value' => 'RO + EN', 'label' => $this->locale->text('limbi pregătite', 'languages ready'), 'description' => $this->locale->text('Româna este limba implicită.', 'Romanian is the default.')],
        ];
    }

    /** @return array<int, array<string, string>> */
    private function features(): array
    {
        return [
            ['title' => $this->locale->text('Profil juridic versionat', 'Versioned legal profile'), 'description' => $this->locale->text('Regulile nu sunt împrăștiate în condiții greu de urmărit.', 'Rules are not scattered through hard-to-trace conditions.')],
            ['title' => $this->locale->text('Schemă pregătită pentru dovezi', 'Evidence-ready schema'), 'description' => $this->locale->text('Snapshot, timestamp, sursă și profil legal pentru fiecare retragere.', 'Snapshot, timestamp, source and legal profile for each withdrawal.')],
            ['title' => $this->locale->text('Securitate din fundație', 'Security from the foundation'), 'description' => $this->locale->text('Nonce, capability checks, rate-limit și protecție împotriva enumerării sunt parte din design.', 'Nonce, capability checks, rate limiting and enumeration protection are part of the design.')],
        ];
    }
}
