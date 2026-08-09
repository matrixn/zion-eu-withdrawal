<?php

declare(strict_types=1);

namespace Zion\EuWithdrawal\Frontend;

use Zion\EuWithdrawal\Infrastructure\Database;
use Zion\EuWithdrawal\Infrastructure\WithdrawalRepository;
use Zion\EuWithdrawal\Internationalization\LocaleManager;
use Zion\EuWithdrawal\Legal\LegalProfile;

final class WithdrawalFlow
{
    private const REVIEW_TTL = 900;

    public function __construct(
        private readonly OrderLookup $orders,
        private readonly WithdrawalRepository $repository,
        private readonly LegalProfile $profile,
        private readonly LocaleManager $locale
    ) {
    }

    public function register_hooks(): void
    {
        add_action('wp_ajax_zion_eu_begin_withdrawal', [$this, 'ajax_begin']);
        add_action('wp_ajax_nopriv_zion_eu_begin_withdrawal', [$this, 'ajax_begin']);
        add_action('wp_ajax_zion_eu_confirm_withdrawal', [$this, 'ajax_confirm']);
        add_action('wp_ajax_nopriv_zion_eu_confirm_withdrawal', [$this, 'ajax_confirm']);
    }

    public function ajax_begin(): void
    {
        check_ajax_referer('zion_eu_public_withdrawal', 'nonce');

        if (! function_exists('wc_get_order')) {
            $this->error($this->locale->text('WooCommerce nu este disponibil.', 'WooCommerce is not available.'), 503);
        }

        $name = sanitize_text_field((string) wp_unslash($_POST['customer_name'] ?? ''));
        $email = sanitize_email((string) wp_unslash($_POST['customer_email'] ?? ''));
        $phone = sanitize_text_field((string) wp_unslash($_POST['customer_phone'] ?? ''));
        $reference = sanitize_text_field((string) wp_unslash($_POST['order_reference'] ?? ''));
        $user_id = is_user_logged_in() ? get_current_user_id() : null;

        if ($name === '' || ! is_email($email) || $reference === '') {
            $this->error($this->locale->text('Completează câmpurile obligatorii pentru a continua.', 'Complete the required fields to continue.'), 422);
        }

        if (! $this->within_rate_limit($email)) {
            $this->error($this->locale->text('Ai încercat de prea multe ori. Încearcă din nou mai târziu.', 'Too many attempts. Please try again later.'), 429);
        }

        $order = $this->orders->find($reference, $email, $user_id);
        if (! $order) {
            $this->error($this->locale->text('Nu am putut confirma combinația de date. Verifică numărul comenzii și e-mailul.', 'We could not confirm this combination of details. Check the order number and e-mail.'), 404);
        }

        $items = $this->orders->items($order);
        $review_token = wp_generate_uuid4();
        $review_data = [
            'order_id' => (int) $order->get_id(),
            'user_id' => (int) ($user_id ?? 0),
            'customer_name' => $name,
            'customer_email' => $email,
            'customer_phone' => $phone,
            'order_reference' => (string) $order->get_order_number(),
            'statement' => '',
            'source' => $user_id ? 'account' : 'public',
            'items' => $items,
            'created_at' => time(),
        ];
        set_transient($this->review_key($review_token), $review_data, self::REVIEW_TTL);

        wp_send_json_success([
            'review_token' => $review_token,
            'order' => [
                'reference' => (string) $order->get_order_number(),
                'items' => array_map(static fn (array $item): array => ['name' => (string) $item['name'], 'quantity' => $item['quantity']], $items),
            ],
            'message' => $this->locale->text('Contractul a fost identificat. Poți formula declarația.', 'The contract was identified. You can write the statement.'),
        ]);
    }

    public function ajax_confirm(): void
    {
        check_ajax_referer('zion_eu_public_withdrawal', 'nonce');

        $review_token = sanitize_text_field((string) wp_unslash($_POST['review_token'] ?? ''));
        $statement = sanitize_textarea_field((string) wp_unslash($_POST['statement'] ?? ''));
        $confirmation = sanitize_text_field((string) wp_unslash($_POST['confirmation'] ?? ''));
        $review = get_transient($this->review_key($review_token));

        if (! is_array($review) || $confirmation !== '1') {
            $this->error($this->locale->text('Revizuirea a expirat sau confirmarea nu a fost bifată.', 'The review expired or confirmation was not checked.'), 422);
        }

        $review['statement'] = $statement;
        $now = gmdate('Y-m-d H:i:s');
        $withdrawal_id = $this->repository->generate_withdrawal_id();
        $statement_snapshot = $this->snapshot($review, $withdrawal_id, $now);

        try {
            $row_id = $this->repository->create([
                'withdrawal_id' => $withdrawal_id,
                'order_id' => $review['order_id'],
                'user_id' => $review['user_id'],
                'customer_email' => $review['customer_email'],
                'customer_name' => $review['customer_name'],
                'customer_phone' => $review['customer_phone'],
                'contract_reference' => $review['order_reference'],
                'status' => 'submitted',
                'legal_profile' => $this->profile->id(),
                'legal_profile_version' => $this->profile->version(),
                'statement_content' => $statement_snapshot,
                'source' => $review['source'],
                'created_at' => $now,
            ]);
            $this->repository->create_items($row_id, $review['items']);
        } catch (\Throwable $exception) {
            $this->error($this->locale->text('Declarația nu a putut fi salvată. Nu reîncărca pagina și încearcă din nou.', 'The statement could not be saved. Do not reload the page and try again.'), 500);
        }

        delete_transient($this->review_key($review_token));

        wp_send_json_success([
            'withdrawal_id' => $withdrawal_id,
            'submitted_at' => $now,
            'message' => $this->locale->text('Declarația a fost salvată cu succes.', 'The statement was saved successfully.'),
        ]);
    }

    private function within_rate_limit(string $email): bool
    {
        $settings = (array) get_option('zion_eu_withdrawal_settings', []);
        $limit = max(1, (int) ($settings['rate_limit_per_hour'] ?? 5));
        $key = 'zion_eu_rate_' . hash_hmac('sha256', strtolower($email), wp_salt('nonce'));
        $count = (int) get_transient($key);

        if ($count >= $limit) {
            return false;
        }

        set_transient($key, $count + 1, HOUR_IN_SECONDS);
        return true;
    }

    private function review_key(string $token): string
    {
        return 'zion_eu_review_' . hash_hmac('sha256', $token, wp_salt('auth'));
    }

    /** @param array<string, mixed> $review */
    private function snapshot(array $review, string $withdrawal_id, string $now): string
    {
        $lines = [
            'ZION EU WITHDRAWAL — IMMUTABLE SUBMISSION SNAPSHOT',
            'Withdrawal ID: ' . $withdrawal_id,
            'Submitted at (UTC): ' . $now,
            'Legal profile: ' . $this->profile->version(),
            'Source: ' . $review['source'],
            'Consumer name: ' . $review['customer_name'],
            'Consumer e-mail: ' . $review['customer_email'],
            'Consumer phone: ' . $review['customer_phone'],
            'Contract / order: ' . $review['order_reference'],
            '',
            'DECLARATION OF WITHDRAWAL',
            'I communicate my unambiguous decision to withdraw from the contract identified above.',
            'Additional statement: ' . ($review['statement'] !== '' ? $review['statement'] : '[none]'),
            '',
            'The submission was saved server-side at the timestamp above. No legal eligibility or refund decision was made automatically.',
        ];

        return implode("\n", $lines);
    }

    private function error(string $message, int $status): never
    {
        wp_send_json_error(['message' => $message], $status);
    }
}
