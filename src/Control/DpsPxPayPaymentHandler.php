<?php

namespace Sunnysideup\PaymentDps\Control;

use SilverStripe\Control\Controller;
use SilverStripe\Control\Director;
use SilverStripe\Core\Config\Config;
use Sunnysideup\Ecommerce\Model\Money\EcommercePayment;
use Sunnysideup\PaymentDps\DpsPxPayComs;
use Sunnysideup\PaymentDps\DpsPxPayPayment;

class DpsPxPayPaymentHandler extends Controller
{
    private static $allowed_actions = [
        'complete_link',
        'absolute_complete_link',
        'paid',
    ];

    private static $url_segment = 'dpspxpaypayment';

    public static function complete_link()
    {
        return Config::inst()->get(DpsPxPayPaymentHandler::class, 'url_segment') . '/paid/';
    }

    public static function absolute_complete_link()
    {
        return Director::AbsoluteURL(self::complete_link());
    }

    public function paid()
    {
        EcommercePayment::get_supported_methods();
        $this->extend('DpsPxPayPaymentHandler_completion_start');
        $commsObject = new DpsPxPayComs();
        $response = $commsObject->processRequestAndReturnResultsAsObject();
        $ResponseText = $response->getResponseText();
        $DpsTxnRef = $response->getDpsTxnRef();
        $payment = DpsPxPayPayment::get_by_id($response->getMerchantReference());
        if ($payment) {
            if (1 === intval($response->getSuccess())) {
                $payment->Status = EcommercePayment::SUCCESS_STATUS;
            } else {
                $payment->Status = EcommercePayment::FAILURE_STATUS;
            }
            if ($DpsTxnRef) {
                $payment->TxnRef = $DpsTxnRef;
            }
            if ($ResponseText) {
                $payment->Message = $ResponseText;
            }
            // check amount and currency...
            $payment->SettlementAmount->Amount = $response->getAmountSettlement();
            $payment->SettlementAmount->Currency = $response->getCurrencySettlement();
            $payment->write();
            $payment->redirectToOrder();
        } else {
            user_error('could not find payment with matching ID', E_USER_WARNING);
        }
    }
}
