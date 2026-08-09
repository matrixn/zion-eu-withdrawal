<?php

declare(strict_types=1);

namespace Zion\EuWithdrawal\Admin;

use Zion\EuWithdrawal\Infrastructure\AuditRepository;
use Zion\EuWithdrawal\Infrastructure\NotificationService;
use Zion\EuWithdrawal\Infrastructure\WithdrawalRepository;
use Zion\EuWithdrawal\Internationalization\LocaleManager;

final class WithdrawalsPage
{
    public function __construct(
        private readonly WithdrawalRepository $repository,
        private readonly LocaleManager $locale,
        private readonly NotificationService $notifications,
        private readonly AuditRepository $audit
    ) {
    }

    public function register_menu(): void
    {
        add_submenu_page('zion-eu-withdrawal', $this->locale->text('Cereri de retragere', 'Withdrawal requests'), $this->locale->text('Cereri de retragere', 'Withdrawal requests'), 'manage_options', 'zion-eu-withdrawal-requests', [$this, 'render']);
    }

    public function ajax_update_withdrawal(): void
    {
        check_ajax_referer('zion_eu_withdrawal_admin', 'nonce');
        if (! current_user_can('manage_options')) {
            wp_send_json_error(['message' => $this->locale->text('Nu ai permisiunea necesara.', 'You do not have the required permission.')], 403);
        }

        $id = absint(wp_unslash($_POST['id'] ?? 0));
        $status = sanitize_key((string) wp_unslash($_POST['status'] ?? 'submitted'));
        $notes = sanitize_textarea_field((string) wp_unslash($_POST['merchant_notes'] ?? ''));
        if ($id < 1 || ! in_array($status, $this->status_keys(), true) || ! $this->repository->find_by_id($id)) {
            wp_send_json_error(['message' => $this->locale->text('Datele cererii nu sunt valide.', 'The request data is invalid.')], 422);
        }

        $this->repository->update($id, $status, $notes);
        $updated = $this->repository->find_by_id($id);
        if (is_array($updated)) {
            do_action('zion_eu_withdrawal_status_changed', $updated, $status);
        }
        $this->audit->record($id, 'merchant_update', 'Status sau nota interna actualizata.', ['status' => $status], get_current_user_id());
        wp_send_json_success(['message' => $this->locale->text('Cererea a fost actualizata.', 'The request was updated.')]);
    }

    public function ajax_resend_notification(): void
    {
        check_ajax_referer('zion_eu_withdrawal_admin', 'nonce');
        if (! current_user_can('manage_options')) {
            wp_send_json_error(['message' => $this->locale->text('Nu ai permisiunea necesara.', 'You do not have the required permission.')], 403);
        }

        $id = absint(wp_unslash($_POST['id'] ?? 0));
        $type = sanitize_key((string) wp_unslash($_POST['type'] ?? 'consumer_confirmation'));
        $row = $this->repository->find_by_id($id);
        if (! $row || ! in_array($type, ['consumer_confirmation', 'admin_confirmation'], true)) {
            wp_send_json_error(['message' => $this->locale->text('Notificarea nu poate fi retransmisa.', 'The notification cannot be resent.')], 422);
        }

        if (! $this->notifications->resend($row, $type)) {
            wp_send_json_error(['message' => $this->locale->text('Trimiterea a esuat; incercarea a fost pastrata in log.', 'Delivery failed; the attempt was kept in the log.')], 500);
        }

        wp_send_json_success(['message' => $this->locale->text('Notificarea a fost retransmisa.', 'The notification was resent.')]);
    }

