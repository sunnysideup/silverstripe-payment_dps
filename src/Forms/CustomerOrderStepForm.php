<?php

namespace Sunnysideup\PaymentDps\Forms;

use SilverStripe\Control\Controller;
use SilverStripe\Core\Convert;
use SilverStripe\Forms\CompositeField;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\Form;
use SilverStripe\Forms\FormAction;
use SilverStripe\Forms\HiddenField;
use Sunnysideup\Ecommerce\Forms\Validation\OrderFormPaymentValidator;
use Sunnysideup\Ecommerce\Model\Money\EcommercePayment;
use Sunnysideup\Ecommerce\Model\Order;

use SilverStripe\Forms\RequiredFields;

class CustomerOrderStepForm extends Form
{
    /**
     * @param Controller $controller
     * @param string     $name
     * @param Order      $order
     * @param string $returnToLink
     */
    public function __construct(Controller $controller, $name, Order $order)
    {
        $requiredFields = [];
        $fields = new FieldList(
            CurrencyField::create(
                'AmountPaid',
                'Amount Paid'
            ),
            new HiddenField('OrderID', '', $order->ID)
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
            if ($orderID = intval($SQLData['OrderID'])) {
                $order = Order::get()->byID($orderID);
                if ($order) {
                    if(OrderStepAmountConfirmedLog::is_locked_out($order)) {
                        $form->sessionMessage('Sorry, you can only try three times per day', 'bad');

                        return $this->controller->redirectBack();
                    }
                } else {
                    $answer = floatval($data['AmountPaid']);
                    if($answer) {
                        $isValid = OrderStepAmountConfirmedLog::test_answer($order, $answer);
                        if($isValid) {
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
        Controller::curr()->getRequest()->getSession()->set("FormInfo.{$this->FormName()}.data", $data);
    }
}
