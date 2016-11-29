<?php

/**
 *@author nicolaas[at]sunnysideup.co.nz
 *@description: OrderNumber and PaymentID
 *
 *
 **/

class DpsPxPayPayment extends EcommercePayment
{
    private static $db = array(
        'TxnRef' => 'Text',
        'DebugMessage' => 'HTMLText'
    );

    protected $Currency = "";
    public function setCurrency($s)
    {
        $this->Currency = $s;
    }

    // DPS Information

    private static $privacy_link = 'http://www.paymentexpress.com/privacypolicy.htm';

    private static $logo = 'payment_dps/images/dps_paymentexpress_small.png';


    // URLs

    // Please set from YAML. See _config/payment_dps.yml.example
    private static $credit_cards = array(
        /*'Visa' => 'ecommerce/images/paymentmethods/visa.jpg',
        'MasterCard' => 'ecommerce/images/paymentmethods/mastercard.jpg',
        'American Express' => 'ecommerce/images/paymentmethods/american-express.gif',
        'Dinners Club' => 'ecommerce/images/paymentmethods/dinners-club.jpg',
        'JCB' => 'ecommerce/images/paymentmethods/jcb.jpg'*/
    );

    public static function remove_credit_card($creditCard)
    {
        unset(self::$credit_cards[$creditCard]);
    }

    private static $email_debug = false;

    public function getCMSFields()
    {
        $fields = parent::getCMSFields();
        $fields->replaceField("DebugMessage", new ReadonlyField("DebugMessage", "Debug info"));
        return $fields;
    }

    public function getPaymentFormFields()
    {
        $logo = '<img src="' . $this->config()->get("logo"). '" alt="Credit card payments powered by DPS"/>';
        $privacyLink = '<a href="' . $this->config()->get("privacy_link"). '" target="_blank" title="Read DPS\'s privacy policy">' . $logo . '</a><br/>';
        $paymentsList = '';
        if ($cards = $this->config()->get("credit_cards")) {
            foreach ($cards as $name => $image) {
                $paymentsList .= '<img src="' . $image . '" alt="' . $name . '"/>';
            }
        }
        $fields = new FieldList(
            new LiteralField('DPSInfo', $privacyLink),
            new LiteralField('DPSPaymentsList', $paymentsList)
        );
        return $fields;
    }

    public function getPaymentFormRequirements()
    {
        return array();
    }

    /**
     * @param array $data The form request data - see OrderForm
     * @param OrderForm $form The form object submitted on
     *
     * @return EcommercePayment_Result
     */
    public function processPayment($data, $form)
    {
        $order = $this->Order();
        //if currency has been pre-set use this
        $currency = $this->Amount->Currency;
        //if amout has been pre-set, use this
        $amount = $this->Amount->Amount;
        if ($order) {
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
        if (!$amount && !empty($data["Amount"])) {
            $amount = floatval($data["Amount"]);
        }
        if (!$currency && !empty($data["Currency"])) {
            $currency = floatval($data["Currency"]);
        }
        //final backup for currency
        if (!$currency) {
            $currency = EcommercePayment::site_currency();
        }
        $this->Amount->Currency = $currency;
        $this->Amount->Amount = $amount;
        //no need to write here, as it will be done by BuildURL
        //$this->write();
        $url = $this->buildURL($amount, $currency);
        return $this->executeURL($url);
    }

    /**
     *
     * @param Float $amount
     * @param String $currency - e.g. NZD
     * @return String
     *
     */
    protected function buildURL($amount, $currency)
    {
        $commsObject = new DpsPxPayComs();

        /**
        * order details
        **/
        $commsObject->setTxnType(DpsPxPayComs::get_txn_type());
        $commsObject->setMerchantReference($this->ID);
        //replace any character that is NOT [0-9] or dot (.)
        $commsObject->setAmountInput(floatval(preg_replace("/[^0-9\.]/", "", $amount)));
        $commsObject->setCurrencyInput($currency);

        /**
        * details of the redirection
        **/
        $commsObject->setUrlFail(DpsPxPayPayment_Handler::absolute_complete_link());
        $commsObject->setUrlSuccess(DpsPxPayPayment_Handler::absolute_complete_link());

        /**
        * process payment data (check if it is OK and go forward if it is...
        **/
        $url = $commsObject->startPaymentProcess();
        $debugMessage = $commsObject->getDebugMessage();
        $this->DebugMessage = $debugMessage;
        $this->write();
        if ($this->config()->get("email_debug")) {
            $from = Email::config()->admin_email;
            $to = Email::config()->admin_email;
            $subject = "DPS Debug Information";
            $body = $debugMessage;
            $email = new Email($from, $to, $subject, $body);
            $email->send();
        }
        return $url;
    }

    public function executeURL($url)
    {
        $url = str_replace("&", "&amp;", $url);
        $url = str_replace("&amp;&amp;", "&amp;", $url);
        //$url = str_replace("==", "", $url);
        if ($url) {
            /**
            * build redirection page
            **/
            $page = new SiteTree();
            $page->Title = 'Redirection to DPS...';
            $page->Logo = '<img src="' . $this->config()->get("logo") . '" alt="Payments powered by DPS"/>';
            $page->Form = $this->DPSForm($url);
            $controller = new ContentController($page);
            Requirements::clear();
            Requirements::javascript(THIRDPARTY_DIR."/jquery/jquery.js");
            //Requirements::block(THIRDPARTY_DIR."/jquery/jquery.js");
            //Requirements::javascript(Director::protocol()."ajax.googleapis.com/ajax/libs/jquery/1.7.2/jquery.min.js");
            return EcommercePayment_Processing::create($controller->renderWith('PaymentProcessingPage'));
        } else {
            $page = new SiteTree();
            $page->Title = 'Sorry, DPS can not be contacted at the moment ...';
            $page->Logo = 'Sorry, an error has occured in contacting the Payment Processing Provider, please try again in a few minutes...';
            $page->Form = $this->DPSForm($url);
            $controller = new ContentController($page);
            Requirements::clear();
            Requirements::javascript(THIRDPARTY_DIR."/jquery/jquery.js");
            //Requirements::block(THIRDPARTY_DIR."/jquery/jquery.js");
            //Requirements::javascript(Director::protocol()."ajax.googleapis.com/ajax/libs/jquery/1.7.2/jquery.min.js");
            return EcommercePayment_Failure::create($controller->renderWith('PaymentProcessingPage'));
        }
    }

    public function DPSForm($url)
    {
        $urlWithoutAmpersand = Convert::raw2js(str_replace('&amp;', '&', $url));
        return <<<HTML
            <form id="PaymentFormDPS" method="post" action="$url">
                <input type="submit" value="pay now" />
            </form>
            <script type="text/javascript">
                jQuery(document).ready(function() {
                    if(!jQuery.browser.msie) {
                        jQuery("#PaymentFormDPS").submit();
                    }
                });
            </script>
HTML;
    }
}
