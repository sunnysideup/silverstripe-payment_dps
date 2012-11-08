<?php

/**
 *@author nicolaas[at]sunnysideup.co.nz
 *@description: OrderNumber and PaymentID
 *
 *
 **/

class DpsPxPayPayment extends Payment {

	static $db = array(
		'TxnRef' => 'Text',
		'DebugMessage' => 'HTMLText'
	);

	protected $Currency = "";
		function setCurrency($s) {$this->Currency = $s;}

	// DPS Information

	protected static $privacy_link = 'http://www.paymentexpress.com/privacypolicy.htm';

	protected static $logo = 'payment_dps/images/dps_paymentexpress_small.png';

	// URLs

	protected static $credit_cards = array(
		'Visa' => 'payment/images/payments/methods/visa.jpg',
		'MasterCard' => 'payment/images/payments/methods/mastercard.jpg',
		'American Express' => 'payment/images/payments/methods/american-express.gif',
		'Dinners Club' => 'payment/images/payments/methods/dinners-club.jpg',
		'JCB' => 'payment/images/payments/methods/jcb.jpg'
	);

	static function remove_credit_card($creditCard) {unset(self::$credit_cards[$creditCard]);}

	static $email_debug = false;

	function getCMSFields() {
		$fields = parent::getCMSFields();
		$fields->replaceField("DebugMessage", new ReadonlyField("DebugMessage", "Debug info"));
		return $fields;
	}

	function getPaymentFormFields() {
		$logo = '<img src="' . self::$logo . '" alt="Credit card payments powered by DPS"/>';
		$privacyLink = '<a href="' . self::$privacy_link . '" target="_blank" title="Read DPS\'s privacy policy">' . $logo . '</a><br/>';
		$paymentsList = '';
		if(self::$credit_cards) {
			foreach(self::$credit_cards as $name => $image) {
				$paymentsList .= '<img src="' . $image . '" alt="' . $name . '"/>';
			}
		}
		$fields = new FieldSet(
			new LiteralField('DPSInfo', $privacyLink),
			new LiteralField('DPSPaymentsList', $paymentsList)
		);
		return $fields;
	}

	function getPaymentFormRequirements() {
		return array();
	}

	function processPayment($data, $form) {
		$order = $this->Order();
		if($order) {
			$amount = $order->TotalOutstanding();
		}
		else {
			$amount = floatval($data["Amount"]);
		}
		$url = $this->buildURL($amount);
		return $this->executeURL($url);
	}

	protected function buildURL($amount) {
		$commsObject = new DpsPxPayComs();

		/**
		* order details
		**/
		$commsObject->setTxnType('Purchase');
		$commsObject->setMerchantReference($this->ID);
		//replace any character that is NOT [0-9] or dot (.)
		$commsObject->setAmountInput(floatval(preg_replace("/[^0-9\.]/", "", $amount)));
		if($this->Currency) {
			$commsObject->setCurrencyInput($this->Currency);
		}
		else {
			$commsObject->setCurrencyInput(Payment::site_currency());
		}

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
		if(self::$email_debug) {
			$from = Email::getAdminEmail();
			$to = Email::getAdminEmail();
			$subject = "DPS Debug Information";
			$body = $debugMessage;
			$email = new Email($from , $to , $subject , $body);
			$email->send();
		}
		return $url;
	}

	function executeURL($url) {
		$url = str_replace("&", "&amp;", $url);
		$url = str_replace("&amp;&amp;", "&amp;", $url);
		//$url = str_replace("==", "", $url);
		if($url) {
			/**
			* build redirection page
			**/
			$page = new Page();
			$page->Title = 'Redirection to DPS...';
			$page->Logo = '<img src="' . self::$logo . '" alt="Payments powered by DPS"/>';
			$page->Form = $this->DPSForm($url);
			$controller = new ContentController($page);
			Requirements::clear();
			Requirements::javascript(THIRDPARTY_DIR."/jquery/jquery.js");
			//Requirements::block(THIRDPARTY_DIR."/jquery/jquery.js");
			//Requirements::javascript(Director::protocol()."ajax.googleapis.com/ajax/libs/jquery/1.7.2/jquery.min.js");
			return new Payment_Processing($controller->renderWith('PaymentProcessingPage'));
		}
		else {
			$page = new Page();
			$page->Title = 'Sorry, DPS can not be contacted at the moment ...';
			$page->Logo = 'Sorry, an error has occured in contacting the Payment Processing Provider, please try again in a few minutes...';
			$page->Form = $this->DPSForm($url);
			$controller = new ContentController($page);
			Requirements::javascript(THIRDPARTY_DIR."/jquery/jquery.js");
			//Requirements::block(THIRDPARTY_DIR."/jquery/jquery.js");
			//Requirements::javascript(Director::protocol()."ajax.googleapis.com/ajax/libs/jquery/1.7.2/jquery.min.js");
			return new Payment_Failure($controller->renderWith('PaymentProcessingPage'));
		}
	}

	function DPSForm($url) {
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

class DpsPxPayPayment_Handler extends Controller {

	protected static $url_segment = 'dpspxpaypayment';
		static function set_url_segment($v) { self::$url_segment = $v;}
		static function get_url_segment() { return self::$url_segment;}

	static function complete_link() {
		return self::$url_segment . '/paid/';
	}

	static function absolute_complete_link() {
		return Director::AbsoluteURL(self::complete_link());
	}

	function paid() {
		$commsObject = new DpsPxPayComs();
		$response = $commsObject->processRequestAndReturnResultsAsObject();
		if($payment = DataObject::get_by_id('DpsPxPayPayment', $response->getMerchantReference())) {
			if(1 == $response->getSuccess()) {
				$payment->Status = 'Success';
			}
			else {
				$payment->Status = 'Failure';
			}
			if($DpsTxnRef = $response->getDpsTxnRef()) $payment->TxnRef = $DpsTxnRef;
			if($ResponseText = $response->getResponseText()) $payment->Message = $ResponseText;
			$payment->write();
			$payment->redirectToOrder();
		}
		else {
			USER_ERROR("could not find payment with matching ID", E_USER_WARNING);
		}
		return;
	}


}
