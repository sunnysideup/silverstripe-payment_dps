<?php

namespace Sunnysideup\PaymentDps\Model\Process;

use Sunnysideup\PaymentDps\Model\Process\OrderStepAmountConfirmedLog;

use SilverStripe\ORM\DB;
use SilverStripe\Control\Controller;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\HeaderField;
use SilverStripe\Forms\LiteralField;
use Sunnysideup\Ecommerce\Api\ArrayMethods;
use Sunnysideup\Ecommerce\Interfaces\OrderStepInterface;
use Sunnysideup\Ecommerce\Model\Money\EcommercePayment;
use Sunnysideup\Ecommerce\Model\Order;
use Sunnysideup\Ecommerce\Model\Process\OrderStep;

use Sunnysideup\Ecommerce\Email\OrderInvoiceEmail;
use Sunnysideup\PaymentDps\DpsPxPayPaymentRandomAmount;
use Sunnysideup\PaymentDps\Forms\CustomerOrderStepForm;

/**
 * @authors: Nicolaas [at] Sunny Side Up .co.nz
 * @package: ecommerce
 * @sub-package: model
 */
class OrderStepAmountConfirmed extends OrderStep implements OrderStepInterface
{

    protected $emailClassName = OrderInvoiceEmail::class;

    private static $table_name = 'OrderStepAmountConfirmed';

    private static $db = [
        'MinimumAmountUnknownCustomers' => 'Currency',
        'MinimumAmountKnownCustomers' => 'Currency',
        'SendMessageToCustomer' => 'Boolean',
    ];

    private static $casting = [
        'MinimumAmountUnknownCustomersRaw' => 'Float',
        'MinimumAmountKnownCustomersRaw' => 'Float',
    ];

    public function getMinimumAmountUnknownCustomersRaw() : float
    {
        return $this->currencyToFloat($this->MinimumAmountUnknownCustomers);
    }

    public function getMinimumAmountKnownCustomersRaw() : float
    {
        return $this->currencyToFloat($this->MinimumAmountKnownCustomers);
    }

    protected function currencyToFloat($value) : float
    {
        return (float) preg_replace('/[^0-9.\-]/', '', $value);
    }

    private static $defaults = [
        'CustomerCanEdit' => 0,
        'CustomerCanCancel' => 0,
        //the one below may seem a bit paradoxical, but the thing is that the customer can pay up to and inclusive of this step
        //that ist he code PAID means that the Order has been paid ONCE this step is completed
        'CustomerCanPay' => 0,
        'Name' => 'Confirm Amount',
        'Code' => 'AMOUNTCONFIRMED',
        'ShowAsInProcessOrder' => 1,
    ];

    public function getCMSFields()
    {
        $fields = parent::getCMSFields();
        $fields->addFieldToTab(
            'Root.Main',
            HeaderField::create(
                'SendMessageToCustomerHeader',
                _t('OrderStep.SENDMESSAGETOCUSTOMER', 'Send message to customer about amount confirmation'),
                3
            ),
            'SendMessageToCustomer'
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

        if($this->hasAmountValidation($order)) {
            return $this->sendEmailForStep(
                $order,
                $subject = $this->EmailSubject ?: 'Confirm Paid Amount',
                $this->CalculatedCustomerMessage(),
                $resend = false,
                $adminOnlyOrToEmail,
                $this->getEmailClassName()
            );
        }
        return true;
    }

    /**
     * can go to next step if order has been paid.
     *
     * @see Order::doNextStatus
     *
     * @return null|OrderStep (next step OrderStep object)
     */
    public function nextStep(Order $order)
    {
        if (! $this->hasAmountValidation($order) || $this->hasAmountConfirmed($order)) {
            return parent::nextStep($order);
        }

        return null;
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
                '
            );
            $fields->addFieldToTab('Root.Next', new LiteralField('NotPaidMessage', '<p>' . $msg . '</p>'));
        }

        return $fields;
    }

    public function hasAmountValidation($order): bool
    {
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
        $relevantLogs = $order->OrderStatusLogs()->filter(['ClassName' => OrderStepAmountConfirmedLog::class]);
        if ($relevantLogs->exists()) {
            return OrderStepAmountConfirmedLog::get()
                ->filter([
                    'ID' => ArrayMethods::filter_array($relevantLogs->columnUnique()),
                    'IsValid' => true,
                ])->exists();
        }

        return  false;
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

    public function requireDefaultRecords()
    {
        parent::requireDefaultRecords();
        DB::query('
            UPDATE "OrderStep"
            SET "ClassName" = \''.addslashes('Sunnysideup\\PaymentDps\\Model\\Process\\OrderStepAmountConfirmed').'\'
            WHERE "ClassName" = \''.addslashes('Sunnysideup\\PaymentDps\\Forms\\Process\\OrderStepAmountConfirmed').'\'
        ');
    }
}