    public function export_csv(): void
    {
        if (! current_user_can('manage_options')) {
            wp_die(esc_html($this->locale->text('Nu ai permisiunea necesara.', 'You do not have the required permission.')));
        }
        check_admin_referer('zion_eu_export_withdrawals');

        $status = sanitize_key((string) wp_unslash($_GET['status'] ?? ''));
        $source = sanitize_key((string) wp_unslash($_GET['source'] ?? ''));
        $search = sanitize_text_field((string) wp_unslash($_GET['s'] ?? ''));
        $rows = $this->repository->all($status, 5000, 0, $search, $source);
        nocache_headers();
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=zion-withdrawals-' . gmdate('Y-m-d') . '.csv');
        $handle = fopen('php://output', 'wb');
        if ($handle === false) {
            exit;
        }
        fputcsv($handle, ['Withdrawal ID', 'Customer', 'E-mail', 'Order', 'Source', 'Status', 'Eligibility', 'Submitted UTC', 'Notes']);
        foreach ($rows as $row) {
            fputcsv($handle, [$row['withdrawal_id'], $row['customer_name'], $row['customer_email'], $row['contract_reference'], $row['source'], $row['status'], $row['legal_exception_code'] ?: $row['eligibility_snapshot'], $row['created_at'], $row['merchant_notes']]);
        }
        fclose($handle);
        exit;
    }

    public function render(): void
    {
        $detail_id = absint(wp_unslash($_GET['withdrawal_id'] ?? 0));
        $this->header();
        if ($detail_id > 0) {
            $this->render_detail($detail_id);
        } else {
            $this->render_list();
        }
        echo '</main></div>';
    }

