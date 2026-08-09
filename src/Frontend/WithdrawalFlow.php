<?php

declare(strict_types=1);

namespace Zion\EuWithdrawal\Frontend;

use Zion\EuWithdrawal\Infrastructure\GuestTokenRepository;
use Zion\EuWithdrawal\Infrastructure\NotificationService;
use Zion\EuWithdrawal\Infrastructure\WithdrawalRepository;
use Zion\EuWithdrawal\Internationalization\LocaleManager;
use Zion\EuWithdrawal\Legal\LegalProfile;
use Zion\EuWithdrawal\Legal\EligibilityEngine;

final class WithdrawalFlow
{
    private const REVIEW_TTL = 900;

    public function __construct(
        private readonly OrderLookup $orders,
        private readonly WithdrawalRepository $repository,
        private readonly GuestTokenRepository $guest_tokens,
        private readonly PageManager $pages,
        private readonly LegalProfile $profile,
        private readonly LocaleManager $locale,
        private readonly EligibilityEngine $eligibility,
        private readonly NotificationService $notifications
    ) {
    }

    public function register_hooks(): void
    {
        add_action('wp_ajax_zion_eu_begin_withdrawal', [$this, 'ajax_begin']);
        add_action('wp_ajax_nopriv_zion_eu_begin_withdrawal', [$this, 'ajax_begin']);
        add_action('wp_ajax_zion_eu_confirm_withdrawal', [$this, 'ajax_confirm']);
        add_action('wp_ajax_nopriv_zion_eu_confirm_withdrawal', [$this, 'ajax_confirm']);
        add_action('wp_ajax_zion_eu_request_guest_link', [$this, 'ajax_request_guest_link']);
        add_action('wp_ajax_nopriv_zion_eu_request_guest_link', [$this, 'ajax_request_guest_link']);
    }

