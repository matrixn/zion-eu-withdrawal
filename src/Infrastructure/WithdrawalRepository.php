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
            'eligibility_snapshot' => $data['eligibility_snapshot'] ?? null,
            'delivery_date' => $data['delivery_date'] ?? null,
            'withdrawal_period_start' => $data['withdrawal_period_start'] ?? null,
            'estimated_deadline' => $data['estimated_deadline'] ?? null,
            'legal_exception_code' => $data['legal_exception_code'] ?? null,
            'merchant_notes' => $data['merchant_notes'] ?? null,
            'created_at' => $now,
            'updated_at' => $now,
        ];

        $formats = ['%s', '%d', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s'];

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
                'product_name' => (string) ($item['name'] ?? ''),
                'quantity' => (float) ($item['quantity'] ?? 1),
                'eligibility' => (string) ($item['eligibility'] ?? 'unknown'),
                'exception_code' => $item['exception_code'] ?? null,
                'eligibility_reason' => (string) ($item['eligibility_reason'] ?? ''),
                'created_at' => gmdate('Y-m-d H:i:s'),
            ], ['%d', '%d', '%d', '%s', '%f', '%s', '%s', '%s', '%s']);
        }
    }

    public function generate_withdrawal_id(): string
    {
        return 'ZWE-' . gmdate('Ymd') . '-' . strtoupper(wp_generate_password(8, false, false));
    }

    /** @return array<int, array<string, mixed>> */
    public function all(string $status = '', int $limit = 50, int $offset = 0, string $search = '', string $source = ''): array
    {
        global $wpdb;

        $table = $this->database->table_names()['withdrawals'];
        $limit = max(1, min(200, $limit));
        $offset = max(0, $offset);
        $where = '';
        $args = [];

        $conditions = [];
        if ($status !== '') {
            $conditions[] = 'status = %s';
            $args[] = $status;
        }
        if ($source !== '') {
            $conditions[] = 'source = %s';
            $args[] = $source;
        }
        if ($search !== '') {
            $like = '%' . $wpdb->esc_like($search) . '%';
            $conditions[] = '(withdrawal_id LIKE %s OR customer_name LIKE %s OR customer_email LIKE %s OR contract_reference LIKE %s)';
            array_push($args, $like, $like, $like, $like);
        }
        $where = $conditions === [] ? '' : ' WHERE ' . implode(' AND ', $conditions);

        $args[] = $limit;
        $args[] = $offset;
        $query = "SELECT * FROM {$table}{$where} ORDER BY created_at DESC, id DESC LIMIT %d OFFSET %d";

        return (array) $wpdb->get_results($wpdb->prepare($query, $args), ARRAY_A);
    }

    public function count(string $status = ''): int
    {
        global $wpdb;

        $table = $this->database->table_names()['withdrawals'];
        if ($status === '') {
            return (int) $wpdb->get_var("SELECT COUNT(*) FROM {$table}");
        }

        return (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$table} WHERE status = %s", $status));
    }

    public function update(int $id, string $status, string $merchant_notes): bool
    {
        global $wpdb;

        $updated = $wpdb->update(
            $this->database->table_names()['withdrawals'],
            ['status' => sanitize_key($status), 'merchant_notes' => sanitize_textarea_field($merchant_notes), 'updated_at' => gmdate('Y-m-d H:i:s')],
            ['id' => $id],
            ['%s', '%s', '%s'],
            ['%d']
        );

        return $updated !== false;
    }

    /** @return array<string, mixed>|null */
    public function find_for_user(string $withdrawal_id, int $user_id): ?array
    {
        global $wpdb;

        $row = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$this->database->table_names()['withdrawals']} WHERE withdrawal_id = %s AND user_id = %d LIMIT 1",
            $withdrawal_id,
            $user_id
        ), ARRAY_A);

        return is_array($row) ? $row : null;
    }

    /** @return array<int, array<string, mixed>> */
    public function for_user(int $user_id, int $limit = 50, int $offset = 0): array
    {
        global $wpdb;

        $limit = max(1, min(100, $limit));
        return (array) $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$this->database->table_names()['withdrawals']} WHERE user_id = %d ORDER BY created_at DESC, id DESC LIMIT %d OFFSET %d",
            $user_id,
            $limit,
            max(0, $offset)
        ), ARRAY_A);
    }

    /** @return array<string, mixed>|null */
    public function find_by_id(int $id): ?array
    {
        global $wpdb;

        $row = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$this->database->table_names()['withdrawals']} WHERE id = %d LIMIT 1",
            $id
        ), ARRAY_A);

        return is_array($row) ? $row : null;
    }

    /** @return array<int, array<string, mixed>> */
    public function items_for(int $withdrawal_id): array
    {
        global $wpdb;

        return (array) $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$this->database->table_names()['items']} WHERE withdrawal_id = %d ORDER BY id ASC",
            $withdrawal_id
        ), ARRAY_A);
    }

    /** @return array<string, int|string> */
    public function statistics(): array
    {
        global $wpdb;

        $table = $this->database->table_names()['withdrawals'];
        return [
            'total' => $this->count(),
            'last_30_days' => (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$table} WHERE created_at >= %s", gmdate('Y-m-d H:i:s', time() - (30 * DAY_IN_SECONDS)))),
            'guest' => (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$table} WHERE source IN (%s, %s)", 'guest', 'public')),
            'account' => (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$table} WHERE source = %s", 'account')),
            'latest' => (string) ($wpdb->get_var("SELECT created_at FROM {$table} ORDER BY created_at DESC LIMIT 1") ?: '-'),
        ];
    }
}
