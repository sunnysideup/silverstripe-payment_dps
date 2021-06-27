<?php

namespace Sunnysideup\PaymentDps;

use SilverStripe\Core\Config\Config;
use SilverStripe\Forms\DropdownField;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\Form;
use SilverStripe\Forms\LiteralField;
use SilverStripe\ORM\DB;
use SilverStripe\Security\Security;
use SilverStripe\View\Requirements;
use Sunnysideup\Ecommerce\Forms\OrderForm;
use Sunnysideup\Ecommerce\Model\Order;
use Sunnysideup\Ecommerce\Money\Payment\PaymentResults\EcommercePaymentFailure;
use Sunnysideup\Ecommerce\Money\Payment\PaymentResults\EcommercePaymentSuccess;
use Sunnysideup\PaymentDps\Control\DpsPxPayStoredPaymentHandler;
use Sunnysideup\PaymentDps\Model\DpsPxPayStoredCard;

class DpsPxPayStoredPayment extends DpsPxPayPayment
{
    private static $pxaccess_url = 'https://sec.paymentexpress.com/pxpay/pxaccess.aspx';

    private static $pxpost_url = 'https://sec.paymentexpress.com/pxpost.aspx';

    private static $privacy_link = '';

    private static $logo = '';

    private static $credit_cards = [];

    private static $username = '';

    private static $password = '';

    private static $add_card_explanation = 'Storing a Card means your Credit Card will be kept on file for your next purchase. ';

    public function getPaymentFormFields($amount = 0, ?Order $order = null): FieldList
    {
        $logo = '<img src="' . self::$logo . '" alt="Credit Card Payments Powered by DPS"/>';
        $privacyLink = '<a href="' . self::$privacy_link . '" target="_blank" title="Read DPS\'s privacy policy">' . $logo . '</a><br/>';
        $paymentsList = '';
        foreach (self::$credit_cards as $name => $image) {
            $paymentsList .= '<img src="' . $image . '" alt="' . $name . '"/>';
        }

        $fields = new FieldList();
        $storedCards = null;
        if ($m = Security::getCurrentUser()) {
            $storedCards = DpsPxPayStoredCard::get()->filter(['MemberID' => $m->ID]);
        }

        $cardsDropdown = ['' => ' --- Select Stored Card ---'];

        if ($storedCards->exists()) {
            $card = null;
            foreach ($storedCards as $card) {
                $cardsDropdown[$card->BillingID] = $card->CardHolder . ' - ' . $card->CardNumber . ' (' . $card->CardName . ')';
            }
            $s = '';
            if ($storedCards->count() > 1) {
                $s = 's';
            }
            $cardsDropdown['deletecards'] = " --- Delete Stored Card{$s} --- ";
            $fields->push(
                DropdownField::create(
                    'DPSUseStoredCard',
                    'Use a stored card?',
                    $cardsDropdown,
                    $value = $card->BillingID
                )->setEmptyString('--- use new Credit Card ---')
            );
        } else {
            $fields->push(new DropdownField('DPSStoreCard', '', [1 => 'Store Credit Card', 0 => 'Do NOT Store Credit Card']));
            $fields->push(new LiteralField('AddCardExplanation', '<p>' . Config::inst()->get(DpsPxPayStoredPayment::class, 'add_card_explanation') . '</p>'));
        }
        $fields->push(new LiteralField('DPSInfo', $privacyLink));
        $fields->push(new LiteralField('DPSPaymentsList', $paymentsList));
        Requirements::javascript(THIRDPARTY_DIR . '/jquery/jquery.js');
        //Requirements::block(THIRDPARTY_DIR."/jquery/jquery.js");
        //Requirements::javascript(Director::protocol()."ajax.googleapis.com/ajax/libs/jquery/1.7.2/jquery.min.js");
        Requirements::javascript('sunnysideup/payment_dps: payment_dps/javascript/DpxPxPayStoredPayment.js');

        return $fields;
    }

    public function autoProcessPayment($amount, $ref)
    {
        //$DPSUrl = $this->buildURL($amount, $ref, false);
        /*
        add CURL HERE
        $data = array('page' => $page);
        // create our curl object
        $ch = curl_init();
        $lurl = 'http://www.test.com';
        curl_setopt( $ch, CURLOPT_POST, true );
        curl_setopt( $ch, CURLOPT_POSTFIELDS,$data);
        curl_setopt($ch, CURLOPT_URL, $lurl);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION  ,1);
        curl_setopt($ch, CURLOPT_HEADER, 0);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
        curl_setopt($ch, CURLOPT_FAILONERROR, 0);
        $content = curl_exec($ch);
        curl_close($ch);
        return $content;
        */
    }

    /**
     * @param array $data The form request data - see OrderForm
     * @param Form  $form The form object submitted on
     *
     * @return \Sunnysideup\Ecommerce\Money\Payment\EcommercePaymentResult
     */
    public function processPayment($data, Form $form)
    {
        if (! isset($data['DPSUseStoredCard'])) {
            $data['DPSUseStoredCard'] = null;
        }
        if (! isset($data['DPSStoreCard'])) {
            $data['DPSStoreCard'] = null;
        }
        if (! isset($data['Amount'])) {
            user_error('There was no amount information for processing the payment.', E_USER_WARNING);
        }
        if ('deletecards' === $data['DPSUseStoredCard']) {
            //important!!!
            $data['DPSUseStoredCard'] = null;
            if ($m = Security::getCurrentUser()) {
                $storedCards = DpsPxPayStoredCard::get()->filter(['MemberID' => $m->ID]);
                if ($storedCards->exists()) {
                    foreach ($storedCards as $card) {
                        $card->delete();
                    }
                    if (DpsPxPayStoredCard::get()->filter(['MemberID' => $m->ID])) {
                        DB::query('DELETE FROM DpsPxPayStoredCard WHERE MemberID = ' . $m->ID);
                    }
                }
            }
        } elseif ($data['DPSUseStoredCard']) {
            return $this->processViaPostRatherThanPxPay($data, $form, $data['DPSUseStoredCard']);
        }
        $url = $this->buildURL($data['Amount'], $data['DPSUseStoredCard'], $data['DPSStoreCard']);

        return $this->executeURL($url);
    }

