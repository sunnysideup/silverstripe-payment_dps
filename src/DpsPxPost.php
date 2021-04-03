<?php

namespace Sunnysideup\PaymentDps;

use SilverStripe\Core\Convert;
use SilverStripe\Forms\FieldList;

use SilverStripe\Forms\LiteralField;
use SimpleXMLElement;
use Sunnysideup\Ecommerce\Forms\OrderForm;
use Sunnysideup\Ecommerce\Model\Money\EcommercePayment;
use Sunnysideup\Ecommerce\Model\Order;
use Sunnysideup\Ecommerce\Money\Payment\PaymentResults\EcommercePaymentFailure;
use Sunnysideup\Ecommerce\Money\Payment\PaymentResults\EcommercePaymentSuccess;

/**
 * @see: https://www.paymentexpress.com/Technical_Resources/Ecommerce_NonHosted/PxPost
 */


class DpsPxPost extends EcommercePayment
{
    /**
     * set the required privacy link as you see fit...
     * also see: https://www.paymentexpress.com/About/Artwork_Downloads
     * also see: https://www.paymentexpress.com/About/About_DPS/Privacy_Policy
     * @var string
     */
    private static $dps_logo_and_link = '
	<div id="PXPostPrivacy">
		<a href="https://www.paymentexpress.com/About/About_DPS/Privacy_Policy">
			<img src="https://www.paymentexpress.com/DPS/media/theme/pxlogostackedreg.png" alt="Payment Processor" width="50%" height="50%" />
		</a>
		<span>
			<a href="https://www.paymentexpress.com/About/About_DPS/Privacy_Policy">
				Payment processing provided by DPS (view Privacy Policy)
			</a>
		</span>
	</div>
	';

    /**
     * we use yes / no as this is more reliable than a boolean value
     * for configs
     * @var string
     */
    private static $is_test = 'yes';

    /**
     * we use yes / no as this is more reliable than a boolean value
     * for configs
     * @var boolean
     */
    private static $is_live = 'no';

    /**
     * @var string
     */
    private static $username = '';

    /**
     * @var string
     */
    private static $password = '';

    /**
     * type: purchase / Authorisation / refund ...
     * @var string
     */
    private static $type = 'Purchase';

    /**
     * Incomplete (default): Payment created but nothing confirmed as successful
     * Success: Payment successful
     * Failure: Payment failed during process
     * Pending: Payment awaiting receipt/bank transfer etc
     */
    private static $table_name = 'DpsPxPost';

    private static $db = [
        'CardNumber' => 'Varchar(64)',
        'NameOnCard' => 'Varchar(40)',
        'ExpiryDate' => 'Varchar(4)',
        'CVVNumber' => 'Varchar(3)',
        'Request' => 'Text',
        'Response' => 'Text',
    ];

    private static $casting = [
        'RequestDetails' => 'HTMLText',
        'ResponseDetails' => 'HTMLText',
    ];

    public function getCMSFields()
    {
        $fields = parent::getCMSFields();
        $fields->addFieldToTab('Root.Details', new LiteralField('Request', $this->getRequestDetails()));
        $fields->addFieldToTab('Root.Details', new LiteralField('Response', $this->getResponseDetails()));
        return $fields;
    }

    /**
     * Return the payment form fields that should
     * be shown on the checkout order form for the
     * payment type. Example: for {@link DPSPayment},
     * this would be a set of fields to enter your
     * credit card details.
     */
    public function getPaymentFormFields(?float $amount = 0, ?Order $order = null): FieldList
    {
        $formHelper = $this->ecommercePaymentFormSetupAndValidationObject();
        $fieldList = $formHelper->getCreditCardPaymentFormFields($this);
        $fieldList->insertBefore(
            new LiteralField('DpsPxPost_Logo', $this->Config()->get('dps_logo_and_link')),
            'DpsPxPost_CreditCard'
        );
        return $fieldList;
    }

    /**
     * Define what fields defined in {@link Order->getPaymentFormFields()}
     * should be required.
     *
     * @see DPSPayment->getPaymentFormRequirements() for an example on how
     * this is implemented.
     */
    public function getPaymentFormRequirements(): array
    {
        $formHelper = $this->ecommercePaymentFormSetupAndValidationObject();

        return $formHelper->getCreditCardPaymentFormFields($this);
    }