    private function render_list(): void
    {
        $status = sanitize_key((string) wp_unslash($_GET['status'] ?? ''));
        $source = sanitize_key((string) wp_unslash($_GET['source'] ?? ''));
        $search = sanitize_text_field((string) wp_unslash($_GET['s'] ?? ''));
        $rows = $this->repository->all($status, 100, 0, $search, $source);
        $export_args = ['action' => 'zion_eu_export_withdrawals', 'status' => $status, 'source' => $source, 's' => $search];
        $export_url = wp_nonce_url(add_query_arg($export_args, admin_url('admin-post.php')), 'zion_eu_export_withdrawals');
        ?>
        <div class="zion-eu-section-title"><div><span class="zion-eu-eyebrow"><?php echo esc_html($this->locale->text('Registru operational', 'Operational register')); ?></span><h1><?php echo esc_html($this->locale->text('Cereri de retragere', 'Withdrawal requests')); ?></h1><p class="zion-eu-admin-muted"><?php echo esc_html($this->locale->text('Toate declaratiile confirmate, cu dovada, eligibilitate orientativa si timestamp.', 'All confirmed statements, with evidence, indicative eligibility and timestamp.')); ?></p></div><a class="zion-eu-button zion-eu-button--primary" href="<?php echo esc_url($export_url); ?>"><?php echo esc_html($this->locale->text('Export CSV', 'Export CSV')); ?> ↓</a></div>
        <div class="zion-eu-stats zion-eu-stats--requests"><div class="zion-eu-stat zion-eu-card"><span class="zion-eu-stat-value"><?php echo esc_html((string) $this->repository->count()); ?></span><strong><?php echo esc_html($this->locale->text('total cereri', 'total requests')); ?></strong><p><?php echo esc_html($this->locale->text('Inregistrari pastrate in baza de date.', 'Records retained in the database.')); ?></p></div><div class="zion-eu-stat zion-eu-card"><span class="zion-eu-stat-value"><?php echo esc_html((string) $this->repository->count('submitted')); ?></span><strong><?php echo esc_html($this->locale->text('transmise', 'submitted')); ?></strong><p><?php echo esc_html($this->locale->text('Asteapta procesarea comerciantului.', 'Waiting for merchant processing.')); ?></p></div></div>
        <div class="zion-eu-card zion-eu-table-card"><div class="zion-eu-table-toolbar"><strong><?php echo esc_html($this->locale->text('Registru cereri', 'Request register')); ?></strong><form method="get"><input type="hidden" name="page" value="zion-eu-withdrawal-requests"><input type="search" name="s" value="<?php echo esc_attr($search); ?>" placeholder="<?php echo esc_attr($this->locale->text('Cauta ID, client, e-mail sau comanda', 'Search ID, customer, e-mail or order')); ?>"><select name="status" aria-label="<?php echo esc_attr($this->locale->text('Filtreaza dupa status', 'Filter by status')); ?>"><option value=""><?php echo esc_html($this->locale->text('Toate statusurile', 'All statuses')); ?></option><?php $this->render_status_options($status); ?></select><select name="source" aria-label="<?php echo esc_attr($this->locale->text('Filtreaza dupa sursa', 'Filter by source')); ?>"><option value=""><?php echo esc_html($this->locale->text('Toate sursele', 'All sources')); ?></option><option value="account" <?php selected($source, 'account'); ?>>Account</option><option value="guest" <?php selected($source, 'guest'); ?>>Guest</option><option value="public" <?php selected($source, 'public'); ?>>Public</option></select><button class="zion-eu-filter-button" type="submit"><?php echo esc_html($this->locale->text('Filtreaza', 'Filter')); ?></button></form></div>
        <?php if ($rows === []) : ?><div class="zion-eu-empty-state"><span>○</span><h2><?php echo esc_html($this->locale->text('Nu exista inca cereri', 'No requests yet')); ?></h2><p><?php echo esc_html($this->locale->text('Cand un client confirma o retragere, aceasta va aparea aici.', 'When a customer confirms a withdrawal, it will appear here.')); ?></p></div>
        <?php else : ?><div class="zion-eu-table-wrap"><table class="zion-eu-requests-table"><thead><tr><th><?php echo esc_html($this->locale->text('ID retragere', 'Withdrawal ID')); ?></th><th><?php echo esc_html($this->locale->text('Client', 'Customer')); ?></th><th><?php echo esc_html($this->locale->text('Comanda', 'Order')); ?></th><th><?php echo esc_html($this->locale->text('Sursa', 'Source')); ?></th><th><?php echo esc_html($this->locale->text('Eligibilitate', 'Eligibility')); ?></th><th><?php echo esc_html($this->locale->text('Data / ora UTC', 'Date / time UTC')); ?></th><th><?php echo esc_html($this->locale->text('Status', 'Status')); ?></th></tr></thead><tbody><?php foreach ($rows as $row) : ?><tr><td><a class="zion-eu-request-id" href="<?php echo esc_url(add_query_arg(['page' => 'zion-eu-withdrawal-requests', 'withdrawal_id' => (int) $row['id']], admin_url('admin.php'))); ?>"><?php echo esc_html((string) $row['withdrawal_id']); ?></a></td><td><strong><?php echo esc_html((string) $row['customer_name']); ?></strong><small><?php echo esc_html((string) $row['customer_email']); ?></small></td><td>#<?php echo esc_html((string) $row['contract_reference']); ?></td><td><span class="zion-eu-source-pill"><?php echo esc_html((string) $row['source']); ?></span></td><td><span class="zion-eu-source-pill"><?php echo esc_html((string) ($row['legal_exception_code'] ?: ($row['delivery_date'] ? 'standard' : 'unknown'))); ?></span></td><td><?php echo esc_html((string) $row['created_at']); ?></td><td><span class="zion-eu-status-pill"><?php echo esc_html((string) $row['status']); ?></span></td></tr><?php endforeach; ?></tbody></table></div><?php endif; ?></div>
        <?php
    }

