<?php

declare(strict_types=1);

namespace Zion\EuWithdrawal\Integration;

final class WooCommerceDetector
{
    public function is_available(): bool
    {
        return class_exists('WooCommerce') || function_exists('WC');
    }

    public function message(string $language = 'ro'): string
    {
        return $language === 'en'
            ? 'WooCommerce is not active. The legal profile and settings remain available, but the order workflow is waiting for WooCommerce.'
            : 'WooCommerce nu este activ. Profilul juridic și setările rămân disponibile, dar fluxul comenzilor așteaptă activarea WooCommerce.';
    }
}
