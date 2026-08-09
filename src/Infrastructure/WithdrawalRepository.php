<?php

declare(strict_types=1);

namespace Zion\EuWithdrawal\Infrastructure;

final class WithdrawalRepository
{
    public function __construct(private readonly Database $database)
    {
    }

    /** @param array<string, mixed> $data */
    public function create(array $data): int
    {
        global $wpdb;

        $tables = $this->database->table_names();
        $now = (string) ($data['created_at'] ?? gmdate('Y-m-d H:i:s'));
        $row = [
            'withdrawal_id' => (string) $data['withdrawal_id'],
            'order_id' => (int) ($data['order_id'] ?? 0),
            'user_id' => (int) ($data['user_id'] ?? 0),
            'customer_email' => (string) $data['customer_email'],
            'customer_name' => (string) ($data['customer_name'] ?? ''),
            'customer_phone' => (string) ($data['customer_phone'] ?? ''),
            'contract_reference' => (string) ($data['contract_reference'] ?? ''),
            'status' => (string) ($data['status'] ?? 'submitted'),
            'legal_profile' => (string) $data['legal_profile'],
            'legal_profile_version' => (string) $data['legal_profile_version'],
            'statement_content' => (string) $data['statement_content'],
            'source' => (string) ($data['source'] ?? 'public'),
            'start_date' => $data['start_date'] ?? null,
            'deadline_date' => $data['deadline_date'] ?? null,
            'confirmation_token_hash' => $data['confirmation_token_hash'] ?? null,
            'merchant_notes' => $data['merchant_notes'] ?? null,
            'created_at' => $now,
            'updated_at' => $now,
        ];

        $formats = ['%s', '%d', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s'];

        if (false === $wpdb->insert($tables['withdrawals'], $row, $formats)) {
            throw new \RuntimeException('The withdrawal could not be persisted.');
        }

        return (int) $wpdb->insert_id;
    }

    /** @param array<int, array<string, mixed>> $items */
    public function create_items(int $withdrawal_id, array $items): void
    {
        global $wpdb;

        $table = $this->database->table_names()['items'];

        foreach ($items as $item) {
            $wpdb->insert($table, [
                'withdrawal_id' => $withdrawal_id,
                'order_item_id' => (int) ($item['order_item_id'] ?? 0),
                'product_id' => (int) ($item['product_id'] ?? 0),
                'quantity' => (float) ($item['quantity'] ?? 1),
                'eligibility' => 'unassessed',
                'exception_code' => null,
                'created_at' => gmdate('Y-m-d H:i:s'),
            ], ['%d', '%d', '%d', '%f', '%s', '%s', '%s']);
        }
    }

    public function generate_withdrawal_id(): string
    {
        return 'ZWE-' . gmdate('Ymd') . '-' . strtoupper(wp_generate_password(8, false, false));
    }
}
