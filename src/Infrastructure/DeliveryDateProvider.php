<?php

declare(strict_types=1);

namespace Zion\EuWithdrawal\Infrastructure;

interface DeliveryDateProvider
{
    public function id(): string;

    public function get_delivery_date(mixed $order): ?\DateTimeImmutable;
}
