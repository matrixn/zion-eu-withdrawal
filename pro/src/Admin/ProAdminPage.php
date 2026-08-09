<?php

declare(strict_types=1);

namespace Zion\EuWithdrawalPro\Admin;

use Zion\EuWithdrawal\Infrastructure\WithdrawalRepository;
use Zion\EuWithdrawal\Internationalization\LocaleManager;
use Zion\EuWithdrawalPro\Infrastructure\ProSettings;

final class ProAdminPage
{
    public function __construct(
        private readonly ProSettings $settings,
        private readonly WithdrawalRepository $repository
    ) {
    }

    public function register_hooks(): void
    {
        add_action('admin_menu', [$this, 'register_menu'], 20);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_assets']);
        add_action('wp_ajax_zion_eu_pro_save_setting', [$this, 'ajax_save_setting']);
        add_action('admin_post_zion_eu_pro_export', [$this, 'export_csv']);
        add_action('init', [$this, 'schedule_deadline_scan'], 20);
        add_action('zion_eu_withdrawal_pro_deadline_scan', [$this, 'deadline_scan']);
    }

    public function register_menu(): void
    {
        $capability = 'manage_options';
        $locale = new LocaleManager();
        add_submenu_page('zion-eu-withdrawal', 'Zion EU Withdrawal Pro', $locale->text('Pro · Control', 'Pro · Control'), $capability, 'zion-eu-withdrawal-pro', [$this, 'render_settings']);
        add_submenu_page('zion-eu-withdrawal', 'Zion EU Withdrawal Pro Dashboard', $locale->text('Pro · Dashboard', 'Pro · Dashboard'), $capability, 'zion-eu-withdrawal-pro-dashboard', [$this, 'render_dashboard']);
    }

