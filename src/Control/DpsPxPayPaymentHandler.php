<?php

namespace Sunnysideup\PaymentDps\Control;

use Psr\Log\LoggerInterface;
use SilverStripe\Control\Controller;
use SilverStripe\Control\Director;
use SilverStripe\Core\Config\Config;
use SilverStripe\Core\Injector\Injector;
use Sunnysideup\Ecommerce\Model\Money\EcommercePayment;
use Sunnysideup\PaymentDps\DpsPxPayComs;
use Sunnysideup\PaymentDps\DpsPxPayPayment;

/**
 * Class \Sunnysideup\PaymentDps\Control\DpsPxPayPaymentHandler
 */
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
        return '/' . Config::inst()->get(DpsPxPayPaymentHandler::class, 'url_segment') . '/paid/';
    }

    public static function absolute_complete_link()
    {
        return Director::AbsoluteURL(self::complete_link());
    }

    public function paid()
    {
        $this->abortIfNotConfigured();
        EcommercePayment::get_supported_methods();
        $this->extend('DpsPxPayPaymentHandler_completion_start');
        $commsObject = new DpsPxPayComs();
        $response = $commsObject->processRequestAndReturnResultsAsObject();
        $ResponseText = $response->getResponseText();
        $DpsTxnRef = $response->getDpsTxnRef();
        $merchantReference = (string) $response->getMerchantReference();
        $merchantReferenceArray = explode('_', $merchantReference);
        $orderID = (int) ($merchantReferenceArray[0] ?? 0);
        $paymentID = (int) ($merchantReferenceArray[1] ?? 0);
        /** @var DpsPxPayPayment $payment */
        $payment = DpsPxPayPayment::get_by_id($paymentID);
        if ($payment && (int) $payment->OrderID === $orderID) {
            // idempotent: a payment that is already successful is never changed again
            if (EcommercePayment::SUCCESS_STATUS === $payment->Status) {
                $payment->redirectToOrder();

                return;
            }
            if (1 === intval($response->getSuccess())) {
                if (! $this->responseMatchesPayment($response, $payment)) {
                    $this->refuseMismatchedResponse($response, $payment);
                    $payment->redirectToOrder();

                    return;
                }
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
            $payment->SettlementAmount->Amount = $response->getAmountSettlement();
            $payment->SettlementAmount->Currency = $response->getCurrencySettlement();
            $payment->write();
            $payment->redirectToOrder();
        } else {
            user_error('could not find payment with matching ID', E_USER_WARNING);
        }
    }

    /**
     * This route is public: if the gateway has not been configured, the route is inert.
     */
    protected function abortIfNotConfigured()
    {
        if (! Config::inst()->get(DpsPxPayComs::class, 'pxpay_userid') || ! Config::inst()->get(DpsPxPayComs::class, 'pxpay_encryption_key')) {
            $this->httpError(404);
        }
    }

    /**
     * Compares the amount and currency reported by the gateway with the stored payment.
     * (2 decimals).
     *
     * @param \Sunnysideup\PaymentDps\Thirdparty\PxPayResponse $response
     * @param DpsPxPayPayment                                     $payment
     */
    protected function responseMatchesPayment($response, $payment): bool
    {
        $expectedCurrency = strtoupper(trim((string) $payment->Amount->Currency));
        if ('' === $expectedCurrency) {
            $expectedCurrency = strtoupper((string) EcommercePayment::site_currency());
        }
        $inputCurrency = strtoupper(trim((string) $response->getCurrencyInput()));
        $settlementCurrency = strtoupper(trim((string) $response->getCurrencySettlement()));
        $currencyToCheck = '' !== $inputCurrency ? $inputCurrency : $settlementCurrency;
        if ('' === $currencyToCheck || $currencyToCheck !== $expectedCurrency) {
            return false;
        }
        // the settlement amount can only be compared if it is in the currency of the payment.
        if ('' === $settlementCurrency || $settlementCurrency === $expectedCurrency) {
            $settled = $response->getAmountSettlement();
            if (! is_numeric($settled)) {
                return false;
            }
            $expectedAmount = round((float) $payment->Amount->Amount - (float) $payment->RandomDeduction, 2);
            if (abs(round((float) $settled, 2) - $expectedAmount) >= 0.005) {
                return false;
            }
        }

        return true;
    }

    /**
     * The gateway reports a success, but not for the amount / currency we expected:
     * do not mark the payment as successful, leave it for a human to look at.
     *
     * @param \Sunnysideup\PaymentDps\Thirdparty\PxPayResponse $response
     * @param DpsPxPayPayment                                     $payment
     */
    protected function refuseMismatchedResponse($response, $payment)
    {
        Injector::inst()->get(LoggerInterface::class)->error(
            'DPS payment ' . $payment->ID . ' amount or currency mismatch: expected ' .
            $payment->Amount->Amount . ' ' . $payment->Amount->Currency . ', received ' .
            $response->getAmountSettlement() . ' ' . $response->getCurrencySettlement() .
            ' (input currency ' . $response->getCurrencyInput() . ', DPS ref ' . $response->getDpsTxnRef() . ')'
        );
        $payment->Status = EcommercePayment::PENDING_STATUS;
        $payment->Message = 'The amount or currency reported by the payment gateway does not match this payment. This payment needs to be checked manually.';
        $payment->write();
    }
}
