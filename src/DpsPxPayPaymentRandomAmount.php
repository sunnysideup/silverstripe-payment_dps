<?php

namespace Sunnysideup\PaymentDps;

/**
 * Class \Sunnysideup\PaymentDps\DpsPxPayPaymentRandomAmount
 *
 * @property float $RandomDeduction
 */
class DpsPxPayPaymentRandomAmount extends DpsPxPayPayment
{
    private static $max_random_deduction = 1;

    private static $db = [
        'RandomDeduction' => 'Currency',
    ];

    private static $table_name = 'DpsPxPayPaymentRandomAmount';

    protected function hasRandomDeduction(): bool
    {
        return true;
    }

    protected function setAndReturnRandomDeduction(): float
    {
        $max = $this->Config()->get('max_random_deduction');
        // random_int: the amount must not be predictable, the customer has to see it on their statement.
        $amount = round(((float) $max) * (random_int(0, 100) / 100), 2);
        $this->RandomDeduction = $amount;
        $this->write();

        return floatval($this->RandomDeduction);
    }
}
