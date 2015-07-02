<?php

/**
 *
 *
 * @see: https://www.paymentexpress.com/Technical_Resources/Ecommerce_NonHosted/PxPost
 */


class DpsPxPost extends EcommercePayment {

	/**
	 * set the required privacy link as you see fit...
	 * also see: https://www.paymentexpress.com/About/Artwork_Downloads
	 * also see: https://www.paymentexpress.com/About/About_DPS/Privacy_Policy
	 * @var String
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
	 * @var String
	 */
	private static $is_test = "yes";

	/**
	 * we use yes / no as this is more reliable than a boolean value
	 * for configs
	 * @var boolean
	 */
	private static $is_live = "no";

	/**
	 *
	 * @var string
	 */
	private static $username = "";

	/**
	 *
	 * @var string
	 */
	private static $password = "";

	/**
	 * type: purchase / Authorisation / refund ...
	 * @var string
	 */
	private static $type = "Purchase";

	/**
	 * Incomplete (default): Payment created but nothing confirmed as successful
	 * Success: Payment successful
	 * Failure: Payment failed during process
	 * Pending: Payment awaiting receipt/bank transfer etc
	 */
	private static $db = array(
		"NameOnCard" => "Varchar(40)",
		"CardNumber" => "Varchar(255)",
		"ExpiryDate" => "Varchar(4)",
		"CVVNumber" => "Varchar(3)",
		"Request" => "Text",
		"Response" => "Text"
	);

	private static $casting = array(
		"ResponseDetails" => "HTMLText"
	);

	function getCMSFields(){
		$fields = parent::getCMSFields();
		$fields->addFieldToTab("Root.Details", new LiteralField("ResponseDetails", "Response Details", $this->getResponseDetails()));
		return $fields;
	}

	/**
	 * Return the payment form fields that should
	 * be shown on the checkout order form for the
	 * payment type. Example: for {@link DPSPayment},
	 * this would be a set of fields to enter your
	 * credit card details.
	 *
	 * @return FieldList
	 */
	function getPaymentFormFields(){
		$fieldList = new FieldList(
			array(
				new LiteralField("DPSPXPost_Logo", $this->Config()->get("dps_logo_and_link")),
				$creditCardField = new EcommerceCreditCardField(
					"DPSPXPost_CreditCard",
					_t("DpsPxPost.DPSPXPOST_CREDITCARD", "Card Number"),
					$this->CardNumber
				),
				$nameOnCardField = new TextField(
					"DPSPXPost_NameOnCard",
					_t("DpsPxPost.DPSPXPOST_NAMEONCARD", "Name on Card"),
					$this->NameOnCard
				),
				$expiryDateField = new ExpiryDateField(
					"DPSPXPost_ExpiryDate",
					_t("DpsPxPost.DPSPXPOST_EXPIRYDATE", "Expiry Date"),
					$this->ExpiryDate
				),
				$cvvNumberField = new TextField(
					"DPSPXPost_CVVNumber",
					_t("DpsPxPost.DPSPXPOST_CVVNumber", "Security Number"),
					$this->CVVNumber
				)
			)
		);
		$nameOnCardField->setAttribute("maxlength", "40");
		$cvvNumberField->setAttribute("maxlength", "4");
		$cvvNumberField->setAttribute("size", "4");
		$cvvNumberField->setAttribute("autocomplete", "off");
		return $fieldList;
	}

	/**
	 * Define what fields defined in {@link Order->getPaymentFormFields()}
	 * should be required.
	 *
	 * @see DPSPayment->getPaymentFormRequirements() for an example on how
	 * this is implemented.
	 *
	 * @return array
	 */
	function getPaymentFormRequirements(){
		return array(
			"DPSPXPost_CreditCard",
			"DPSPXPost_NameOnCard",
			"DPSPXPost_ExpiryDate",
			"DPSPXPost_CVVNumber"
		);
	}