    private function render_detail(int $id): void
    {
        $row = $this->repository->find_by_id($id);
        if (! $row) {
            echo '<div class="zion-eu-alert zion-eu-alert--warning">' . esc_html($this->locale->text('Cererea nu a fost gasita.', 'The request was not found.')) . '</div>';
            return;
        }

        $items = $this->repository->items_for($id);
        $notifications = $this->notifications->for_withdrawal($id);
        $audit = $this->audit->for_withdrawal($id);
        ?>
        <div class="zion-eu-section-title"><div><a class="zion-eu-back-link" href="<?php echo esc_url(admin_url('admin.php?page=zion-eu-withdrawal-requests')); ?>">← <?php echo esc_html($this->locale->text('Toate cererile', 'All requests')); ?></a><h1><?php echo esc_html((string) $row['withdrawal_id']); ?></h1></div><span class="zion-eu-status-pill zion-eu-status-pill--large"><?php echo esc_html((string) $row['status']); ?></span></div>
        <div class="zion-eu-request-detail-grid"><section class="zion-eu-card"><div class="zion-eu-card-heading"><span class="zion-eu-icon">◈</span><h2><?php echo esc_html($this->locale->text('Datele cererii', 'Request details')); ?></h2></div><dl class="zion-eu-detail-list"><dt><?php echo esc_html($this->locale->text('Client', 'Customer')); ?></dt><dd><?php echo esc_html((string) $row['customer_name']); ?><br><a href="mailto:<?php echo esc_attr((string) $row['customer_email']); ?>"><?php echo esc_html((string) $row['customer_email']); ?></a></dd><dt><?php echo esc_html($this->locale->text('Telefon', 'Phone')); ?></dt><dd><?php echo esc_html((string) $row['customer_phone'] ?: '—'); ?></dd><dt><?php echo esc_html($this->locale->text('Comanda / contract', 'Order / contract')); ?></dt><dd>#<?php echo esc_html((string) $row['contract_reference']); ?> <span class="zion-eu-muted-id">(ID <?php echo esc_html((string) $row['order_id']); ?>)</span></dd><dt><?php echo esc_html($this->locale->text('Profil juridic', 'Legal profile')); ?></dt><dd><?php echo esc_html((string) $row['legal_profile_version']); ?></dd><dt><?php echo esc_html($this->locale->text('Sursa', 'Source')); ?></dt><dd><?php echo esc_html((string) $row['source']); ?></dd><dt><?php echo esc_html($this->locale->text('Data livrarii', 'Delivery date')); ?></dt><dd><?php echo esc_html((string) ($row['delivery_date'] ?: $this->locale->text('Necunoscuta', 'Unknown'))); ?></dd><dt><?php echo esc_html($this->locale->text('Termen estimat', 'Estimated deadline')); ?></dt><dd><?php echo esc_html((string) ($row['estimated_deadline'] ?: $this->locale->text('Necesita verificare', 'Needs review'))); ?></dd><dt><?php echo esc_html($this->locale->text('Timestamp server-side UTC', 'Server-side timestamp UTC')); ?></dt><dd><?php echo esc_html((string) $row['created_at']); ?></dd></dl></section><section class="zion-eu-card"><div class="zion-eu-card-heading"><span class="zion-eu-icon">✓</span><h2><?php echo esc_html($this->locale->text('Produse si eligibilitate', 'Items and eligibility')); ?></h2></div><?php if ($items === []) : ?><p class="zion-eu-admin-muted">—</p><?php else : ?><ul class="zion-eu-detail-items"><?php foreach ($items as $item) : ?><li><span><?php echo esc_html((string) ($item['product_name'] ?: ('Product #' . $item['product_id']))); ?><small><?php echo esc_html((string) ($item['eligibility_reason'] ?? '')); ?></small></span><strong><?php echo esc_html((string) $item['eligibility']); ?> × <?php echo esc_html((string) $item['quantity']); ?></strong></li><?php endforeach; ?></ul><?php endif; ?></section></div>
        <section class="zion-eu-card zion-eu-admin-edit-card"><div class="zion-eu-card-heading"><span class="zion-eu-icon">✎</span><h2><?php echo esc_html($this->locale->text('Procesare comerciant', 'Merchant processing')); ?></h2></div><div class="zion-eu-admin-edit-grid"><label><span><?php echo esc_html($this->locale->text('Status', 'Status')); ?></span><small><?php echo esc_html($this->locale->text('Status operational; nu reprezinta o decizie juridica automata.', 'Operational status; it is not an automatic legal decision.')); ?></small><select data-withdrawal-status><?php $this->render_status_options((string) $row['status']); ?></select></label><label><span><?php echo esc_html($this->locale->text('Note interne', 'Internal notes')); ?></span><small><?php echo esc_html($this->locale->text('Note vizibile doar echipei cu acces administrativ.', 'Notes visible only to the administrative team.')); ?></small><textarea rows="5" data-withdrawal-notes><?php echo esc_textarea((string) $row['merchant_notes']); ?></textarea></label></div><button type="button" class="zion-eu-button zion-eu-button--primary" data-save-withdrawal="<?php echo esc_attr((string) $id); ?>"><?php echo esc_html($this->locale->text('Salveaza procesarea', 'Save processing')); ?></button><span class="zion-eu-save-status" data-withdrawal-feedback aria-live="polite"></span></section>
        <section class="zion-eu-card zion-eu-snapshot-card"><div class="zion-eu-card-heading"><span class="zion-eu-icon">▤</span><h2><?php echo esc_html($this->locale->text('Snapshot exact transmis de client', 'Exact snapshot submitted by the customer')); ?></h2></div><pre><?php echo esc_html((string) $row['statement_content']); ?></pre></section>
        <section class="zion-eu-card zion-eu-notification-card"><div class="zion-eu-card-heading"><span class="zion-eu-icon">✉</span><h2><?php echo esc_html($this->locale->text('Notificari si retransmitere', 'Notifications and resend')); ?></h2></div><div class="zion-eu-notification-actions"><button type="button" class="zion-eu-filter-button" data-resend-notification="<?php echo esc_attr((string) $id); ?>" data-notification-type="consumer_confirmation"><?php echo esc_html($this->locale->text('Retrimite clientului', 'Resend to consumer')); ?></button><button type="button" class="zion-eu-filter-button" data-resend-notification="<?php echo esc_attr((string) $id); ?>" data-notification-type="admin_confirmation"><?php echo esc_html($this->locale->text('Retrimite comerciantului', 'Resend to merchant')); ?></button></div><ul class="zion-eu-audit-list"><?php foreach ($notifications as $notification) : ?><li><strong><?php echo esc_html((string) $notification['notification_type']); ?></strong><span><?php echo esc_html((string) $notification['status']); ?> · <?php echo esc_html((string) $notification['created_at']); ?></span><?php if (! empty($notification['error_message'])) : ?><small><?php echo esc_html((string) $notification['error_message']); ?></small><?php endif; ?></li><?php endforeach; ?></ul></section>
        <section class="zion-eu-card zion-eu-audit-card"><div class="zion-eu-card-heading"><span class="zion-eu-icon">◷</span><h2><?php echo esc_html($this->locale->text('Istoric audit', 'Audit history')); ?></h2></div><ul class="zion-eu-audit-list"><?php foreach ($audit as $event) : ?><li><strong><?php echo esc_html((string) $event['event_type']); ?></strong><span><?php echo esc_html((string) $event['created_at']); ?></span><small><?php echo esc_html((string) $event['message']); ?></small></li><?php endforeach; ?></ul></section>
        <?php
    }

    private function header(): void
    {
        ?><div class="wrap zion-eu-admin"><main class="zion-eu-content">
        <?php
    }

    /** @return array<int, string> */
    private function status_keys(): array
    {
        return ['submitted', 'under_review', 'acknowledged', 'closed', 'notification_failed'];
    }

    private function render_status_options(string $selected): void
    {
        $labels = ['submitted' => ['ro' => 'Transmisa', 'en' => 'Submitted'], 'under_review' => ['ro' => 'In verificare', 'en' => 'Under review'], 'acknowledged' => ['ro' => 'Confirmata intern', 'en' => 'Acknowledged'], 'closed' => ['ro' => 'Inchisa', 'en' => 'Closed'], 'notification_failed' => ['ro' => 'Notificare esuata', 'en' => 'Notification failed']];
        foreach ($labels as $key => $label) {
            echo '<option value="' . esc_attr($key) . '" ' . selected($selected, $key, false) . '>' . esc_html($this->locale->text($label['ro'], $label['en'])) . '</option>';
        }
    }
}
