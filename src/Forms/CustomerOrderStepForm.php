<?php

namespace Sunnysideup\PaymentDps\Forms;

use SilverStripe\Forms\ReadonlyField;
use SilverStripe\Control\Controller;
use SilverStripe\Core\Convert;

use SilverStripe\Core\Config\Config;
use SilverStripe\Forms\CurrencyField;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\Form;
use SilverStripe\Forms\FormAction;
use SilverStripe\Forms\HeaderField;
use SilverStripe\Forms\HiddenField;
use SilverStripe\Forms\LiteralField;
use SilverStripe\Forms\RequiredFields;

use SilverStripe\ORM\ValidationResult;
use Sunnysideup\Ecommerce\Api\Sanitizer;
use Sunnysideup\Ecommerce\Model\Order;
use Sunnysideup\PaymentDps\Model\Process\OrderStepAmountConfirmed;
use Sunnysideup\PaymentDps\Model\Process\OrderStepAmountConfirmedLog;

class CustomerOrderStepForm extends Form
{
    /**
     * @param string $name
     */
    public function __construct(Controller $controller, $name, Order $order)
    {
        $step = OrderStepAmountConfirmed::get()->first();
        $explanation = 'Please check your credit card / bank statement to confirm the amount that was charged.';
        if ($step) {
            $heading = $step->Heading;
            $explanation = $step->Explanation;
        } else {
            $defaults = Config::inst()->get(OrderStepAmountConfirmed::class, 'defaults');
            $explanation = $defaults['Explanation'] ?? '';
            $heading = $defaults['Heading'] ?? 'Action Required: Confirm Amount Paid';
        }
        if(OrderStepAmountConfirmedLog::has_been_confirmed($order)) {
            $amountField = ReadonlyField::create(
                'AmountPaid',
                'Discounted amount charged to your credit card',
                $step->ThankYou ?: 'Thank you for your confirmation'
            );
        } else {
            $amountField = CurrencyField::create(
                'AmountPaid',
                'Discounted amount charged to your credit card'
            );
        }
        $requiredFields = [];
        $fields = new FieldList(
            [
                HeaderField::create(
                    'AmountPaidHeader',
                    $heading
                ),
                LiteralField::create(
                    'AmountPaidExplanation',
                    '<div class="important-explanation">'.$explanation.'</div>'
                ),
                $amountField,
                new HiddenField('OrderID', '', $order->ID),
            ]
        );
        $actions = new FieldList();
        if(! OrderStepAmountConfirmedLog::has_been_confirmed($order)) {
            $actions->push(new FormAction('confirmamount', 'Confirm Amount'));
        }
        $validator = RequiredFields::create($requiredFields);
        parent::__construct($controller, $name, $fields, $actions, $validator);

        //extension point
        $this->extend('updateFields', $fields);
        $this->setFields($fields);
        $this->extend('updateActions', $actions);
        $this->setActions($actions);
        $this->extend('updateValidator', $validator);
        $this->setValidator($validator);

        $this->setFormAction($controller->Link($name));
        $oldData = Controller::curr()->getRequest()->getSession()->get("FormInfo.{$this->FormName()}.data");
        if ($oldData && (is_array($oldData) || is_object($oldData))) {
            $this->loadDataFrom($oldData);
        }
        $this->extend('updateCustomerOrderStepForm', $this);
    }

    /**
     * @param array $data
     * @param Form  $form
     *
     * @return mixed
     */
    public function confirmamount($data, $form)
    {
        $SQLData = Convert::raw2sql($data);
        $order = null;
        $validationResult = new ValidationResult();
        if (isset($SQLData['OrderID'])) {
            $orderID = intval($SQLData['OrderID']);
            if ($orderID) {
                $order = Order::get_order_cached((int) $orderID);
                if ($order) {
                    if (OrderStepAmountConfirmedLog::is_locked_out($order)) {
                        $form->sessionMessage('Sorry, you can only try three times per day', 'bad');
                    } else {
                        $answer = OrderStepAmountConfirmed::currency_to_float($data['AmountPaid'] ?? '');

                        if ($answer) {
                            $isValid = OrderStepAmountConfirmedLog::test_answer($order, $answer);
                            if ($isValid) {
                                $validationResult->addFieldError('AmountPaid', _t('OrderForm.RIGHTANSWER', 'Thank you for your confirmation.'), 'good');
                                $order->tryToFinaliseOrder();
                            } else {
                                $validationResult->addFieldError('AmountPaid',_t('OrderForm.WRONGANSWER', 'Sorry, the amount does not match.'), 'bad');
                            }
                        } else {
                            $validationResult->addFieldError('AmountPaid',_t('OrderForm.PLEASE_ENTER_ANSWER', 'Please enter an amount.'), 'bad');
                        }
                    }
                }
            }
        }
        if(! $order) {
            $validationResult->addFieldError('AmountPaid',_t('OrderForm.COULDNOTPROCESSPAYMENT', 'Sorry, we could not find the Order for payment.'), 'bad');
        }
        $form->setSessionValidationResult($validationResult);

        return $this->controller->redirectBack();
    }

    /**
     * saves the form into session.
     */
    public function saveDataToSession()
    {
        $data = $this->getData();
        $data = Sanitizer::remove_from_data_array($data);
        $this->setSessionData($data);
    }
}
