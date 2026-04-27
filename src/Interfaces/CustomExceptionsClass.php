<?php

declare(strict_types=1);

namespace Sunnysideup\PaymentDps\Interfaces;

use Sunnysideup\Ecommerce\Model\Order;

interface CustomExceptionsClass
{
    public function notRequired(Order $order): bool;
}
