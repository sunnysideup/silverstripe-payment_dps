<?php

namespace Sunnysideup\PaymentDps;

use Override;

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

    #[Override]
    protected function hasRandomDeduction(): bool
    {
        return true;
    }

    #[Override]
    protected function setAndReturnRandomDeduction(): float
    {
        $max = $this->Config()->get('max_random_deduction');
        $amount = round($max * (mt_rand() / mt_getrandmax()), 2);
        $this->RandomDeduction = $amount;
        $this->write();

        return floatval($this->RandomDeduction);
    }
}
