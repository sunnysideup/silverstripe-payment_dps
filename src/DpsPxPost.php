<?php

namespace Sunnysideup\PaymentDps;

use SilverStripe\Core\Convert;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\Form;
use SilverStripe\Forms\LiteralField;
use SimpleXMLElement;
use Sunnysideup\Ecommerce\Forms\OrderForm;
use Sunnysideup\Ecommerce\Model\Money\EcommercePayment;
use Sunnysideup\Ecommerce\Model\Order;
use Sunnysideup\Ecommerce\Money\Payment\PaymentResults\EcommercePaymentFailure;
use Sunnysideup\Ecommerce\Money\Payment\PaymentResults\EcommercePaymentSuccess;

/**
 * Class \Sunnysideup\PaymentDps\DpsPxPost
 *
 * @property string $CardNumber
 * @property string $NameOnCard
 * @property string $ExpiryDate
 * @property string $CVVNumber
 * @property string $Request
 * @property string $Response
 */
class DpsPxPost extends EcommercePayment
{
    /**
     * set the required privacy link as you see fit...
     * also see: https://www.paymentexpress.com/About/Artwork_Downloads
     * also see: https://www.paymentexpress.com/About/About_DPS/Privacy_Policy.
     *
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
     * for configs.
     *
     * @var string
     */
    private static $is_test = 'yes';

    /**
     * we use yes / no as this is more reliable than a boolean value
     * for configs.
     *
     * @var string
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
     *
     * @var string
     */
    private static $type = 'Purchase';

