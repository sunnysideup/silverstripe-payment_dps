<?php

namespace Sunnysideup\PaymentDps\Forms;

use SilverStripe\Control\Controller;
use SilverStripe\Core\Convert;
use SilverStripe\Forms\CurrencyField;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\Form;
use SilverStripe\Forms\FormAction;
use SilverStripe\Forms\HiddenField;
use SilverStripe\Forms\HeaderField;
use SilverStripe\Forms\RequiredFields;
use Sunnysideup\Ecommerce\Api\Sanitizer;
use Sunnysideup\Ecommerce\Model\Order;
use Sunnysideup\PaymentDps\Forms\Process\OrderStepAmountConfirmedLog;

class CustomerOrderStepForm extends Form
{
    /**
     * @param string $name
     */
    public function __construct(Controller $controller, $name, Order $order)
    {
        $requiredFields = [];
        $fields = new FieldList(
            [
                HeaderField::create(
                    'AmountPaidHeader',
                    'Amount Paid (check bank balance)'
                ),
                CurrencyField::create(
                    'AmountPaid',
                    'Amount Paid'
                ),
                new HiddenField('OrderID', '', $order->ID)
            ]
        );
        $actions = new FieldList(
            new FormAction('confirmamount', 'Confirm Amount')
        );
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
        if (isset($SQLData['OrderID'])) {
            $orderID = intval($SQLData['OrderID']);
            if ($orderID) {
                $order = Order::get_order_cached((int) $orderID);
                if ($order) {
                    if (OrderStepAmountConfirmedLog::is_locked_out($order)) {
                        $form->sessionMessage('Sorry, you can only try three times per day', 'bad');

                        return $this->controller->redirectBack();
                    }
                    $answer = floatval($data['AmountPaid']);
                    if ($answer) {
                        $isValid = OrderStepAmountConfirmedLog::test_answer($order, $answer);
                        if ($isValid) {
                            $order->tryToFinaliseOrder();
                        } else {
                            $form->sessionMessage(_t('OrderForm.WRONGANSWER', 'Sorry, the amount does not match.'), 'bad');
                        }
                    }
                }
            }
        } else {
            $form->sessionMessage(_t('OrderForm.COULDNOTPROCESSPAYMENT', 'Sorry, we could not find the Order for payment.'), 'bad');
        }

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
