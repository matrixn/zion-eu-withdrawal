<?php

declare(strict_types=1);

namespace Zion\EuWithdrawal\Internationalization;

final class LocaleManager
{
    public function language(): string
    {
        $settings = (array) get_option('zion_eu_withdrawal_settings', []);
        $configured = (string) ($settings['language'] ?? 'site');

        if ($configured === 'ro' || $configured === 'en') {
            return $configured;
        }

        return str_starts_with(strtolower((string) get_locale()), 'en') ? 'en' : 'ro';
    }

    public function text(string $romanian, string $english): string
    {
        return $this->language() === 'en' ? $english : $romanian;
    }
}
