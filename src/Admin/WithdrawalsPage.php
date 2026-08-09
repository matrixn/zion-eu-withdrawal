<?php

declare(strict_types=1);

namespace Zion\EuWithdrawal\Admin;

use Zion\EuWithdrawal\Infrastructure\WithdrawalRepository;
use Zion\EuWithdrawal\Internationalization\LocaleManager;

final class WithdrawalsPage
{
    public function __construct(
        private readonly WithdrawalRepository $repository,
        private readonly LocaleManager $locale
    ) {
    }

    public function register_menu(): void
    {
        add_submenu_page(
            'zion-eu-withdrawal',
            $this->locale->text('Cereri de retragere', 'Withdrawal requests'),
            $this->locale->text('Cereri de retragere', 'Withdrawal requests'),
            'manage_options',
            'zion-eu-withdrawal-requests',
            [$this, 'render']
        );
    }

    public function render(): void
    {
        $detail_id = absint(wp_unslash($_GET['withdrawal_id'] ?? 0));
        $status = sanitize_key((string) wp_unslash($_GET['status'] ?? ''));
        $this->header();

        if ($detail_id > 0) {
            $this->render_detail($detail_id);
        } else {
            $this->render_list($status);
        }

        echo '</main></div>';
    }

    private function render_list(string $status): void
    {
        $rows = $this->repository->all($status, 100);
        ?>
        <div class="zion-eu-section-title"><div><span class="zion-eu-eyebrow"><?php echo esc_html($this->locale->text('Registru operațional', 'Operational register')); ?></span><h1><?php echo esc_html($this->locale->text('Cereri de retragere', 'Withdrawal requests')); ?></h1><p class="zion-eu-admin-muted"><?php echo esc_html($this->locale->text('Toate declarațiile confirmate, cu dovada și timestamp-ul lor.', 'All confirmed statements, with their evidence and timestamp.')); ?></p></div><a class="zion-eu-button zion-eu-button--primary" href="<?php echo esc_url(admin_url('admin.php?page=zion-eu-withdrawal')); ?>"><?php echo esc_html($this->locale->text('Înapoi la prezentare', 'Back to overview')); ?></a></div>
        <div class="zion-eu-stats zion-eu-stats--requests"><div class="zion-eu-stat zion-eu-card"><span class="zion-eu-stat-value"><?php echo esc_html((string) $this->repository->count()); ?></span><strong><?php echo esc_html($this->locale->text('total cereri', 'total requests')); ?></strong><p><?php echo esc_html($this->locale->text('Înregistrări păstrate în baza de date.', 'Records retained in the database.')); ?></p></div><div class="zion-eu-stat zion-eu-card"><span class="zion-eu-stat-value"><?php echo esc_html((string) $this->repository->count('submitted')); ?></span><strong><?php echo esc_html($this->locale->text('transmise', 'submitted')); ?></strong><p><?php echo esc_html($this->locale->text('Așteaptă procesarea comerciantului.', 'Waiting for merchant processing.')); ?></p></div></div>
        <div class="zion-eu-card zion-eu-table-card"><div class="zion-eu-table-toolbar"><strong><?php echo esc_html($this->locale->text('Registru cereri', 'Request register')); ?></strong><form method="get"><input type="hidden" name="page" value="zion-eu-withdrawal-requests"><select name="status" aria-label="<?php echo esc_attr($this->locale->text('Filtrează după status', 'Filter by status')); ?>"><option value=""><?php echo esc_html($this->locale->text('Toate statusurile', 'All statuses')); ?></option><option value="submitted" <?php selected($status, 'submitted'); ?>><?php echo esc_html($this->locale->text('Transmisă', 'Submitted')); ?></option></select><button class="zion-eu-filter-button" type="submit"><?php echo esc_html($this->locale->text('Filtrează', 'Filter')); ?></button></form></div>
        <?php if ($rows === []) : ?><div class="zion-eu-empty-state"><span>◌</span><h2><?php echo esc_html($this->locale->text('Nu există încă cereri', 'No requests yet')); ?></h2><p><?php echo esc_html($this->locale->text('Când un client confirmă o retragere, aceasta va apărea aici.', 'When a customer confirms a withdrawal, it will appear here.')); ?></p></div>
        <?php else : ?><div class="zion-eu-table-wrap"><table class="zion-eu-requests-table"><thead><tr><th><?php echo esc_html($this->locale->text('ID retragere', 'Withdrawal ID')); ?></th><th><?php echo esc_html($this->locale->text('Client', 'Customer')); ?></th><th><?php echo esc_html($this->locale->text('Comandă', 'Order')); ?></th><th><?php echo esc_html($this->locale->text('Sursă', 'Source')); ?></th><th><?php echo esc_html($this->locale->text('Data / ora UTC', 'Date / time UTC')); ?></th><th><?php echo esc_html($this->locale->text('Status', 'Status')); ?></th></tr></thead><tbody><?php foreach ($rows as $row) : ?><tr><td><a class="zion-eu-request-id" href="<?php echo esc_url(add_query_arg(['page' => 'zion-eu-withdrawal-requests', 'withdrawal_id' => (int) $row['id']], admin_url('admin.php'))); ?>"><?php echo esc_html((string) $row['withdrawal_id']); ?></a></td><td><strong><?php echo esc_html((string) $row['customer_name']); ?></strong><small><?php echo esc_html((string) $row['customer_email']); ?></small></td><td>#<?php echo esc_html((string) $row['contract_reference']); ?></td><td><span class="zion-eu-source-pill"><?php echo esc_html((string) $row['source']); ?></span></td><td><?php echo esc_html((string) $row['created_at']); ?></td><td><span class="zion-eu-status-pill"><?php echo esc_html((string) $row['status']); ?></span></td></tr><?php endforeach; ?></tbody></table></div><?php endif; ?></div>
        <?php
    }