    public function ajax_request_guest_link(): void
    {
        check_ajax_referer('zion_eu_public_withdrawal', 'nonce');

        $email = sanitize_email((string) wp_unslash($_POST['customer_email'] ?? ''));
        $reference = sanitize_text_field((string) wp_unslash($_POST['order_reference'] ?? ''));
        $generic = $this->locale->text(
            'Dacă datele corespund unei comenzi, vei primi în scurt timp un link securizat pe e-mail.',
            'If the details match an order, you will receive a secure link by e-mail shortly.'
        );

        if (! is_email($email) || $reference === '' || ! $this->within_rate_limit($email)) {
            wp_send_json_success(['message' => $generic]);
        }

        $order = $this->orders->find($reference, $email, null);
        if (! $order) {
            wp_send_json_success(['message' => $generic]);
        }

        $this->guest_tokens->revoke_for_order((int) $order->get_id(), $email);
        $raw_token = wp_generate_password(64, false, false);
        $settings = (array) get_option('zion_eu_withdrawal_settings', []);
        $ttl_minutes = max(5, min(1440, (int) ($settings['guest_link_ttl_minutes'] ?? 30)));
        $expires_at = gmdate('Y-m-d H:i:s', time() + ($ttl_minutes * MINUTE_IN_SECONDS));
        $this->guest_tokens->create($raw_token, (int) $order->get_id(), $email, $expires_at);
        $link = add_query_arg('guest_token', rawurlencode($raw_token), $this->pages->public_url());
        $subject = sanitize_text_field((string) ($settings['guest_email_subject'] ?? 'Secure withdrawal link'));
        $body = $this->locale->text(
            "Ai solicitat un link securizat pentru retragerea din contract.\n\nDeschide linkul pentru a continua:\n{$link}\n\nLinkul expiră la {$expires_at} UTC și o cerere nouă revocă linkul anterior.",
            "You requested a secure link to withdraw from a contract.\n\nOpen the link to continue:\n{$link}\n\nThe link expires at {$expires_at} UTC and a new request revokes the previous link."
        );
        wp_mail($email, $subject, $body);
        wp_send_json_success(['message' => $generic]);
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
        $guest_token = sanitize_text_field((string) wp_unslash($_POST['guest_token'] ?? ''));
        $user_id = is_user_logged_in() ? get_current_user_id() : null;

        $guest_row = $guest_token !== '' ? $this->guest_tokens->find($guest_token) : null;
        if ($guest_token !== '' && ! $guest_row) {
            $this->error($this->locale->text('Linkul securizat a expirat. Solicită unul nou.', 'The secure link expired. Request a new one.'), 410);
        }

        if (is_array($guest_row)) {
            $reference = (string) $guest_row['order_id'];
            $guest_order = wc_get_order((int) $guest_row['order_id']);
            $email = $email !== '' ? $email : (string) ($guest_order ? $guest_order->get_billing_email() : '');
            $name = $name !== '' ? $name : (string) ($guest_order && method_exists($guest_order, 'get_formatted_billing_full_name') ? $guest_order->get_formatted_billing_full_name() : '');
        }

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
        $eligibility = $this->eligibility->evaluate_order($order, $items);
        $items = $eligibility['items'];
        $review_token = wp_generate_uuid4();
        $review_data = [
            'order_id' => (int) $order->get_id(),
            'user_id' => (int) ($user_id ?? 0),
            'customer_name' => $name,
            'customer_email' => $email,
            'customer_phone' => $phone,
            'order_reference' => (string) $order->get_order_number(),
            'statement' => '',
            'source' => is_array($guest_row) ? 'guest' : ($user_id ? 'account' : 'public'),
            'guest_token' => $guest_token,
            'items' => $items,
            'eligibility' => $eligibility,
            'created_at' => time(),
        ];
        set_transient($this->review_key($review_token), $review_data, self::REVIEW_TTL);

        wp_send_json_success([
            'review_token' => $review_token,
            'order' => [
                'reference' => (string) $order->get_order_number(),
                'items' => array_map(static fn (array $item): array => ['name' => (string) $item['name'], 'quantity' => $item['quantity']], $items),
                'eligibility' => [
                    'overall' => (string) $eligibility['overall'],
                    'delivery_date' => $eligibility['delivery_date'],
                    'estimated_deadline' => $eligibility['estimated_deadline'],
                ],
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
                'start_date' => $review['eligibility']['period_start'] ?? null,
                'deadline_date' => $review['eligibility']['estimated_deadline'] ?? null,
                'delivery_date' => $review['eligibility']['delivery_date'] ?? null,
                'withdrawal_period_start' => $review['eligibility']['period_start'] ?? null,
                'estimated_deadline' => $review['eligibility']['estimated_deadline'] ?? null,
                'legal_exception_code' => $this->first_exception_code($review['eligibility']['items'] ?? []),
                'eligibility_snapshot' => wp_json_encode($review['eligibility'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE),
                'created_at' => $now,
            ]);
            $this->repository->create_items($row_id, $review['items']);
            if (! empty($review['guest_token'])) {
                $this->guest_tokens->mark_used((string) $review['guest_token']);
            }
        } catch (\Throwable $exception) {
            $this->error($this->locale->text('Declarația nu a putut fi salvată. Nu reîncărca pagina și încearcă din nou.', 'The statement could not be saved. Do not reload the page and try again.'), 500);
        }

        $saved = $this->repository->find_by_id($row_id);
        if (is_array($saved)) {
            $settings = (array) get_option('zion_eu_withdrawal_settings', []);
            $consumer_sent = $this->notifications->send_consumer_confirmation($saved);
            $admin_sent = $this->notifications->send_admin_confirmation($saved);
            $delivery_failed = (! empty($settings['send_consumer_email']) && ! $consumer_sent) || (! empty($settings['send_admin_email']) && ! $admin_sent);
            if ($delivery_failed) {
                $this->repository->update($row_id, 'notification_failed', 'Una sau mai multe notificari nu au putut fi livrate. Verifica logul si retransmite.');
            }
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
            'Eligibility snapshot: ' . wp_json_encode($review['eligibility'] ?? [], JSON_UNESCAPED_UNICODE),
        ];

        return implode("\n", $lines);
    }

    /** @param array<int, array<string, mixed>> $items */
    private function first_exception_code(array $items): ?string
    {
        foreach ($items as $item) {
            if (! empty($item['exception_code'])) {
                return (string) $item['exception_code'];
            }
        }

        return null;
    }

    private function error(string $message, int $status): never
    {
        wp_send_json_error(['message' => $message], $status);
    }
}