    /**
     * Incomplete (default): Payment created but nothing confirmed as successful
     * Success: Payment successful
     * Failure: Payment failed during process
     * Pending: Payment awaiting receipt/bank transfer etc.
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

    /**
     * Card data (PAN, CVV, expiry) must never be stored in the database.
     * It is held in memory only, for the duration of the request.
     *
     * @var array
     */
    protected $sensitiveCardData = [];

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
     *
     * @param mixed $amount
     */
    public function getPaymentFormFields($amount = 0, ?Order $order = null): FieldList
    {
        $formHelper = $this->ecommercePaymentFormSetupAndValidationObject();
        $fieldList = $formHelper->getCreditCardPaymentFormFields($this);
        $fieldList->insertBefore(
            'DpsPxPost_CreditCard',
            new LiteralField('DpsPxPost_Logo', $this->Config()->get('dps_logo_and_link')),
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

        return $formHelper->getCreditCardPaymentFormFieldsRequired($this);
    }

    /**
     * returns true if all the data is correct.
     *
     * @param array $data The form request data - see OrderForm
     * @param Form  $form The form object submitted on
     *
     * @return bool
     */
    public function validatePayment($data, Form $form)
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
     * @param Form  $form The form object submitted on
     *
     * @return \Sunnysideup\Ecommerce\Money\Payment\EcommercePaymentResult
     */
    public function processPayment($data, Form $form)
    {
        // idempotent: never charge a payment that has already been completed
        if (EcommercePayment::SUCCESS_STATUS === $this->Status) {
            return EcommercePaymentSuccess::create();
        }
        //save data (card data is masked before it reaches the database, see onBeforeWrite)
        $this->stashAndMaskCardData();
        $this->write();
        $cardData = $this->sensitiveCardData;
        $this->sensitiveCardData = [];

        //if currency has been pre-set use this
        $currency = strtoupper((string) $this->Amount->Currency);
        //if amout has been pre-set, use this
        $amount = round((float) $this->Amount->Amount, 2);
        $username = $this->Config()->get('username');
        $password = $this->Config()->get('password');
        if (! $username || ! $password) {
            user_error('Make sure to set a username and password.');
        }
        if (empty($cardData['CardNumber']) || $amount <= 0) {
            $this->Status = EcommercePayment::FAILURE_STATUS;
            $this->Message = 'Credit card details were not available for processing.';
            $this->write();

            return EcommercePaymentFailure::create();
        }
        $xmlEscape = static fn ($value) => htmlspecialchars((string) $value, ENT_XML1 | ENT_QUOTES, 'UTF-8');

        $xml = '<Txn>';
        $xml .= '<PostUsername>' . $xmlEscape($username) . '</PostUsername>';
        $xml .= '<PostPassword>' . $xmlEscape($password) . '</PostPassword>';
        $xml .= '<CardHolderName>' . $xmlEscape($this->NameOnCard) . '</CardHolderName>';
        $xml .= '<CardNumber>' . $xmlEscape($cardData['CardNumber']) . '</CardNumber>';
        $xml .= '<Amount>' . number_format($amount, 2, '.', '') . '</Amount>';
        $xml .= '<DateExpiry>' . $xmlEscape($cardData['ExpiryDate'] ?? '') . '</DateExpiry>';
        $xml .= '<Cvc2>' . $xmlEscape($cardData['CVVNumber'] ?? '') . '</Cvc2>';
        $xml .= '<Cvc2Presence>1</Cvc2Presence>';
        $xml .= '<InputCurrency>' . $xmlEscape($currency) . '</InputCurrency>';
        $xml .= '<TxnType>' . $xmlEscape($this->Config()->get('type')) . '</TxnType>';
        $xml .= '<TxnId>' . (int) $this->ID . '</TxnId>';
        $xml .= '<MerchantReference>' . (int) $this->OrderID . '_' . (int) $this->ID . '</MerchantReference>';
        $xml .= '</Txn>';
        unset($cardData);
        $URL = 'sec.paymentexpress.com/pxpost.aspx';

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, 'https://' . $URL);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $xml);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);

        $result = curl_exec($ch);
        curl_close($ch);
        unset($xml);

        if (! is_string($result) || '' === $result) {
            $this->Status = EcommercePayment::FAILURE_STATUS;
            $this->Message = 'The payment gateway could not be contacted.';
            $this->write();

            return EcommercePaymentFailure::create();
        }

        try {
            $params = new SimpleXMLElement($result);
        } catch (\Exception $exception) {
            $this->Status = EcommercePayment::FAILURE_STATUS;
            $this->Message = 'The payment gateway returned an invalid response.';
            $this->write();

            return EcommercePaymentFailure::create();
        }
        $txn = $params->Transaction;

        //save basic info (the ORM takes care of escaping for the database)
        $this->Response = print_r($params, 1);
        $this->Message = Convert::raw2xml(trim($txn->CardHolderResponseText . ' ' . $txn->CardHolderResponseDescription));
        $this->CardNumber = substr((string) $txn->CardNumber, -19);
        if (
            (1 === (int) $params->Success || 1 === (int) $txn['success']) &&
            round((float) $txn->Amount, 2) === $amount &&
            $currency === strtoupper(trim((string) $txn->CurrencyName)) &&
            trim($this->OrderID . '_' . $this->ID) === trim((string) $txn->MerchantReference)
        ) {
            $this->Status = EcommercePayment::SUCCESS_STATUS;
            $returnObject = EcommercePaymentSuccess::create();
        } else {
            $this->Status = EcommercePayment::FAILURE_STATUS;
            $returnObject = EcommercePaymentFailure::create();
        }
        $this->write();

        return $returnObject;
    }

    public function getRequestDetails()
    {
        return '<pre>' . htmlspecialchars((string) $this->Request) . '</pre>';
    }

    public function getResponseDetails()
    {
        return '<pre>' . htmlspecialchars((string) $this->Response) . '</pre>';
    }

    /**
     * Never write the full card number, the CVV or the expiry date to the database.
     * The values are kept in memory (for processPayment) and the database fields are masked / emptied.
     */
    protected function onBeforeWrite()
    {
        parent::onBeforeWrite();
        $this->stashAndMaskCardData();
    }

    protected function stashAndMaskCardData()
    {
        $cardNumber = preg_replace('/\s+/', '', (string) $this->CardNumber);
        if (ctype_digit($cardNumber) && strlen($cardNumber) >= 12) {
            $this->sensitiveCardData['CardNumber'] = $cardNumber;
            $this->CardNumber = str_repeat('*', strlen($cardNumber) - 4) . substr($cardNumber, -4);
        }
        if ('' !== (string) $this->CVVNumber) {
            $this->sensitiveCardData['CVVNumber'] = (string) $this->CVVNumber;
            $this->CVVNumber = '';
        }
        if ('' !== (string) $this->ExpiryDate) {
            $this->sensitiveCardData['ExpiryDate'] = (string) $this->ExpiryDate;
            $this->ExpiryDate = '';
        }
    }

    /**
     * are you running in test mode?
     *
     * @return bool
     */
    protected function isTest()
    {
        if ('yes' === $this->Config()->get('is_test') && 'no' === $this->Config()->get('is_live')) {
            return true;
        }
        if ('no' === $this->Config()->get('is_test') && 'yes' === $this->Config()->get('is_live')) {
            return false;
        }
        user_error('Class not set to live or test correctly.');

        return false;
    }
}
