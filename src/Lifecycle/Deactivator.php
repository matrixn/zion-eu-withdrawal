<?php

declare(strict_types=1);

namespace Zion\EuWithdrawal\Lifecycle;

final class Deactivator
{
    public static function deactivate(): void
    {
        // Datele și setările rămân intacte la dezactivare; ștergerea este opt-in la uninstall.
        flush_rewrite_rules();
    }
}