    public function processViaPostRatherThanPxPay($data, $form, $cardToUse)
    {
        // 1) Main Settings

        $inputs['PostUsername'] = $this->config->get('username');
        $inputs['PostPassword'] = $this->config->get('password');

        // 2) Payment Informations

        $inputs['Amount'] = $this->Amount->Amount;
        $inputs['InputCurrency'] = $this->Amount->Currency;
        $inputs['TxnId'] = $this->ID;
        $inputs['TxnType'] = DpsPxPayComs::get_txn_type();
        $inputs['MerchantReference'] = $this->ID;

        // 3) Credit Card Informations
        $inputs['DpsBillingId'] = $cardToUse;

        // 4) DPS Transaction Sending

        $responseFields = $this->doPayment($inputs);
        // 5) DPS Response Management

        if (isset($responseFields['SUCCESS']) && $responseFields['SUCCESS']) {
            $this->Status = 'Success';
            $result = EcommercePaymentSuccess::create();
        } else {
            $this->Status = 'Failure';
            $result = EcommercePaymentFailure::create();
        }
        if (isset($responseFields['DPSTXNREF'])) {
            if ($transactionRef = $responseFields['DPSTXNREF']) {
                $this->TxnRef = $transactionRef;
            }
        }

        if (isset($responseFields['HELPTEXT'])) {
            if ($helpText = $responseFields['HELPTEXT']) {
                $this->Message = $helpText;
            }
        }
        if (isset($responseFields['RESPONSETEXT'])) {
            if ($responseText = $responseFields['RESPONSETEXT']) {
                $this->Message .= $responseText;
            }
        }

        $this->write();

        return $result;
    }

    public function doPayment(array $inputs)
    {
        // 1) Transaction Creation
        $transaction = '<Txn>';
        foreach ($inputs as $name => $value) {
            if ('Amount' === $name) {
                $value = number_format($value, 2, '.', '');
            }
            $transaction .= "<{$name}>{$value}</{$name}>";
        }
        $transaction .= '</Txn>';

        // 2) CURL Creation
        $clientURL = curl_init();
        curl_setopt($clientURL, CURLOPT_URL, $this->config()->get('pxpost_url'));
        curl_setopt($clientURL, CURLOPT_POST, 1);
        curl_setopt($clientURL, CURLOPT_POSTFIELDS, $transaction);
        curl_setopt($clientURL, CURLOPT_RETURNTRANSFER, 1);
        //curl_setopt($clientURL, CURLOPT_SSLVERSION, 3);

        // 3) CURL Execution

        $resultXml = curl_exec($clientURL);

        // 4) CURL Closing

        curl_close($clientURL);

        // 5) XML Parser Creation

        $xmlParser = xml_parser_create();
        $values = null;
        $indexes = null;
        xml_parse_into_struct($xmlParser, $resultXml, $values, $indexes);
        xml_parser_free($xmlParser);

        // 6) XML Result Parsed In A PHP Array

        $resultPhp = [];
        $level = [];
        foreach ($values as $xmlElement) {
            if ('open' === $xmlElement['type']) {
                if (array_key_exists('attributes', $xmlElement)) {
                    $arrayValues = array_values($xmlElement['attributes']);
                    list($level[$xmlElement['level']]) = $arrayValues;
                } else {
                    $level[$xmlElement['level']] = $xmlElement['tag'];
                }
            } elseif ('complete' === $xmlElement['type']) {
                $startLevel = 1;
                $phpArray = '$resultPhp';
                while ($startLevel < $xmlElement['level']) {
                    $phpArray .= '[$level[' . $startLevel++ . ']]';
                }
                $phpArray .= '[$xmlElement[\'tag\']] = array_key_exists(\'value\', $xmlElement)? $xmlElement[\'value\'] : null;';
                eval($phpArray);
            }
        }
        if (! isset($resultPhp['TXN'])) {
            return false;
        }

        return $resultPhp['TXN'];
    }

    protected function buildURL($amount, $cardToUse = '', ?bool $storeCard = false)
    {
        $commsObject = new DpsPxPayComs();

        // order details
        $commsObject->setTxnType(DpsPxPayComs::get_txn_type());
        $commsObject->setMerchantReference($this->ID);
        //replace any character that is NOT [0-9] or dot (.)
        $commsObject->setAmountInput(floatval(preg_replace('/[^0-9\\.]/', '', $amount)));

        if (! empty($cardToUse)) {
            $commsObject->setBillingId($cardToUse);
        } elseif ($storeCard) {
            $commsObject->setEnableAddBillCard(1);
        }

        /**
         * details of the redirection.
         */
        $link = DpsPxPayStoredPaymentHandler::absolute_complete_link();
        $commsObject->setUrlFail($link);
        $commsObject->setUrlSuccess($link);

        // process payment data (check if it is OK and go forward if it is...
        return $commsObject->startPaymentProcess();
    }
}
