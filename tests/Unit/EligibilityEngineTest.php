<?php

declare(strict_types=1);

namespace Zion\EuWithdrawal\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Zion\EuWithdrawal\Infrastructure\DeliveryDateProvider;
use Zion\EuWithdrawal\Legal\EligibilityEngine;
use Zion\EuWithdrawal\Legal\ROLegalProfile;

final class EligibilityEngineTest extends TestCase
{
    public function test_invalid_or_unknown_rules_never_become_an_automatic_refusal(): void
    {
        $provider = new class implements DeliveryDateProvider {
            public function id(): string { return 'test'; }
            public function get_delivery_date(mixed $order): ?\DateTimeImmutable { return null; }
        };
        $engine = new EligibilityEngine(new ROLegalProfile(), $provider);

        self::assertSame('unknown', $engine->classify_item(['state' => 'not-a-real-state'])['eligibility']);
        self::assertSame('potential_exception', $engine->classify_item(['state' => 'potential_exception', 'exception_code' => 'EXC-C'])['eligibility']);
        self::assertNull($engine->classify_item(['state' => 'potential_exception', 'exception_code' => 'EXC-NOT-DEFINED'])['exception_code']);
    }
}
