<?php

declare(strict_types=1);

namespace Zion\EuWithdrawal\Infrastructure;

final class AuditRepository
{
    public function __construct(private readonly Database $database)
    {
    }

    /** @param array<string, mixed> $metadata */
    public function record(int $withdrawal_id, string $event_type, string $message, array $metadata = [], ?int $actor_user_id = null): void
    {
        global $wpdb;

        $wpdb->insert($this->database->table_names()['audit'], [
            'withdrawal_id' => $withdrawal_id,
            'event_type' => sanitize_key($event_type),
            'actor_user_id' => $actor_user_id ?: null,
            'message' => $message,
            'metadata' => $metadata === [] ? null : wp_json_encode($metadata),
            'created_at' => gmdate('Y-m-d H:i:s'),
        ], ['%d', '%s', '%d', '%s', '%s', '%s']);
    }

    /** @return array<int, array<string, mixed>> */
    public function for_withdrawal(int $withdrawal_id): array
    {
        global $wpdb;

        return (array) $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$this->database->table_names()['audit']} WHERE withdrawal_id = %d ORDER BY created_at DESC, id DESC",
            $withdrawal_id
        ), ARRAY_A);
    }
}
