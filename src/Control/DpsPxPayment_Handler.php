<?php

namespace Sunnysideup\PaymentDps\Control;

use Controller;
use Config;
use Director;
use EcommercePayment;
use DpsPxPayComs;
use DpsPxPayPayment;



class DpsPxPayPayment_Handler extends Controller
{
    private static $allowed_actions = array(
        "complete_link",
        "absolute_complete_link",
        "paid"
    );

    private static $url_segment = 'dpspxpaypayment';

    public static function complete_link()
    {
        return Config::inst()->get('DpsPxPayPayment_Handler', 'url_segment') . '/paid/';
    }

    public static function absolute_complete_link()
    {
        return Director::AbsoluteURL(self::complete_link());
    }

    public function paid()
    {
        EcommercePayment::get_supported_methods();
        $this->extend("DpsPxPayPayment_Handler_completion_start");
        $commsObject = new DpsPxPayComs();
        $response = $commsObject->processRequestAndReturnResultsAsObject();
        if ($payment = DpsPxPayPayment::get()->byID($response->getMerchantReference())) {
            if (1 == $response->getSuccess()) {
                $payment->Status = 'Success';
            } else {
                $payment->Status = 'Failure';
            }
            if ($DpsTxnRef = $response->getDpsTxnRef()) {
                $payment->TxnRef = $DpsTxnRef;
            }
            if ($ResponseText = $response->getResponseText()) {
                $payment->Message = $ResponseText;
            }
            $payment->write();
            $payment->redirectToOrder();
        } else {
            USER_ERROR("could not find payment with matching ID", E_USER_WARNING);
        }
        return;
    }
}
