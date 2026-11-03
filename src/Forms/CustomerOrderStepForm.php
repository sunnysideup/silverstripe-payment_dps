<?php

namespace Sunnysideup\PaymentDps\Forms;

use SilverStripe\Control\Controller;
use SilverStripe\Core\Config\Config;
use SilverStripe\Forms\CurrencyField;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\Form;
use SilverStripe\Forms\FormAction;
use SilverStripe\Forms\HeaderField;
use SilverStripe\Forms\HiddenField;
use SilverStripe\Forms\LiteralField;
use SilverStripe\Forms\ReadonlyField;
use SilverStripe\Forms\RequiredFields;
use SilverStripe\ORM\ValidationResult;
use Sunnysideup\Ecommerce\Api\Sanitizer;
use Sunnysideup\Ecommerce\Model\Order;
use Sunnysideup\PaymentDps\Model\Process\OrderStepAmountConfirmed;
use Sunnysideup\PaymentDps\Model\Process\OrderStepAmountConfirmedLog;

class CustomerOrderStepForm extends Form
{
    private const SESSION_KEY_ORDER_IDS = 'CustomerOrderStepFormOrderIDs';

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
        if (OrderStepAmountConfirmedLog::has_been_confirmed($order)) {
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
                    '<div class="important-explanation">' . $explanation . '</div>'
                ),
                $amountField,
                new HiddenField('OrderID', '', $order->ID),
            ]
        );
        $actions = new FieldList();
        if (! OrderStepAmountConfirmedLog::has_been_confirmed($order)) {
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
        // remember which order this form was shown for, so that only that order can be confirmed with it.
        $session = Controller::curr()->getRequest()->getSession();
        $allowedOrderIds = $session->get(self::SESSION_KEY_ORDER_IDS);
        $allowedOrderIds = is_array($allowedOrderIds) ? $allowedOrderIds : [];
        $allowedOrderIds[(int) $order->ID] = (int) $order->ID;
        $session->set(self::SESSION_KEY_ORDER_IDS, array_slice($allowedOrderIds, -10, null, true));
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
        $order = null;
        $validationResult = new ValidationResult();
        if (isset($data['OrderID'])) {
            $orderID = intval($data['OrderID']);
            if ($orderID !== 0) {
                $order = Order::get_order_cached((int) $orderID);
                if ($order && ! $this->mayConfirmOrder($order)) {
                    $order = null;
                }
                if ($order instanceof \Sunnysideup\Ecommerce\Model\Order) {
                    if (OrderStepAmountConfirmedLog::is_locked_out($order)) {
                        $form->sessionMessage('Sorry, you can only try three times per day', 'bad');
                    } else {
                        $answer = OrderStepAmountConfirmed::currency_to_float($data['AmountPaid'] ?? '');

                        if ($answer !== 0.0) {
                            $isValid = OrderStepAmountConfirmedLog::test_answer($order, $answer);
                            if ($isValid) {
                                $validationResult->addFieldError('AmountPaid', _t('OrderForm.RIGHTANSWER', 'Thank you for your confirmation.'), 'good');
                                $order->tryToFinaliseOrder();
                            } else {
                                $validationResult->addFieldError('AmountPaid', _t('OrderForm.WRONGANSWER', 'Sorry, the amount does not match.'), 'bad');
                            }
                        } else {
                            $validationResult->addFieldError('AmountPaid', _t('OrderForm.PLEASE_ENTER_ANSWER', 'Please enter an amount.'), 'bad');
                        }
                    }
                }
            }
        }
        if (! $order instanceof \Sunnysideup\Ecommerce\Model\Order) {
            $validationResult->addFieldError('AmountPaid', _t('OrderForm.COULDNOTPROCESSPAYMENT', 'Sorry, we could not find the Order for payment.'), 'bad');
        }
        $form->setSessionValidationResult($validationResult);

        return $this->controller->redirectBack();
    }

    /**
     * Only the order that this form was shown for (in this session), or an order that the current user is allowed to view.
     */
    protected function mayConfirmOrder(Order $order): bool
    {
        $allowedOrderIds = Controller::curr()->getRequest()->getSession()->get(self::SESSION_KEY_ORDER_IDS);
        if (is_array($allowedOrderIds) && isset($allowedOrderIds[(int) $order->ID])) {
            return true;
        }

        return (bool) $order->canView();
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
