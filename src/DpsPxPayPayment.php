<?php

namespace Sunnysideup\PaymentDps;

use SilverStripe\CMS\Controllers\ContentController;
use SilverStripe\CMS\Model\SiteTree;
use SilverStripe\Control\Email\Email;
use SilverStripe\Core\Manifest\ModuleResourceLoader;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\Form;
use SilverStripe\Forms\LiteralField;
use SilverStripe\Forms\ReadonlyField;
use SilverStripe\ORM\FieldType\DBField;
use SilverStripe\ORM\FieldType\DBMoney;
use SilverStripe\View\Requirements;
use Sunnysideup\Ecommerce\Forms\OrderForm;
use Sunnysideup\Ecommerce\Model\Money\EcommercePayment;
use Sunnysideup\Ecommerce\Model\Order;
use Sunnysideup\Ecommerce\Money\Payment\PaymentResults\EcommercePaymentFailure;
use Sunnysideup\Ecommerce\Money\Payment\PaymentResults\EcommercePaymentProcessing;
use Sunnysideup\PaymentDps\Control\DpsPxPayPaymentHandler;

/**
 * Class \Sunnysideup\PaymentDps\DpsPxPayPayment
 *
 * @property string $TxnRef
 * @property string $DebugMessage
 * @property float $RandomDeduction
 * @property DBMoney $SettlementAmount
 */
class DpsPxPayPayment extends EcommercePayment
{
    private static $table_name = 'DpsPxPayPayment';

    private static $db = [
        'TxnRef' => 'Text',
        'DebugMessage' => 'HTMLText',
        'RandomDeduction' => 'Currency',
        'SettlementAmount' => 'Money',
    ];

    // DPS Information

    private static $privacy_link = 'http://www.paymentexpress.com/privacypolicy.htm';

    private static $logo = 'sunnysideup/payment_dps: client/images/dps_paymentexpress_small.png';

    // URLs

    // Please set from YAML. See _config/payment_dps.yml.example
    private static $credit_cards = [
    /*'Visa' => 'ecommerce/images/paymentmethods/visa.jpg',
        'MasterCard' => 'ecommerce/images/paymentmethods/mastercard.jpg',
        'American Express' => 'ecommerce/images/paymentmethods/american-express.gif',
        'Dinners Club' => 'ecommerce/images/paymentmethods/dinners-club.jpg',
        'JCB' => 'ecommerce/images/paymentmethods/jcb.jpg'*/];

    private static $email_debug = false;

    public function setCurrency(string $s): self
    {
        $this->Currency = $s;

        return $this;
    }

    public static function remove_credit_card($creditCard)
    {
        unset(self::$credit_cards[$creditCard]);
    }

    public function getCMSFields()
    {
        $fields = parent::getCMSFields();
        $fields->replaceField('DebugMessage', new ReadonlyField('DebugMessage', 'Debug info'));

        return $fields;
    }

    public function getPaymentFormFields($amount = 0, ?Order $order = null): FieldList
    {
        $logo = $this->getLogoResource();
        $privacyLink = '<a href="' . $this->config()->get('privacy_link') . '" target="_blank" title="Read DPS\'s privacy policy">' . $logo . '</a><br/>';
        $paymentsList = '';
        $cards = $this->config()->get('credit_cards');
        if ($cards) {
            foreach ($cards as $name => $image) {
                $paymentsList .= '<img src="' . $image . '" alt="' . $name . '"/>';
            }
        }

        return new FieldList(
            new LiteralField('DPSInfo', $privacyLink),
            new LiteralField('DPSPaymentsList', $paymentsList)
        );
    }

    public function getLogoResource()
    {
        $logo = $this->config()->get('logo');
        $src = ModuleResourceLoader::singleton()->resolveURL($logo);

        return DBField::create_field(
            'HTMLText',
            '<img src="' . $src . '" alt="Credit card payments powered by DPS"/>'
        );
    }

    public function getPaymentFormRequirements(): array
    {
        return [];
    }