    /**
     * returns true if all the data is correct.
     *
     * @param array $data The form request data - see OrderForm
     * @param OrderForm $form The form object submitted on
     *
     * @return bool
     */
    public function validatePayment($data, OrderForm $form)
    {
        $formHelper = $this->ecommercePaymentFormSetupAndValidationObject();
        return $formHelper->validateAndSaveCreditCardInformation($data, $form, $this);
    }

    /**
     * Perform payment processing for the type of
     * payment. For example, if this was a credit card
     * payment type, you would perform the data send
     * off to the payment gateway on this function for
     * your payment subclass.
     *
     * This is used by {@link OrderForm} when it is
     * submitted.
     *
     * @param array $data The form request data - see OrderForm
     * @param OrderForm $form The form object submitted on
     *
     * @return \Sunnysideup\Ecommerce\Money\Payment\EcommercePaymentResult
     */
    public function processPayment($data, OrderForm $form)
    {
        //save data
        $this->write();

        //if currency has been pre-set use this
        $currency = strtoupper($this->Amount->Currency);
        //if amout has been pre-set, use this
        $amount = $this->Amount->Amount;
        $username = $this->Config()->get('username');
        $password = $this->Config()->get('password');
        if (! $username || ! $password) {
            user_error('Make sure to set a username and password.');
        }

        $xml = '<Txn>';
        $xml .= '<PostUsername>' . $username . '</PostUsername>';
        $xml .= '<PostPassword>' . $password . '</PostPassword>';
        $xml .= '<CardHolderName>' . Convert::raw2xml($this->NameOnCard) . '</CardHolderName>';
        $xml .= '<CardNumber>' . $this->CardNumber . '</CardNumber>';
        $xml .= '<Amount>' . round($amount, 2) . '</Amount>';
        $xml .= '<DateExpiry>' . $this->ExpiryDate . '</DateExpiry>';
        $xml .= '<Cvc2>' . $this->CVVNumber . '</Cvc2>';
        $xml .= '<Cvc2Presence>1</Cvc2Presence>';
        $xml .= '<InputCurrency>' . Convert::raw2xml(strtoupper($currency)) . '</InputCurrency>';
        $xml .= '<TxnType>' . Convert::raw2xml($this->Config()->get('type')) . '</TxnType>';
        $xml .= '<TxnId>' . $this->ID . '</TxnId>';
        $xml .= '<MerchantReference>' . $this->OrderID . '</MerchantReference>';
        $xml .= '</Txn>';
        $URL = 'sec.paymentexpress.com/pxpost.aspx';
        //echo "\n\n\n\nSENT:\n$cmdDoTxnTransaction\n\n\n\n\n$";

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, 'https://' . $URL);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $xml);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0); //Needs to be included if no *.crt is available to verify SSL certificates

        $result = curl_exec($ch);
        curl_close($ch);

        $params = new SimpleXMLElement($result);
        $txn = $params->Transaction;

        //save basic info
        //$this->Request = Convert::raw2sql($xml);
        $this->Response = str_replace('\n', "\n", Convert::raw2sql(print_r($params, 1)));
        $this->Message = Convert::raw2sql($txn->CardHolderResponseText . ' ' . $txn->CardHolderResponseDescription);
        $this->CardNumber = Convert::raw2sql($txn->CardNumber);
        if ($params->Success === 1 &&
            $amount === $txn->Amount &&
            $currency === $txn->CurrencyName &&
            trim($this->OrderID) === trim($txn->MerchantReference)
        ) {
            $this->Status = 'Success';
            $returnObject = EcommercePaymentSuccess::create();
        } else {
            $this->Status = 'Failure';
            $returnObject = EcommercePaymentFailure::create();
        }
        $this->write();
        return $returnObject;
    }

    public function getRequestDetails()
    {
        return '<pre>' . $this->Request . '</pre>';
    }

    public function getResponseDetails()
    {
        return '<pre>' . $this->Response . '</pre>';
    }

    /**
     * are you running in test mode?
     *
     * @return bool
     */
    protected function isTest()
    {
        if ($this->Config()->get('is_test') === 'yes' && $this->Config()->get('is_live') === 'no') {
            return true;
        } elseif ($this->Config()->get('is_test') === 'no' && $this->Config()->get('is_live') === 'yes') {
            return false;
        }
        user_error('Class not set to live or test correctly.');
        return false;
    }
}
