<?php

namespace Sunnysideup\PaymentDps\Interfaces;

use Sunnysideup\Ecommerce\Model\Order;

interface CustomExceptionsClass
{
    public function notRequired(Order $order): bool;
}
