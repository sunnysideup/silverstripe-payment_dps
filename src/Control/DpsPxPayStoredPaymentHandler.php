<?php

namespace Sunnysideup\PaymentDps\Control;

use SilverStripe\Control\Director;
use SilverStripe\Core\Config\Config;
use Sunnysideup\Ecommerce\Model\Money\EcommercePayment;
use Sunnysideup\PaymentDps\DpsPxPayComs;
use Sunnysideup\PaymentDps\DpsPxPayStoredPayment;
use Sunnysideup\PaymentDps\Model\DpsPxPayStoredCard;

/**
 * Class \Sunnysideup\PaymentDps\Control\DpsPxPayStoredPaymentHandler
 *
 */
class DpsPxPayStoredPaymentHandler extends DpsPxPayPaymentHandler
{
    private static $url_segment = 'dpspxpaystoredpayment';

    public static function complete_link()
    {
        return '/' . Config::inst()->get(DpsPxPayStoredPaymentHandler::class, 'url_segment') . '/paid/';
    }

    public static function absolute_complete_link()
    {
        return Director::AbsoluteURL(self::complete_link());
    }

    public function paid()
    {
        $commsObject = new DpsPxPayComs();
        $response = $commsObject->processRequestAndReturnResultsAsObject();
        $ResponseText = $response->getResponseText();
        $DpsTxnRef = $response->getDpsTxnRef();
        $merchantReference = $response->getMerchantReference();
        $merchantReferenceArray = explode('_', $merchantReference);
        $orderID = (int) $merchantReferenceArray[0];
        $paymentID = (int) $merchantReferenceArray[1];
        /** @var DpsPxPayStoredPayment $payment */
        $payment = DpsPxPayStoredPayment::get_by_id($paymentID);
        if ($payment && $payment->OrderID === $orderID) {
            if (EcommercePayment::SUCCESS_STATUS !== $payment->Status) {
                if (1 === $response->getSuccess()) {
                    $payment->Status = EcommercePayment::SUCCESS_STATUS;

                    if ($response->DpsBillingId) {
                        $existingCard = DpsPxPayStoredCard::get()->filter(['BillingID' => $response->DpsBillingId])->First();

                        if ($existingCard === false) {
                            $storedCard = new DpsPxPayStoredCard();
                            $storedCard->BillingID = $response->DpsBillingId;
                            $storedCard->CardName = $response->CardName;
                            $storedCard->CardHolder = $response->CardHolderName;
                            $storedCard->CardNumber = $response->CardNumber;
                            $storedCard->MemberID = $payment->getOrderCached()->MemberID;
                            $storedCard->write();
                        }
                    }
                } else {
                    $payment->Status = EcommercePayment::FAILURE_STATUS;
                }
                if ($DpsTxnRef) {
                    $payment->TxnRef = $DpsTxnRef;
                }
                if ($ResponseText) {
                    $payment->Message = $ResponseText;
                }
                $payment->write();
            }
            $payment->redirectToOrder();
        } else {
            user_error('could not find payment with matching ID', E_USER_WARNING);
        }
    }
}
