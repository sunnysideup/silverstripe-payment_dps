<?php

namespace Sunnysideup\PaymentDps\Control;

use SilverStripe\Control\Director;
use SilverStripe\Core\Config\Config;
use Sunnysideup\PaymentDps\DpsPxPayComs;
use Sunnysideup\PaymentDps\DpsPxPayStoredPayment;
use Sunnysideup\PaymentDps\Model\DpsPxPayStoredCard;

class DpsPxPayStoredPaymentHandler extends DpsPxPayPaymentHandler
{
    private static $url_segment = 'dpspxpaystoredpayment';

    public static function complete_link()
    {
        return Config::inst()->get(DpsPxPayStoredPaymentHandler::class, 'url_segment') . '/paid/';
    }

    public static function absolute_complete_link()
    {
        return Director::AbsoluteURL(self::complete_link());
    }

    public function paid()
    {
        $commsObject = new DpsPxPayComs();
        $response = $commsObject->processRequestAndReturnResultsAsObject();
        if ($payment = DpsPxPayStoredPayment::get()->byID($response->getMerchantReference())) {
            if ($payment->Status !== 'Success') {
                if ($response->getSuccess() === 1) {
                    $payment->Status = 'Success';

                    if ($response->DpsBillingId) {
                        $existingCard = DpsPxPayStoredCard::get()->filter(['BillingID' => $response->DpsBillingId])->First();

                        if ($existingCard === false) {
                            $storedCard = new DpsPxPayStoredCard();
                            $storedCard->BillingID = $response->DpsBillingId;
                            $storedCard->CardName = $response->CardName;
                            $storedCard->CardHolder = $response->CardHolderName;
                            $storedCard->CardNumber = $response->CardNumber;
                            $storedCard->MemberID = $payment->Order()->MemberID;
                            $storedCard->write();
                        }
                    }
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
            }
            $payment->redirectToOrder();
        } else {
            USER_ERROR('could not find payment with matching ID', E_USER_WARNING);
        }
        return;
    }
}
