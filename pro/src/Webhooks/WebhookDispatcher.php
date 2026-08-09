<?php

declare(strict_types=1);

namespace Zion\EuWithdrawalPro\Webhooks;

use Zion\EuWithdrawalPro\Infrastructure\ProSettings;

final class WebhookDispatcher
{
    public function __construct(private readonly ProSettings $settings)
    {
    }

    public function register_hooks(): void
    {
        add_action('zion_eu_withdrawal_created', [$this, 'created'], 10, 1);
        add_action('zion_eu_withdrawal_status_changed', [$this, 'status_changed'], 10, 2);
    }

    /** @param array<string, mixed> $withdrawal */
    public function created(array $withdrawal): void
    {
        $this->send('withdrawal.created', $withdrawal);
    }

    /** @param array<string, mixed> $withdrawal */
    public function status_changed(array $withdrawal, string $status): void
    {
        $withdrawal['new_status'] = $status;
        $this->send('withdrawal.status_changed', $withdrawal);
    }

    /** @param array<string, mixed> $payload */
    private function send(string $event, array $payload): void
    {
        $url = esc_url_raw((string) $this->settings->get('webhook_url', ''));
        $secret = (string) $this->settings->get('webhook_secret', '');
        $events = preg_split('/\R+/', (string) $this->settings->get('webhook_events', '')) ?: [];
        if ($url === '' || $secret === '' || ! in_array($event, array_map('trim', $events), true)) {
            return;
        }

        $body = wp_json_encode(['event' => $event, 'sent_at' => gmdate('c'), 'data' => $payload], JSON_UNESCAPED_UNICODE);
        if (! is_string($body)) {
            return;
        }
        $signature = hash_hmac('sha256', $body, $secret);
        wp_remote_post($url, [
            'timeout' => 8,
            'blocking' => false,
            'headers' => ['Content-Type' => 'application/json', 'X-Zion-Signature' => $signature],
            'body' => $body,
        ]);
    }
}
