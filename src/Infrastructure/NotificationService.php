<?php

declare(strict_types=1);

namespace Zion\EuWithdrawal\Infrastructure;

use Zion\EuWithdrawal\Internationalization\LocaleManager;

final class NotificationService
{
    public function __construct(
        private readonly Database $database,
        private readonly AuditRepository $audit,
        private readonly LocaleManager $locale
    ) {
    }

    /** @param array<string, mixed> $withdrawal */
    public function send_consumer_confirmation(array $withdrawal, bool $force = false): bool
    {
        $settings = (array) get_option('zion_eu_withdrawal_settings', []);
        if (! $force && empty($settings['send_consumer_email'])) {
            return false;
        }

        $recipient = sanitize_email((string) ($withdrawal['customer_email'] ?? ''));
        if (! is_email($recipient)) {
            return $this->log($withdrawal, 'consumer_confirmation', $recipient, '', 'failed', 0, 'Invalid consumer e-mail address.');
        }

        $id = (string) ($withdrawal['withdrawal_id'] ?? '');
        $subject = $this->subject((string) ($settings['consumer_email_subject'] ?? 'Withdrawal confirmation - {withdrawal_id}'), $id);
        $body = $this->consumer_body($withdrawal);
        return $this->deliver($withdrawal, 'consumer_confirmation', $recipient, $subject, $body);
    }

    /** @param array<string, mixed> $withdrawal */
    public function send_admin_confirmation(array $withdrawal, bool $force = false): bool
    {
        $settings = (array) get_option('zion_eu_withdrawal_settings', []);
        if (! $force && empty($settings['send_admin_email'])) {
            return false;
        }

        $recipients = $this->admin_recipients($settings);
        if ($recipients === []) {
            return $this->log($withdrawal, 'admin_confirmation', '', '', 'failed', 0, 'No valid administrator recipient configured.');
        }

        $id = (string) ($withdrawal['withdrawal_id'] ?? '');
        $subject = $this->subject((string) ($settings['admin_email_subject'] ?? 'New withdrawal - {withdrawal_id}'), $id);
        $body = $this->admin_body($withdrawal);
        return $this->deliver($withdrawal, 'admin_confirmation', implode(', ', $recipients), $subject, $body);
    }

    /** @param array<string, mixed> $withdrawal */
    public function resend(array $withdrawal, string $type): bool
    {
        return $type === 'admin_confirmation'
            ? $this->send_admin_confirmation($withdrawal, true)
            : $this->send_consumer_confirmation($withdrawal, true);
    }