    public function enqueue_assets(string $hook): void
    {
        if (! str_contains($hook, 'zion-eu-withdrawal-pro')) {
            return;
        }
        wp_enqueue_style('zion-eu-withdrawal-pro-admin', ZION_EU_WITHDRAWAL_PRO_URL . 'assets/pro-admin.css', [], ZION_EU_WITHDRAWAL_PRO_VERSION);
        wp_enqueue_script('zion-eu-withdrawal-pro-admin', ZION_EU_WITHDRAWAL_PRO_URL . 'assets/pro-admin.js', ['jquery'], ZION_EU_WITHDRAWAL_PRO_VERSION, true);
        $locale = new LocaleManager();
        wp_localize_script('zion-eu-withdrawal-pro-admin', 'ZionEuWithdrawalProAdmin', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('zion_eu_withdrawal_pro_settings'),
            'saving' => $locale->text('Se salveaza…', 'Saving…'),
            'saved' => $locale->text('Salvat automat', 'Saved automatically'),
            'error' => $locale->text('Nu s-a putut salva.', 'Could not save.'),
        ]);
    }

    public function ajax_save_setting(): void
    {
        check_ajax_referer('zion_eu_withdrawal_pro_settings', 'nonce');
        if (! current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Permission denied.'], 403);
        }
        $key = sanitize_key((string) wp_unslash($_POST['key'] ?? ''));
        if (! isset(ProSettings::schema()[$key])) {
            wp_send_json_error(['message' => 'Unknown Pro setting.'], 400);
        }
        $value = $this->settings->sanitize($key, wp_unslash($_POST['value'] ?? ''));
        $this->settings->update($key, $value);
        wp_send_json_success(['key' => $key, 'value' => $value, 'message' => 'Salvat automat / Saved automatically']);
    }

    public function render_settings(): void
    {
        $locale = new LocaleManager();
        $this->header($locale->text('Pro · Control', 'Pro · Control'));
        ?>
        <section class="zion-eu-pro-hero zion-eu-card"><span class="zion-eu-eyebrow">PHASE 11–12 / PRO ADD-ON</span><h1><?php echo esc_html($locale->text('Control Pro peste aceeași fundație core', 'Pro control on the same core foundation')); ?></h1><p><?php echo esc_html($locale->text('Pro nu duplică fluxul legal și baza de date. Adaugă controale avansate pentru experiență, reguli, livrare, operare și integrări.', 'Pro does not duplicate the legal flow or database. It adds advanced controls for experience, rules, delivery, operations and integrations.')); ?></p></section>
        <div class="zion-eu-pro-layout"><main>
        <?php foreach ($this->sections() as $section_key => $section) : ?>
            <section class="zion-eu-card zion-eu-settings-section"><div class="zion-eu-section-head"><div><span class="zion-eu-section-number"><?php echo esc_html($section['number']); ?></span><h2><?php echo esc_html($locale->text($section['ro'], $section['en'])); ?></h2><p><?php echo esc_html($locale->text($section['description_ro'], $section['description_en'])); ?></p></div></div><div class="zion-eu-fields">
            <?php foreach (ProSettings::schema() as $key => $field) : if ($field['section'] === $section_key) { $this->render_field($key, $field, $locale); } endforeach; ?>
            </div></section>
        <?php endforeach; ?>
        </main><aside><section class="zion-eu-card zion-eu-side-card zion-eu-side-card--dark"><span class="zion-eu-eyebrow">PRO</span><h2><?php echo esc_html($locale->text('Add-on activ', 'Active add-on')); ?></h2><p><?php echo esc_html($locale->text('Core-ul FREE rămâne sursa fluxului legal. Dezactivează Pro pentru a reveni la experiența Free.', 'The FREE core remains the source of the legal flow. Disable Pro to return to the Free experience.')); ?></p><a class="zion-eu-button zion-eu-button--primary" href="<?php echo esc_url(admin_url('admin.php?page=zion-eu-withdrawal-pro-dashboard')); ?>"><?php echo esc_html($locale->text('Deschide dashboardul', 'Open dashboard')); ?> →</a></section><section class="zion-eu-card"><h2><?php echo esc_html($locale->text('Format reguli', 'Rules format')); ?></h2><p class="zion-eu-admin-muted"><code>{"when":{"category":"personalizate"},"state":"potential_exception","exception_code":"EXC-C"}</code></p><p><?php echo esc_html($locale->text('Regulile sunt semnale orientative. Niciun rezultat Pro nu blochează transmiterea cererii.', 'Rules are indicative signals. No Pro result blocks submission.')); ?></p></section></aside></div>
        <?php $this->footer($locale); }

    public function render_dashboard(): void
    {
        $locale = new LocaleManager();
        $rows = $this->repository->all('', 100, 0);
        $stats = $this->dashboard_stats($rows);
        $this->header($locale->text('Pro · Dashboard', 'Pro · Dashboard'));
        ?>
        <section class="zion-eu-pro-hero zion-eu-card"><span class="zion-eu-eyebrow">PHASE 12 / OPERATIONS</span><h1><?php echo esc_html($locale->text('Dashboard de termene și control', 'Deadline and control dashboard')); ?></h1><p><?php echo esc_html($locale->text('Vezi rapid cererile care necesită atenție, excepțiile potențiale și starea integrărilor Pro.', 'Quickly see requests needing attention, potential exceptions and Pro integration status.')); ?></p><div class="zion-eu-hero-actions"><a class="zion-eu-button zion-eu-button--primary" href="<?php echo esc_url(wp_nonce_url(admin_url('admin-post.php?action=zion_eu_pro_export'), 'zion_eu_pro_export')); ?>"><?php echo esc_html($locale->text('Exportă CSV', 'Export CSV')); ?> ↓</a><a class="zion-eu-text-link" href="<?php echo esc_url(admin_url('admin.php?page=zion-eu-withdrawal-pro')); ?>"><?php echo esc_html($locale->text('Configurează Pro', 'Configure Pro')); ?> →</a></div></section>
        <div class="zion-eu-stats zion-eu-stats--requests"><?php foreach ($stats as $stat) : ?><div class="zion-eu-stat zion-eu-card"><span class="zion-eu-stat-value"><?php echo esc_html((string) $stat['value']); ?></span><strong><?php echo esc_html($stat['label']); ?></strong><p><?php echo esc_html($stat['description']); ?></p></div><?php endforeach; ?></div>
        <section class="zion-eu-card"><div class="zion-eu-card-heading"><span class="zion-eu-icon">◈</span><h2><?php echo esc_html($locale->text('Cererile recente', 'Recent requests')); ?></h2></div><div class="zion-eu-table-wrap"><table class="zion-eu-table"><thead><tr><th><?php echo esc_html($locale->text('ID', 'ID')); ?></th><th><?php echo esc_html($locale->text('Client', 'Customer')); ?></th><th><?php echo esc_html($locale->text('Status', 'Status')); ?></th><th><?php echo esc_html($locale->text('Termen estimat', 'Estimated deadline')); ?></th></tr></thead><tbody><?php foreach (array_slice($rows, 0, 30) as $row) : ?><tr><td><strong><?php echo esc_html((string) $row['withdrawal_id']); ?></strong></td><td><?php echo esc_html((string) $row['customer_name']); ?><br><small><?php echo esc_html((string) $row['customer_email']); ?></small></td><td><span class="zion-eu-status-badge"><?php echo esc_html((string) $row['status']); ?></span></td><td><?php echo esc_html((string) ($row['estimated_deadline'] ?: $locale->text('Necunoscut', 'Unknown'))); ?></td></tr><?php endforeach; ?></tbody></table></div></section>
        <?php $this->footer($locale); }

    public function export_csv(): void
    {
        if (! current_user_can('manage_options')) {
            wp_die('Permission denied.');
        }
        check_admin_referer('zion_eu_pro_export');
        nocache_headers();
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=zion-eu-pro-withdrawals-' . gmdate('Ymd-His') . '.csv');
        $output = fopen('php://output', 'wb');
        fputcsv($output, ['withdrawal_id', 'customer_name', 'customer_email', 'status', 'delivery_date', 'estimated_deadline', 'created_at']);
        foreach ($this->repository->all('', 200, 0) as $row) {
            fputcsv($output, [$row['withdrawal_id'], $row['customer_name'], $row['customer_email'], $row['status'], $row['delivery_date'], $row['estimated_deadline'], $row['created_at']]);
        }
        fclose($output);
        exit;
    }

    public function schedule_deadline_scan(): void
    {
        if (! wp_next_scheduled('zion_eu_withdrawal_pro_deadline_scan')) {
            wp_schedule_event(time() + HOUR_IN_SECONDS, 'hourly', 'zion_eu_withdrawal_pro_deadline_scan');
        }
    }

    public function deadline_scan(): void
    {
        $lead = (int) $this->settings->get('deadline_reminder_days', 3);
        $now = time();
        $recipient = sanitize_email((string) get_option('admin_email'));
        if ($lead < 1 || $recipient === '') {
            return;
        }

        foreach ($this->repository->all('', 200, 0) as $row) {
            $deadline = strtotime((string) ($row['estimated_deadline'] ?? ''));
            if (! $deadline || $deadline < $now || $deadline > $now + ($lead * DAY_IN_SECONDS)) {
                continue;
            }
            $id = sanitize_key((string) ($row['withdrawal_id'] ?? ''));
            if ($id === '' || get_transient('zion_eu_pro_deadline_notice_' . $id)) {
                continue;
            }
            wp_mail($recipient, 'Zion EU Withdrawal Pro - termen apropiat', 'Cererea ' . $id . ' are termenul estimat ' . (string) $row['estimated_deadline'] . '. Verifica dashboardul Pro.');
            set_transient('zion_eu_pro_deadline_notice_' . $id, 1, DAY_IN_SECONDS);
        }
    }

    /** @param array<int, array<string, mixed>> $rows @return array<int, array<string, string|int>> */
    private function dashboard_stats(array $rows): array
    {
        $soon = 0;
        $exceptions = 0;
        $now = time();
        $lead = (int) $this->settings->get('deadline_reminder_days', 3);
        foreach ($rows as $row) {
            if (! empty($row['legal_exception_code'])) {
                $exceptions++;
            }
            $deadline = strtotime((string) ($row['estimated_deadline'] ?? ''));
            if ($deadline && $deadline >= $now && $deadline <= $now + ($lead * DAY_IN_SECONDS)) {
                $soon++;
            }
        }
        return [
            ['value' => count($rows), 'label' => 'cereri / requests', 'description' => 'Ultimele 100 înregistrări disponibile în dashboard.'],
            ['value' => $soon, 'label' => 'termene apropiate / due soon', 'description' => 'În fereastra de avertizare configurată în Pro.'],
            ['value' => $exceptions, 'label' => 'excepții potențiale / potential exceptions', 'description' => 'Necesită verificare operațională și juridică.'],
        ];
    }

    /** @param array<string, mixed> $field */
    private function render_field(string $key, array $field, LocaleManager $locale): void
    {
        $value = $this->settings->get($key, '');
        $type = $field['type'];
        $id = 'zion-eu-pro-setting-' . $key;
        ?><div class="zion-eu-field zion-eu-field--<?php echo esc_attr($type); ?>" data-zion-pro-setting-row="<?php echo esc_attr($key); ?>"><div class="zion-eu-field-copy"><label for="<?php echo esc_attr($id); ?>"><?php echo esc_html($locale->text($field['ro'], $field['en'])); ?></label><p><?php echo esc_html($locale->text($field['description_ro'], $field['description_en'])); ?></p></div><div class="zion-eu-field-control">
        <?php if ($type === 'checkbox') : ?><label class="zion-eu-switch"><input id="<?php echo esc_attr($id); ?>" type="checkbox" data-zion-pro-setting="<?php echo esc_attr($key); ?>" value="1" <?php checked((int) $value, 1); ?>><span class="zion-eu-switch-track"></span></label>
        <?php elseif ($type === 'select') : ?><select id="<?php echo esc_attr($id); ?>" data-zion-pro-setting="<?php echo esc_attr($key); ?>"><?php foreach ($field['options'] as $option_key => $option) : ?><option value="<?php echo esc_attr($option_key); ?>" <?php selected((string) $value, $option_key); ?>><?php echo esc_html($locale->text($option['ro'], $option['en'])); ?></option><?php endforeach; ?></select>
        <?php elseif ($type === 'textarea') : ?><textarea id="<?php echo esc_attr($id); ?>" data-zion-pro-setting="<?php echo esc_attr($key); ?>" rows="<?php echo $key === 'advanced_rules' ? '12' : '5'; ?>"><?php echo esc_textarea((string) $value); ?></textarea>
        <?php else : ?><input id="<?php echo esc_attr($id); ?>" type="<?php echo esc_attr($type); ?>" data-zion-pro-setting="<?php echo esc_attr($key); ?>" value="<?php echo esc_attr((string) $value); ?>" <?php echo isset($field['min']) ? 'min="' . esc_attr((string) $field['min']) . '"' : ''; ?> <?php echo isset($field['max']) ? 'max="' . esc_attr((string) $field['max']) . '"' : ''; ?>><?php endif; ?><span class="zion-eu-save-status" aria-live="polite"></span></div></div><?php
    }

    /** @return array<string, array<string, string>> */
    private function sections(): array
    {
        return [
            'visual' => ['number' => '01', 'ro' => 'Visual și poziționare', 'en' => 'Visual and placement', 'description_ro' => 'Buton plutitor, culori, etichete, tipografie și CSS controlat.', 'description_en' => 'Floating button, colours, labels, typography and controlled CSS.'],
            'checkout' => ['number' => '02', 'ro' => 'Informare checkout Pro', 'en' => 'Pro checkout disclosure', 'description_ro' => 'Texte custom și poziționare avansată pentru informarea precontractuală.', 'description_en' => 'Custom copy and advanced placement for pre-contract information.'],
            'rules' => ['number' => '03', 'ro' => 'Rules Engine avansat', 'en' => 'Advanced Rules Engine', 'description_ro' => 'Condiții pe categorie, tag, tip produs, țară, status și metode de livrare.', 'description_en' => 'Conditions on category, tag, product type, country, status and shipping methods.'],
            'delivery' => ['number' => '04', 'ro' => 'Livrare și termene', 'en' => 'Delivery and deadlines', 'description_ro' => 'Data reală a livrării și fereastra de atenționare pentru operator.', 'description_en' => 'Actual delivery date and operator attention window.'],
            'integrations' => ['number' => '05', 'ro' => 'API și Webhooks', 'en' => 'API and webhooks', 'description_ro' => 'Endpoint-uri REST protejate și evenimente semnate HMAC.', 'description_en' => 'Protected REST endpoints and HMAC-signed events.'],
            'agency' => ['number' => '06', 'ro' => 'Agency și white-label', 'en' => 'Agency and white-label', 'description_ro' => 'Controlul brandingului pentru implementări autorizate de agenții.', 'description_en' => 'Branding controls for authorised agency implementations.'],
        ];
    }

    private function header(string $title): void
    {
        echo '<div class="wrap zion-eu-admin zion-eu-pro-admin"><main class="zion-eu-content">';
    }

    private function footer(LocaleManager $locale): void
    {
        echo '<footer class="zion-eu-footer"><span>© ' . esc_html((string) gmdate('Y')) . ' Zion3D</span><span>' . esc_html($locale->text('Phase 11–12 Pro add-on', 'Phase 11–12 Pro add-on')) . '</span></footer></main></div>';
    }
}
