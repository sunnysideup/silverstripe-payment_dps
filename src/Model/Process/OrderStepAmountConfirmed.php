<?php

namespace Sunnysideup\PaymentDps\Model\Process;

use SilverStripe\Control\Controller;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\Forms\CheckboxField;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\LiteralField;
use SilverStripe\Forms\ReadonlyField;
use SilverStripe\ORM\DB;
use Sunnysideup\Ecommerce\Email\OrderInvoiceEmail;
use Sunnysideup\Ecommerce\Interfaces\OrderStepInterface;
use Sunnysideup\Ecommerce\Model\Money\EcommercePayment;
use Sunnysideup\Ecommerce\Model\Order;
use Sunnysideup\Ecommerce\Model\Process\OrderStep;
use Sunnysideup\PaymentDps\DpsPxPayPaymentRandomAmount;
use Sunnysideup\PaymentDps\Forms\CustomerOrderStepForm;

/**
 * Class \Sunnysideup\PaymentDps\Model\Process\OrderStepAmountConfirmed
 *
 * @property float $MinimumAmountUnknownCustomers
 * @property float $MinimumAmountKnownCustomers
 * @property bool $SendMessageToCustomer
 * @property string $Heading
 * @property string $Explanation
 * @property string $ThankYou
 */
class OrderStepAmountConfirmed extends OrderStep implements OrderStepInterface
{
    protected $emailClassName = OrderInvoiceEmail::class;

    private static $table_name = 'OrderStepAmountConfirmed';

    private static $custom_exceptions_class = '';

    private static $step_logic_conditions = [
        'hasBeenDone' => true,
    ];

    private static $db = [
        'MinimumAmountUnknownCustomers' => 'Currency',
        'MinimumAmountKnownCustomers' => 'Currency',
        'SendMessageToCustomer' => 'Boolean',
        'Heading' => 'Varchar',
        'Explanation' => 'HTMLText',
        'ThankYou' => 'Varchar(255)',
    ];

    private static $casting = [
        'MinimumAmountUnknownCustomersRaw' => 'Float',
        'MinimumAmountKnownCustomersRaw' => 'Float',
    ];

    private static $defaults = [
        'CustomerCanEdit' => 0,
        'CustomerCanCancel' => 0,
        //the one below may seem a bit paradoxical, but the thing is that the customer can pay up to and inclusive of this step
        //that ist he code PAID means that the Order has been paid ONCE this step is completed
        'CustomerCanPay' => 0,
        'Name' => 'Confirm Amount',
        'Code' => 'AMOUNTCONFIRMED',
        'ShowAsInProcessOrder' => 1,
        'Explanation' => '
            <p>
            Due to the amount paid, you will need to pass a fraud check to proceed with this order.
            Please check your credit card / bank statement to confirm the amount that was charged.
            </p>
        ',
        'ThankYou' => 'Thank you for your confirmation.',
    ];

    public function getMinimumAmountUnknownCustomersRaw(): float
    {
        return self::currency_to_float($this->MinimumAmountUnknownCustomers);
    }

    public function getMinimumAmountKnownCustomersRaw(): float
    {
        return self::currency_to_float($this->MinimumAmountKnownCustomers);
    }

    public static function currency_to_float($value): float
    {
        return (float) preg_replace('/[^0-9.\-]/', '', (string) $value);
    }

    public function getCMSFields()
    {
        $fields = parent::getCMSFields();
        $fields->addFieldsToTab(
            'Root.Settings',
            [
                $fields->dataFieldByName('MinimumAmountUnknownCustomers'),
                $fields->dataFieldByName('MinimumAmountKnownCustomers'),
                $fields->dataFieldByName('Heading'),
                $fields->dataFieldByName('Explanation'),
                $fields->dataFieldByName('ThankYou'),
            ]
        );
        $fields->addFieldsToTab(
            'Root.CustomerMessage',
            [
                CheckboxField::create('SendMessageToCustomer', 'Send message to customer'),
            ],
            'EmailSubject'
        );

        return $fields;
    }

    /**
     * A form that can be used by the Customer to progress step!
     *
     * @return null|\SilverStripe\Forms\Form (CustomerOrderStepForm)
     */
    public function CustomerOrderStepForm(Controller $controller, string $name, Order $order)
    {
        return CustomerOrderStepForm::create($controller, $name, $order);
    }