    private function render_detail(int $id): void
    {
        $row = $this->repository->find_by_id($id);
        if (! $row) {
            echo '<div class="zion-eu-alert zion-eu-alert--warning">' . esc_html($this->locale->text('Cererea nu a fost găsită.', 'The request was not found.')) . '</div>';
            return;
        }

        $items = $this->repository->items_for($id);
        ?>
        <div class="zion-eu-section-title"><div><a class="zion-eu-back-link" href="<?php echo esc_url(admin_url('admin.php?page=zion-eu-withdrawal-requests')); ?>">← <?php echo esc_html($this->locale->text('Toate cererile', 'All requests')); ?></a><h1><?php echo esc_html((string) $row['withdrawal_id']); ?></h1></div><span class="zion-eu-status-pill zion-eu-status-pill--large"><?php echo esc_html((string) $row['status']); ?></span></div>
        <div class="zion-eu-request-detail-grid"><section class="zion-eu-card"><div class="zion-eu-card-heading"><span class="zion-eu-icon">◈</span><h2><?php echo esc_html($this->locale->text('Datele cererii', 'Request details')); ?></h2></div><dl class="zion-eu-detail-list"><dt><?php echo esc_html($this->locale->text('Client', 'Customer')); ?></dt><dd><?php echo esc_html((string) $row['customer_name']); ?><br><a href="mailto:<?php echo esc_attr((string) $row['customer_email']); ?>"><?php echo esc_html((string) $row['customer_email']); ?></a></dd><dt><?php echo esc_html($this->locale->text('Telefon', 'Phone')); ?></dt><dd><?php echo esc_html((string) $row['customer_phone'] ?: '—'); ?></dd><dt><?php echo esc_html($this->locale->text('Comandă / contract', 'Order / contract')); ?></dt><dd>#<?php echo esc_html((string) $row['contract_reference']); ?> <span class="zion-eu-muted-id">(ID <?php echo esc_html((string) $row['order_id']); ?>)</span></dd><dt><?php echo esc_html($this->locale->text('Profil juridic', 'Legal profile')); ?></dt><dd><?php echo esc_html((string) $row['legal_profile_version']); ?></dd><dt><?php echo esc_html($this->locale->text('Sursă', 'Source')); ?></dt><dd><?php echo esc_html((string) $row['source']); ?></dd><dt><?php echo esc_html($this->locale->text('Timestamp server-side UTC', 'Server-side timestamp UTC')); ?></dt><dd><?php echo esc_html((string) $row['created_at']); ?></dd></dl></section><section class="zion-eu-card"><div class="zion-eu-card-heading"><span class="zion-eu-icon">✓</span><h2><?php echo esc_html($this->locale->text('Produse în snapshot', 'Items in snapshot')); ?></h2></div><?php if ($items === []) : ?><p class="zion-eu-admin-muted">—</p><?php else : ?><ul class="zion-eu-detail-items"><?php foreach ($items as $item) : ?><li><span><?php echo esc_html($this->locale->text('Produs', 'Product')); ?> #<?php echo esc_html((string) $item['product_id']); ?></span><strong>× <?php echo esc_html((string) $item['quantity']); ?></strong></li><?php endforeach; ?></ul><?php endif; ?></section></div>
        <section class="zion-eu-card zion-eu-snapshot-card"><div class="zion-eu-card-heading"><span class="zion-eu-icon">▤</span><h2><?php echo esc_html($this->locale->text('Snapshot exact transmis de client', 'Exact snapshot submitted by the customer')); ?></h2></div><pre><?php echo esc_html((string) $row['statement_content']); ?></pre></section>
        <?php
    }

    private function header(): void
    {
        ?><div class="wrap zion-eu-admin"><div class="zion-eu-topbar"><a class="zion-eu-brand" href="<?php echo esc_url(admin_url('admin.php?page=zion-eu-withdrawal')); ?>"><span class="zion-eu-brand-mark">Z</span><span><strong>Zion</strong><small>EU Withdrawal</small></span></a><span class="zion-eu-version">v<?php echo esc_html(ZION_EU_WITHDRAWAL_VERSION); ?></span></div><main class="zion-eu-content">
        <?php
    }
}
