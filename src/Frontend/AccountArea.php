<?php

declare(strict_types=1);

namespace Zion\EuWithdrawal\Frontend;

use Zion\EuWithdrawal\Infrastructure\WithdrawalRepository;
use Zion\EuWithdrawal\Internationalization\LocaleManager;

final class AccountArea
{
    public function __construct(
        private readonly WithdrawalRepository $repository,
        private readonly LocaleManager $locale
    ) {
    }

    public function register_hooks(): void
    {
        add_action('init', [$this, 'register_endpoint'], 25);
        add_filter('woocommerce_account_menu_items', [$this, 'add_menu_item']);
        add_action('woocommerce_account_retrageri_endpoint', [$this, 'render_endpoint']);
    }

    public function register_endpoint(): void
    {
        add_rewrite_endpoint('retrageri', EP_ROOT | EP_PAGES);

        if ((string) get_option('zion_eu_withdrawal_rewrite_version', '') !== ZION_EU_WITHDRAWAL_VERSION) {
            flush_rewrite_rules(false);
            update_option('zion_eu_withdrawal_rewrite_version', ZION_EU_WITHDRAWAL_VERSION, false);
        }
    }

    /** @param array<string, string> $items */
    public function add_menu_item(array $items): array
    {
        $result = [];
        foreach ($items as $key => $label) {
            if ($key === 'customer-logout') {
                $result['retrageri'] = $this->locale->text('Retrageri', 'Withdrawals');
            }
            $result[$key] = $label;
        }

        return $result;
    }

    public function render_endpoint(): void
    {
        $withdrawal_id = sanitize_text_field((string) wp_unslash($_GET['withdrawal'] ?? ''));
        $row = $withdrawal_id !== '' ? $this->repository->find_for_user($withdrawal_id, get_current_user_id()) : null;

        if ($row) {
            $this->render_detail($row);
            return;
        }

        $this->render_history();
    }

    private function render_history(): void
    {
        $rows = $this->repository->for_user(get_current_user_id());
        echo '<div class="zion-eu-account-withdrawals"><div class="zion-eu-account-heading"><div><span class="zion-eu-account-eyebrow">ZION / EU WITHDRAWAL</span><h2>' . esc_html($this->locale->text('Istoricul retragerilor', 'Withdrawal history')) . '</h2><p>' . esc_html($this->locale->text('Aici găsești declarațiile transmise din contul tău.', 'Here you can find statements submitted from your account.')) . '</p></div></div>';

        if ($rows === []) {
            echo '<div class="zion-eu-account-empty"><span>◌</span><strong>' . esc_html($this->locale->text('Nu ai transmis încă o retragere.', 'You have not submitted a withdrawal yet.')) . '</strong><p>' . esc_html($this->locale->text('Când vei confirma o declarație, aceasta va apărea aici.', 'Once you confirm a statement, it will appear here.')) . '</p></div></div>';
            return;
        }

        echo '<div class="zion-eu-account-list">';
        foreach ($rows as $row) {
            $url = add_query_arg('withdrawal', rawurlencode((string) $row['withdrawal_id']), wc_get_endpoint_url('retrageri'));
            echo '<a class="zion-eu-account-row" href="' . esc_url($url) . '"><span class="zion-eu-account-row-mark">✓</span><span class="zion-eu-account-row-main"><strong>' . esc_html((string) $row['withdrawal_id']) . '</strong><small>#' . esc_html((string) $row['contract_reference']) . ' · ' . esc_html((string) $row['created_at']) . ' UTC</small></span><span class="zion-eu-account-status">' . esc_html((string) $row['status']) . ' →</span></a>';
        }
        echo '</div></div>';
    }

    /** @param array<string, mixed> $row */
    private function render_detail(array $row): void
    {
        $back = wc_get_endpoint_url('retrageri');
        echo '<div class="zion-eu-account-withdrawals"><a class="zion-eu-account-back" href="' . esc_url($back) . '">← ' . esc_html($this->locale->text('Înapoi la istoric', 'Back to history')) . '</a><div class="zion-eu-account-heading"><div><span class="zion-eu-account-eyebrow">' . esc_html((string) $row['legal_profile_version']) . '</span><h2>' . esc_html((string) $row['withdrawal_id']) . '</h2><p>' . esc_html($this->locale->text('Declarația ta a fost salvată cu timestamp server-side.', 'Your statement was saved with a server-side timestamp.')) . '</p></div><span class="zion-eu-account-status">' . esc_html((string) $row['status']) . '</span></div><div class="zion-eu-account-detail"><div><span>' . esc_html($this->locale->text('Comandă / contract', 'Order / contract')) . '</span><strong>#' . esc_html((string) $row['contract_reference']) . '</strong></div><div><span>' . esc_html($this->locale->text('Transmis la UTC', 'Submitted at UTC')) . '</span><strong>' . esc_html((string) $row['created_at']) . '</strong></div><div><span>' . esc_html($this->locale->text('Sursă', 'Source')) . '</span><strong>' . esc_html((string) $row['source']) . '</strong></div></div><div class="zion-eu-account-snapshot"><span>' . esc_html($this->locale->text('Dovada transmisă', 'Submitted evidence')) . '</span><pre>' . esc_html((string) $row['statement_content']) . '</pre></div></div>';
    }
}
