<?php

declare(strict_types=1);

namespace Zion\EuWithdrawal\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Zion\EuWithdrawal\Legal\ROLegalProfile;

final class ROLegalProfileTest extends TestCase
{
    public function test_profile_contains_the_phase_zero_contract(): void
    {
        $profile = new ROLegalProfile();

        self::assertSame('RO-2026.06.19-v1', $profile->version());
        self::assertSame(14, $profile->standard_period_days());
        self::assertCount(13, $profile->exceptions());
        self::assertArrayHasKey('goods', $profile->start_date_rules());
        self::assertArrayHasKey('confirmation', $profile->mandatory_fields());
    }
}