	/**
	 * returns true if all the data is correct.
	 *
	 * @param array $data The form request data - see OrderForm
	 * @param OrderForm $form The form object submitted on
	 * @return Boolean
	 */
	function validatePayment($data, $form){
		$this->getDataFromForm($data);
		$errors = false;
		if(!$this->validCreditCard($this->CreditCard)) {
			$form->addErrorMessage(
				'DPSPXPost_CreditCard',
				_t('DPSPXPost.INVALID_CREDIT_CARD','Invalid credit card number.'),
				'bad'
			);
			$errors = true;
		}
		if(strlen($this->NameOnCard) < 3) {
			$form->addErrorMessage(
				'DPSPXPost_NameOnCard',
				_t('DPSPXPost.INVALID_NAME_ON_CARD','No card name provided.'),
				'bad'
			);
			$errors = true;
		}
		if(!$this->validExpiryDate($this->ExpiryDate)) {
			$form->addErrorMessage(
				'DPSPXPost_ExpiryDate',
				_t('DPSPXPost.INVALID_EXPIRY_DATE','Expiry date not valid.'),
				'bad'
			);
			$errors = true;
		}
		if(!$this->validCVV($this->CreditCard, $this->CVVNumber)) {
			$form->addErrorMessage(
				'DPSPXPost_CVVNumber',
				_t('DPSPXPost.INVALID_CVV_NUMBER','Invalid security number.'),
				'bad'
			);
			$errors = true;
		}
		if($errors) {
			$form->sessionMessage(_t('DPSPXPost.PLEASE_REVIEW_CARD_DETAILS','Please review your card details.'),'bad');
			return false;
		}
		return true;
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
	 */
	function processPayment($data, $form){
		//save data
		$this->getDataFromForm($data);
		$this->write();

		//get variables
		$isTest = $this->isTest();
		$order = $this->Order();
		//if currency has been pre-set use this
		$currency = strtoupper($this->Amount->Currency);
		//if amout has been pre-set, use this
		$amount = $this->Amount->Amount;
		$username = $this->Config()->get("username");
		$password = $this->Config()->get("password");
		if(!$username || !$password) {
			user_error("Make sure to set a username and password.");
		}

		$xml  = "<Txn>";
		$xml .= "<PostUsername>".$username."</PostUsername>";
		$xml .= "<PostPassword>".$password."</PostPassword>";
		$xml .= "<CardHolderName>".Convert::raw2xml($this->NameOnCard)."</CardHolderName>";
		$xml .= "<CardNumber>".$this->CreditCard."</CardNumber>";
		$xml .= "<Amount>".round($amount, 2)."</Amount>";
		$xml .= "<DateExpiry>".$this->ExpiryDate."</DateExpiry>";
		$xml .= "<Cvc2>".$this->CVVNumber."</Cvc2>";
		$xml .= "<Cvc2Presence>1</Cvc2Presence>";
		$xml .= "<InputCurrency>".Convert::raw2xml(strtoupper($currency))."</InputCurrency>";
		$xml .= "<TxnType>".Convert::raw2xml($this->Config()->get("type"))."</TxnType>";
		$xml .= "<TxnId>".$this->ID."</TxnId>";
		$xml .= "<MerchantReference>".$this->OrderID."</MerchantReference>";
		$xml .= "</Txn>";
		$URL = "sec.paymentexpress.com/pxpost.aspx";
		//echo "\n\n\n\nSENT:\n$cmdDoTxnTransaction\n\n\n\n\n$";

		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL,"https://".$URL);
		curl_setopt($ch, CURLOPT_POST, 1);
		curl_setopt($ch, CURLOPT_POSTFIELDS,$xml);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0); //Needs to be included if no *.crt is available to verify SSL certificates

		$result = curl_exec ($ch);
		curl_close ($ch);

		$params = new SimpleXMLElement($result);
		$txn = $params->Transaction;

		//save basic info
		$this->Response = Convert::raw2sql(print_r($params, 1));
		$this->Message = Convert::raw2sql($txn->CardHolderResponseText." ".$txn->CardHolderResponseDescription);
		$this->CardNumber = Convert::raw2sql($txn->CardNumber);
		if(
			$params->Success &&
			$amount == $txn->Amount &&
			$currency == $txn->CurrencyName &&
			trim($this->OrderID) == trim($txn->MerchantReference)
		) {
			$this->Status = "Success";
			$returnObject = new Payment_Success();
		}
		else {
			$this->Status = "Failure";
			$returnObject = new Payment_Failure();
		}
		$this->write();
		return $returnObject;
	}

	/**
	 * @param Array $data
	 */
	protected function getDataFromForm($data) {
		$this->CreditCard = trim(
			$data["DPSPXPost_CreditCard"][0].
			$data["DPSPXPost_CreditCard"][1].
			$data["DPSPXPost_CreditCard"][2].
			$data["DPSPXPost_CreditCard"][3]
		);

		$this->NameOnCard = trim($data["DPSPXPost_NameOnCard"]);
		$this->ExpiryDate =
			$data["DPSPXPost_ExpiryDate"]["month"].
			$data["DPSPXPost_ExpiryDate"]["year"];
		$this->CVVNumber = $data["DPSPXPost_CVVNumber"];
	}

	/**
	 * are you running in test mode?
	 *
	 * @return Boolean
	 */
	protected function isTest(){
		if($this->Config()->get("is_test") == "yes" && $this->Config()->get("is_live") == "no") {
			return true;
		}
		elseif($this->Config()->get("is_test") == "no" && $this->Config()->get("is_live") == "yes") {
			return false;
		}
		else {
			user_error("Class not set to live or test correctly.");
		}
	}

	function getResponseDetails(){
		return "<pre>".$this->Response."</pre>";
	}

}