    /**
     * @param array $data The form request data - see OrderForm
     * @param Form  $form The form object submitted on
     *
     * @return \Sunnysideup\Ecommerce\Money\Payment\EcommercePaymentResult
     */
    public function processPayment($data, Form $form)
    {
        $order = $this->getOrderCached();
        //if currency has been pre-set use this
        $currency = $this->Amount->Currency;
        //if amout has been pre-set, use this
        $amount = $this->Amount->Amount;
        if ($order && $order->exists()) {
            //amount may need to be adjusted to total outstanding
            //or amount may not have been set yet
            $amount = $order->TotalOutstanding();
            //get currency from Order
            //this is better than the pre-set currency one
            //which may have been set to the default
            $currencyObject = $order->CurrencyUsed();
            if ($currencyObject) {
                $currency = $currencyObject->Code;
            }
        }
        if (! $amount && ! empty($data['Amount'])) {
            $amount = (float) $data['Amount'];
        }
        if (! $currency && ! empty($data['Currency'])) {
            $currency = (string) $data['Currency'];
        }
        //final backup for currency
        if (! $currency) {
            $currency = EcommercePayment::site_currency();
        }
        $this->Amount->Currency = $currency;
        $this->Amount->Amount = $amount;
        //no need to write here, as it will be done by BuildURL
        //$this->write();
        if ($this->hasRandomDeduction()) {
            $randomDeduction = $this->setAndReturnRandomDeduction();
            if ($randomDeduction) {
                $amount -= $randomDeduction;
            }
        }
        $url = $this->buildURL($amount, $currency);

        return $this->executeURL($url);
    }

    public function executeURL($url)
    {
        $url = str_replace('&', '&amp;', $url);
        $url = str_replace('&amp;&amp;', '&amp;', $url);
        //$url = str_replace("==", "", $url);
        if ($url) {
            /**
             * build redirection page.
             */
            $page = new SiteTree();
            $page->Title = 'Redirection to DPS...';
            $page->Logo = $this->getLogoResource();
            $page->Form = $this->DPSForm($url);
            $controller = new ContentController($page);
            Requirements::clear();
            Requirements::javascript('https://ajax.googleapis.com/ajax/libs/jquery/3.6.1/jquery.min.js');

            return EcommercePaymentProcessing::create($controller->RenderWith('Sunnysideup\Ecommerce\PaymentProcessingPage'));
        }
        $page = new SiteTree();
        $page->Title = 'Sorry, DPS can not be contacted at the moment ...';
        $page->Logo = 'Sorry, an error has occured in contacting the Payment Processing Provider, please try again in a few minutes...';
        $page->Form = $this->DPSForm($url);
        $controller = new ContentController($page);
        Requirements::clear();
        Requirements::javascript('https://ajax.googleapis.com/ajax/libs/jquery/3.6.1/jquery.min.js');

        return EcommercePaymentFailure::create($controller->RenderWith('Sunnysideup\Ecommerce\PaymentProcessingPage'));
    }

    public function DPSForm($url)
    {
        return DBField::create_field(
            'HTMLText',
            '<form id="PaymentFormDPS" method="post" action="' . $url . '">
                <input type="submit" value="pay now" />
            </form>
            <script type="text/javascript">
                jQuery(document).ready(function() {
                    jQuery("#PaymentFormDPS").submit();
                });
            </script>'
        );
    }

    protected function hasRandomDeduction(): bool
    {
        return false;
    }

    protected function setAndReturnRandomDeduction(): float
    {
        return 0;
    }

    /**
     * @param float  $amount
     * @param string $currency - e.g. NZD
     *
     * @return string
     */
    protected function buildURL($amount, $currency)
    {
        $commsObject = new DpsPxPayComs();

        // order details
        $commsObject->setTxnType(DpsPxPayComs::get_txn_type());
        $commsObject->setMerchantReference($this->ID);
        //replace any character that is NOT [0-9] or dot (.)

        $commsObject->setAmountInput(floatval(preg_replace('/[^0-9\\.]/', '', (string) $amount)));
        $commsObject->setCurrencyInput($currency);

        // details of the redirection
        $commsObject->setUrlFail(DpsPxPayPaymentHandler::absolute_complete_link());
        $commsObject->setUrlSuccess(DpsPxPayPaymentHandler::absolute_complete_link());

        /**
         * process payment data (check if it is OK and go forward if it is...
         */
        $url = $commsObject->startPaymentProcess();
        $debugMessage = $commsObject->getDebugMessage();
        $this->DebugMessage = $debugMessage;
        $this->write();
        if ($this->config()->get('email_debug')) {
            $from = Email::config()->admin_email;
            $to = Email::config()->admin_email;
            $subject = 'DPS Debug Information';
            $body = $debugMessage;
            $email = new Email($from, $to, $subject, $body);
            $email->send();
        }

        return $url;
    }
}