    /**
     *initStep:
     * makes sure the step is ready to run.... (e.g. check if the order is ready to be emailed as receipt).
     * should be able to run this function many times to check if the step is ready.
     *
     * @see Order::doNextStatus
     *
     * @param Order $order object
     *
     * @return bool - true if the current step is ready to be run...
     */
    public function initStep(Order $order): bool
    {
        return true;
    }

    /**
     *doStep:
     * should only be able to run this function once
     * (init stops you from running it twice - in theory....)
     * runs the actual step.
     *
     * @see Order::doNextStatus
     *
     * @param Order $order object
     *
     * @return bool - true if run correctly
     */
    public function doStep(Order $order): bool
    {
        $adminOnlyOrToEmail = ! (bool) $this->SendMessageToCustomer;
        if (true === $this->stillToDo($order)) {
            return $this->sendEmailForStep(
                $order,
                (string) $subject = $this->EmailSubject ?: 'Confirm Paid Amount',
                (string) $this->CalculatedCustomerMessage(),
                $resend = false,
                $adminOnlyOrToEmail,
                $this->getEmailClassName()
            );
        }

        return true;
    }

    public function hasBeenDone(Order $order): bool
    {
        return $this->stillToDo($order) ? false : true;
    }

    /**
     * Allows the opportunity for the Order Step to add any fields to Order::getCMSFields.
     *
     * @return \SilverStripe\Forms\FieldList
     */
    public function addOrderStepFields(FieldList $fields, Order $order, ?bool $nothingToDo = false)
    {
        $fields = parent::addOrderStepFields($fields, $order);
        if (! $this->hasAmountConfirmed($order)) {
            $msg = _t(
                'OrderStep.AMOUNTNOTCONFIRMED',
                '
                    This order can not be completed, because it requires the customer to confirm the amount
                    deducted from their credit card.
                    They can confirm the amount paid from the <a href="' . $order->Link() . '">Order Confirmation Page</a>.
                '
            );
            $fields->addFieldsToTab(
                'Root.Next',
                [
                    new LiteralField('NotPaidMessage', '<p>' . $msg . '</p>'),
                    ReadonlyField::create(
                        'AmountToBeConfirmd',
                        'Amount to be confirmed',
                        $this->AmountToBeConfirmed($order)
                    ),
                ]
            );
        }

        return $fields;
    }

    public function hasAmountValidation($order): bool
    {
        $customExceptionsClass = $this->Config()->get('custom_exceptions_class');
        if (class_exists($customExceptionsClass)) {
            $class = Injector::inst()->get($customExceptionsClass);
            if (true === $class->notRequired($order)) {
                return false;
            }
        }
        foreach ($order->Payments() as $payment) {
            if ($payment) {
                if ($payment instanceof DpsPxPayPaymentRandomAmount) {
                    return $payment->RandomDeduction > 0;
                }
            }
        }

        return false;
    }

    public function relevantPayments(Order $order)
    {
        return $order->Payments()->filter(
            [
                'ClassName' => DpsPxPayPaymentRandomAmount::class,
                'Status' => EcommercePayment::SUCCESS_STATUS,
            ]
        );
    }

    public function hasAmountConfirmed(Order $order): bool
    {
        return OrderStepAmountConfirmedLog::has_been_confirmed($order);
    }

    public function requireDefaultRecords()
    {
        parent::requireDefaultRecords();
        DB::query('
            UPDATE "OrderStep"
            SET "ClassName" = \'' . addslashes('Sunnysideup\\PaymentDps\\Model\\Process\\OrderStepAmountConfirmed') . '\'
            WHERE "ClassName" = \'' . addslashes('Sunnysideup\\PaymentDps\\Forms\\Process\\OrderStepAmountConfirmed') . '\'
        ');
    }

    /**
     * @return bool
     */
    public function hasCustomerMessage()
    {
        return $this->SendMessageToCustomer;
    }

    protected function canBeDefered()
    {
        return false;
    }

    protected function stillToDo(Order $order): bool
    {
        return $this->hasAmountValidation($order) && ! $this->hasAmountConfirmed($order);
    }

    protected function AmountToBeConfirmed($order)
    {
        /**
         * @var  DpsPxPayPaymentRandomAmount $firstPayment
         */
        $firstPayment = $this->relevantPayments($order)->first();
        if ($firstPayment) {
            return $firstPayment->SettlementAmount;
        }

        return 'please check payments tab for more details.';
    }

    /**
     * Explains the current order step.
     *
     * @return string
     */
    protected function myDescription()
    {
        return _t('OrderStep.PAID_DESCRIPTION', 'The order amount charged is confirmed by customer.');
    }
}
