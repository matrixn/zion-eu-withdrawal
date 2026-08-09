<?php

declare(strict_types=1);

namespace Zion\EuWithdrawal\Infrastructure;

final class GuestTokenRepository
{
    public function __construct(private readonly Database $database)
    {
    }

    public function create(string $raw_token, int $order_id, string $email, string $expires_at): int
    {
        global $wpdb;

        $table = $this->database->table_names()['guest_tokens'];
        $wpdb->insert($table, [
            'token_hash' => $this->hash_token($raw_token),
            'order_id' => $order_id,
            'email_hash' => $this->hash_email($email),
            'expires_at' => $expires_at,
            'used_at' => null,
            'created_at' => gmdate('Y-m-d H:i:s'),
        ], ['%s', '%d', '%s', '%s', '%s', '%s']);

        return (int) $wpdb->insert_id;
    }

    /** @return array<string, mixed>|null */
    public function find(string $raw_token): ?array
    {
        global $wpdb;

        $table = $this->database->table_names()['guest_tokens'];
        $row = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$table} WHERE token_hash = %s LIMIT 1",
            $this->hash_token($raw_token)
        ), ARRAY_A);

        if (! is_array($row) || ! empty($row['used_at']) || strtotime((string) $row['expires_at']) < time()) {
            return null;
        }

        return $row;
    }

    public function mark_used(string $raw_token): void
    {
        global $wpdb;

        $wpdb->update(
            $this->database->table_names()['guest_tokens'],
            ['used_at' => gmdate('Y-m-d H:i:s')],
            ['token_hash' => $this->hash_token($raw_token)],
            ['%s'],
            ['%s']
        );
    }

    public function revoke_for_order(int $order_id, string $email): void
    {
        global $wpdb;

        $wpdb->query($wpdb->prepare(
            "UPDATE {$this->database->table_names()['guest_tokens']} SET used_at = %s WHERE order_id = %d AND email_hash = %s AND used_at IS NULL",
            gmdate('Y-m-d H:i:s'),
            $order_id,
            $this->hash_email($email)
        ));
    }

    private function hash_token(string $token): string
    {
        return hash_hmac('sha256', $token, wp_salt('auth'));
    }

    private function hash_email(string $email): string
    {
        return hash_hmac('sha256', strtolower(trim($email)), wp_salt('nonce'));
    }
}
