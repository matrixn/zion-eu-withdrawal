<?php

declare(strict_types=1);

namespace Zion\EuWithdrawal\Admin;

use Zion\EuWithdrawal\Infrastructure\NotificationService;
use Zion\EuWithdrawal\Infrastructure\WithdrawalRepository;
use Zion\EuWithdrawal\Internationalization\LocaleManager;

final class NotificationsPage
{
    public function __construct(
        private readonly NotificationService $notifications,
        private readonly WithdrawalRepository $repository,
        private readonly LocaleManager $locale
    ) {
    }

    public function register_menu(): void
    {
        add_submenu_page('zion-eu-withdrawal', $this->locale->text('Notificari', 'Notifications'), $this->locale->text('Notificari', 'Notifications'), 'manage_options', 'zion-eu-withdrawal-notifications', [$this, 'render']);
    }

    public function render(): void
    {
        $status = sanitize_key((string) wp_unslash($_GET['status'] ?? ''));
        $rows = $this->notifications->all($status);
        ?><div class="wrap zion-eu-admin"><div class="zion-eu-topbar"><a class="zion-eu-brand" href="<?php echo esc_url(admin_url('admin.php?page=zion-eu-withdrawal')); ?>"><span class="zion-eu-brand-mark">Z</span><span><strong>Zion</strong><small>EU Withdrawal</small></span></a><span class="zion-eu-version">v<?php echo esc_html(ZION_EU_WITHDRAWAL_VERSION); ?></span></div><main class="zion-eu-content"><div class="zion-eu-section-title"><div><span class="zion-eu-eyebrow"><?php echo esc_html($this->locale->text('Livrare e-mail', 'E-mail delivery')); ?></span><h1><?php echo esc_html($this->locale->text('Notificari', 'Notifications')); ?></h1><p class="zion-eu-admin-muted"><?php echo esc_html($this->locale->text('Istoricul confirmarilor, erorilor si retransmiterilor pentru fiecare retragere.', 'History of confirmations, errors and resends for each withdrawal.')); ?></p></div></div><div class="zion-eu-card zion-eu-table-card"><div class="zion-eu-table-toolbar"><strong><?php echo esc_html($this->locale->text('Log notificari', 'Notification log')); ?></strong><form method="get"><input type="hidden" name="page" value="zion-eu-withdrawal-notifications"><select name="status"><option value=""><?php echo esc_html($this->locale->text('Toate statusurile', 'All statuses')); ?></option><option value="sent" <?php selected($status, 'sent'); ?>>Sent</option><option value="failed" <?php selected($status, 'failed'); ?>>Failed</option></select><button class="zion-eu-filter-button" type="submit"><?php echo esc_html($this->locale->text('Filtreaza', 'Filter')); ?></button></form></div><?php if ($rows === []) : ?><div class="zion-eu-empty-state"><h2><?php echo esc_html($this->locale->text('Nu exista loguri de notificare', 'No notification logs')); ?></h2></div><?php else : ?><div class="zion-eu-table-wrap"><table class="zion-eu-requests-table"><thead><tr><th><?php echo esc_html($this->locale->text('Tip', 'Type')); ?></th><th><?php echo esc_html($this->locale->text('Retragere', 'Withdrawal')); ?></th><th><?php echo esc_html($this->locale->text('Destinatar', 'Recipient')); ?></th><th><?php echo esc_html($this->locale->text('Status', 'Status')); ?></th><th><?php echo esc_html($this->locale->text('Data', 'Date')); ?></th></tr></thead><tbody><?php foreach ($rows as $row) : $withdrawal = $this->repository->find_by_id((int) $row['withdrawal_id']); ?><tr><td><?php echo esc_html((string) $row['notification_type']); ?></td><td><?php if ($withdrawal) : ?><a class="zion-eu-request-id" href="<?php echo esc_url(add_query_arg(['page' => 'zion-eu-withdrawal-requests', 'withdrawal_id' => (int) $row['withdrawal_id']], admin_url('admin.php'))); ?>"><?php echo esc_html((string) $withdrawal['withdrawal_id']); ?></a><?php else : ?>—<?php endif; ?></td><td><?php echo esc_html((string) $row['recipient']); ?></td><td><span class="zion-eu-status-pill"><?php echo esc_html((string) $row['status']); ?></span><?php if (! empty($row['error_message'])) : ?><small><?php echo esc_html((string) $row['error_message']); ?></small><?php endif; ?></td><td><?php echo esc_html((string) $row['created_at']); ?></td></tr><?php endforeach; ?></tbody></table></div><?php endif; ?></div></main></div><?php
    }
}
