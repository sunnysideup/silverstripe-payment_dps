<?php
use Sunnysideup\Ecommerce\Model\Process\OrderStatusLog;


class OrderStepAmountConfirmedLog extends OrderStatusLog
{

    private static $db = [
        'IsValid' => 'Boolean',
    ];

    public function i18n_singular_name()
    {
        return 'Amount Validated Log';
    }

    public function i18n_plural_name()
    {
        return 'Amount Validated Logs';
    }


    public static function is_locked_out($order) : bool
    {
        $logs = OrderStepAmountConfirmedLog::get()->filter(
            [
                'OrderID' => $order->ID,
                'Created:GreaterThan' => date('Y-m-d h:i', strtotime('1 day')),
            ]
        )->count() > 3 ? true : false;
    }

    /**
     *
     * @param  Order $order  [description]
     * @param  string $answer [description]
     * @return bool           [description]
     */
    public static function test_answer($order, $answer) : bool
    {
        if(self::is_rigth_step($order)) {
            if($order->Status()->hasAmountConfirmed($order)) {
                return true;
            }
            $isValid = false;

            $log = OrderStepAmountConfirmedLog::create(
                ['OrderID' => $orderID, 'Answer' => floatval($answer)]
            );
            $payment = $order->Status()->relevantPayments()->Last();
            if($payment) {
                $amount = (float) $payment->Amount->Amount;
                $deduction = (float) $payment->RandomDeduction;
                $expectedAnswer = (float) $amount - $deduction;
                if($expectedAnswer == $answer) {
                    $isValid = true;
                }
            }
            $log->IsValid = $isValid;
            $log->write();
        } else {
            return true;
        }
    }


    protected static function is_rigth_step($order) : bool
    {
        $status = $order->Status();

        return $status instanceof OrderStepAmountConfirmed;
    }

}