    /** @return array<int, array<string, mixed>> */
    public function for_withdrawal(int $withdrawal_id): array
    {
        global $wpdb;

        return (array) $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$this->database->table_names()['notifications']} WHERE withdrawal_id = %d ORDER BY created_at DESC, id DESC",
            $withdrawal_id
        ), ARRAY_A);
    }

    /** @return array<int, array<string, mixed>> */
    public function all(string $status = '', int $limit = 100): array
    {
        global $wpdb;

        $limit = max(1, min(500, $limit));
        if ($status !== '') {
            return (array) $wpdb->get_results($wpdb->prepare(
                "SELECT * FROM {$this->database->table_names()['notifications']} WHERE status = %s ORDER BY created_at DESC, id DESC LIMIT %d",
                $status,
                $limit
            ), ARRAY_A);
        }

        return (array) $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$this->database->table_names()['notifications']} ORDER BY created_at DESC, id DESC LIMIT %d",
            $limit
        ), ARRAY_A);
    }

    /** @param array<string, mixed> $settings @return array<int, string> */
    private function admin_recipients(array $settings): array
    {
        $raw = trim((string) ($settings['admin_recipients'] ?? ''));
        if ($raw === '') {
            $raw = trim((string) ($settings['admin_email'] ?? ''));
        }
        if ($raw === '') {
            $raw = (string) get_option('admin_email', '');
        }

        $recipients = [];
        foreach (preg_split('/[,;\s]+/', $raw) ?: [] as $email) {
            $email = sanitize_email($email);
            if ($email !== '' && is_email($email)) {
                $recipients[] = $email;
            }
        }

        return array_values(array_unique($recipients));
    }

    private function subject(string $subject, string $withdrawal_id): string
    {
        return sanitize_text_field(str_replace('{withdrawal_id}', $withdrawal_id, $subject));
    }

    /** @param array<string, mixed> $withdrawal */
    private function consumer_body(array $withdrawal): string
    {
        return $this->locale->text(
            "Am primit declarația ta de retragere.\n\nID retragere: {$withdrawal['withdrawal_id']}\nTransmisă la (UTC): {$withdrawal['created_at']}\nComandă / contract: #{$withdrawal['contract_reference']}\n\nDovada exactă a declarației:\n{$withdrawal['statement_content']}\n\nPăstrează acest e-mail pentru evidențele tale.",
            "We received your withdrawal statement.\n\nWithdrawal ID: {$withdrawal['withdrawal_id']}\nSubmitted at (UTC): {$withdrawal['created_at']}\nOrder / contract: #{$withdrawal['contract_reference']}\n\nExact submission evidence:\n{$withdrawal['statement_content']}\n\nKeep this e-mail for your records."
        );
    }

    /** @param array<string, mixed> $withdrawal */
    private function admin_body(array $withdrawal): string
    {
        return $this->locale->text(
            "A fost transmisă o nouă retragere din contract.\n\nID: {$withdrawal['withdrawal_id']}\nClient: {$withdrawal['customer_name']} <{$withdrawal['customer_email']}>\nComandă / contract: #{$withdrawal['contract_reference']}\nSursă: {$withdrawal['source']}\nTransmisă la (UTC): {$withdrawal['created_at']}\n\nSnapshot:\n{$withdrawal['statement_content']}",
            "A new contract withdrawal was submitted.\n\nID: {$withdrawal['withdrawal_id']}\nCustomer: {$withdrawal['customer_name']} <{$withdrawal['customer_email']}>\nOrder / contract: #{$withdrawal['contract_reference']}\nSource: {$withdrawal['source']}\nSubmitted at (UTC): {$withdrawal['created_at']}\n\nSnapshot:\n{$withdrawal['statement_content']}"
        );
    }

    /** @param array<string, mixed> $withdrawal */
    private function deliver(array $withdrawal, string $type, string $recipient, string $subject, string $body): bool
    {
        $sent = wp_mail($recipient, $subject, $body);
        $status = $sent ? 'sent' : 'failed';
        $error = $sent ? '' : 'wp_mail returned false.';
        return $this->log($withdrawal, $type, $recipient, $subject, $status, 1, $error);
    }

    /** @param array<string, mixed> $withdrawal */
    private function log(array $withdrawal, string $type, string $recipient, string $subject, string $status, int $attempts, string $error): bool
    {
        global $wpdb;

        $now = gmdate('Y-m-d H:i:s');
        $wpdb->insert($this->database->table_names()['notifications'], [
            'withdrawal_id' => (int) ($withdrawal['id'] ?? 0),
            'notification_type' => $type,
            'recipient' => $recipient,
            'subject' => $subject,
            'status' => $status,
            'attempts' => $attempts,
            'error_message' => $error !== '' ? $error : null,
            'sent_at' => $status === 'sent' ? $now : null,
            'created_at' => $now,
            'updated_at' => $now,
        ], ['%d', '%s', '%s', '%s', '%s', '%d', '%s', '%s', '%s', '%s']);

        $this->audit->record((int) ($withdrawal['id'] ?? 0), 'notification_' . $status, $type . ($error !== '' ? ': ' . $error : ''), ['recipient' => $recipient]);
        return $status === 'sent';
    }
}
