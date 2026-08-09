<?php

declare(strict_types=1);

namespace Zion\EuWithdrawal\Legal;

interface LegalProfile
{
    public function id(): string;

    public function version(): string;

    public function country_code(): string;

    public function standard_period_days(): int;

    /** @return array<string, array<string, string>> */
    public function exceptions(): array;

    /** @return array<string, string> */
    public function start_date_rules(): array;

    /** @return array<string, string> */
    public function mandatory_fields(): array;
}
