<?php
/**
 *@author nicolaas [at] sunnysideup.co.nz
 **/

class DpsPxPayComs {

	/**
	* seller details - are always the same
	* can be set like this DpsPxPayComs::set_pxpay_userid("blabla"); in _config file.
	**/
  protected static $pxpay_url    = "https://www.paymentexpress.com/pxpay/pxaccess.aspx";
		static function set_pxpay_url($v)                       {self::$pxpay_url = $v;}
	protected static $pxpay_userid = "";
		static function set_pxpay_userid($v)                    {self::$pxpay_userid = $v;}
	protected static $pxpay_encryption_key =  "";
		static function set_pxpay_encryption_key($v)            {self::$pxpay_encryption_key = $v;}
	protected static $alternative_thirdparty_folder =  "";
		static function set_alternative_thirdparty_folder($v)    {self::$alternative_thirdparty_folder = $v;}
	protected static $overriding_txn_type =  ""; //e.g. AUTH
		static function set_overriding_txn_type($v)    {self::$overriding_txn_type = $v;}

	/**
	* customer details
	**/

	protected $TxnData1 = "";
		public function setTxnData1($v)          { $this->TxnData1 = $v;}
	protected $TxnData2 = "";
		public function setTxnData2($v)          { $this->TxnData2 = $v;}
	protected $TxnData3 = "";
		public function setTxnData3($v)          { $this->TxnData3 = $v;}
	protected $EmailAddress = "";
		public function setEmailAddress($v)      { $this->EmailAddress = $v;}

	/**
	* order details
	**/
	protected $AmountInput = 0;
		public function setAmountInput($v)       { $this->AmountInput = $v;}
	protected $MerchantReference = "";
		public function setMerchantReference($v) {$this->MerchantReference = $v;}
	protected $CurrencyInput = "NZD";
		public function setCurrencyInput($v)     {$this->CurrencyInput = $v;}
	protected $TxnType = "Purchase";
		public function setTxnType($v)           {$this->TxnType = $v; if(self::$overriding_txn_type) {$this->TxnType = self::$overriding_txn_type;}}
	protected $TxnId = "";
		public function setTxnId($v)             {$this->TxnId = $v; }

	/**
	* details of the redirection
	**/
	protected $setUrlFail = "";
		public function setUrlFail($v)           { $this->UrlFail = $v;}
	protected $setUrlSuccess = "";
		public function setUrlSuccess($v)        { $this->UrlSuccess = $v;}

	/**
	 * Details to use stored cards
	 */
	protected $EnableAddBillCard = 0;
		public function setEnableAddBillCard($v)             {$this->EnableAddBillCard = $v;}
	protected $BillingId = 0;
		public function setBillingId($v)             {$this->BillingId = $v;}

	/**
	* external object
	**/
	protected $PxPayObject = null;

	function __construct() {
		if(!self::$alternative_thirdparty_folder) {
			self::$alternative_thirdparty_folder = Director::baseFolder().'/payment_dps/code/thirdparty';
		}
		require_once(self::$alternative_thirdparty_folder."/PxPay_Curl.inc.php");
		if(!self::$pxpay_url)            {user_error("error in DpsPxPayComs::__construct, self::$pxpay_url not set. ", E_USER_WARNING);}
		if(!self::$pxpay_userid)         {user_error("error in DpsPxPayComs::__construct, self::$pxpay_userid not set. ", E_USER_WARNING);}
		if(!self::$pxpay_encryption_key) {user_error("error in DpsPxPayComs::__construct, self::$pxpay_encryption_key not set. ", E_USER_WARNING);}

	  $this->PxPayObject = new PxPay_Curl(
			self::$pxpay_url,
			self::$pxpay_userid,
			self::$pxpay_encryption_key
		);
	}

	/*
	 * This function formats data into a request and returns redirection URL
	 * NOTE: you will need to set all the variables prior to running this.
	 * e.g. $myDPSPxPayComsObject->setMerchantReference("myreferenceHere");
	 **/
	function startPaymentProcess() {

		if(!$this->TxnId) {$this->TxnId = uniqid("ID");}
		$request = new PxPayRequest();

		#Set PxPay properties
		if($this->MerchantReference) {$request->setMerchantReference($this->MerchantReference);}  else { user_error("error in DpsPxPayComs::startPaymentProcess, MerchantReference not set. ", E_USER_WARNING);}
		if($this->AmountInput)       {$request->setAmountInput($this->AmountInput);}              else { user_error("error in DpsPxPayComs::startPaymentProcess, AmountInput not set. ", E_USER_WARNING);}
		if($this->TxnData1)          {$request->setTxnData1($this->TxnData1);}
		if($this->TxnData2)          {$request->setTxnData2($this->TxnData2);}
		if($this->TxnData3)          {$request->setTxnData3($this->TxnData3);}
		if($this->TxnType)           {$request->setTxnType($this->TxnType);}                      else { user_error("error in DpsPxPayComs::startPaymentProcess, TxnType not set. ", E_USER_WARNING);}
		if($this->CurrencyInput)     {$request->setCurrencyInput($this->CurrencyInput);}          else { user_error("error in DpsPxPayComs::startPaymentProcess, CurrencyInput not set. ", E_USER_WARNING);}
		if($this->EmailAddress)      {$request->setEmailAddress($this->EmailAddress);}
		if($this->UrlFail)           {$request->setUrlFail($this->UrlFail);	}                     else { user_error("error in DpsPxPayComs::startPaymentProcess, UrlFail not set. ", E_USER_WARNING);}
		if($this->UrlSuccess)        {$request->setUrlSuccess($this->UrlSuccess);}                else { user_error("error in DpsPxPayComs::startPaymentProcess, UrlSuccess not set. ", E_USER_WARNING);}
		if($this->TxnId)             {$request->setTxnId($this->TxnId);}
		if($this->EnableAddBillCard)             {$request->setEnableAddBillCard($this->EnableAddBillCard);}
		if($this->BillingId)             {$request->setBillingId($this->BillingId);}

		/* TODO:
		$request->setEnableAddBillCard($EnableAddBillCard);
		$request->setBillingId($BillingId);
		$request->setOpt($Opt);
		*/

		#Call makeRequest function to obtain input XML
		$request_string = $this->PxPayObject->makeRequest($request);

		#Obtain output XML
		$response = new MifMessage($request_string);

		#Parse output XML
		$url = $response->get_element_text("URI");
		$valid = $response->get_attribute("valid");

		#Redirect to payment page
		return $url;
	}


	/*
	 * This function receives information back from the payments page as a response object
	 * --------------------- RESPONSE DATA ---------------------
	 * $Success           = $resonseObject->getSuccess();   # =1 when request succeeds
	 * $AmountSettlement  = $resonseObject->getAmountSettlement();
	 * $AuthCode          = $resonseObject->getAuthCode();  # from bank
	 * $CardName          = $resonseObject->getCardName();  # e.g. "Visa"
	 * $CardNumber        = $resonseObject->getCardNumber(); # Truncated card number
	 * $DateExpiry        = $resonseObject->getDateExpiry(); # in mmyy format
	 * $DpsBillingId      = $resonseObject->getDpsBillingId();
	 * $BillingId         = $resonseObject->getBillingId();
	 * $CardHolderName    = $resonseObject->getCardHolderName();
	 * $DpsTxnRef	        = $resonseObject->getDpsTxnRef();
	 * $TxnType           = $resonseObject->getTxnType();
	 * $TxnData1          = $resonseObject->getTxnData1();
	 * $TxnData2          = $resonseObject->getTxnData2();
	 * $TxnData3          = $resonseObject->getTxnData3();
	 * $CurrencySettlement= $resonseObject->getCurrencySettlement();
	 * $ClientInfo        = $resonseObject->getClientInfo(); # The IP address of the user who submitted the transaction
	 * $TxnId             = $resonseObject->getTxnId();
	 * $CurrencyInput     = $resonseObject->getCurrencyInput();
	 * $EmailAddress      = $resonseObject->getEmailAddress();
	 * $MerchantReference = $resonseObject->getMerchantReference();
	 * $ResponseText      = $resonseObject->getResponseText();
	 * $TxnMac            = $resonseObject->getTxnMac(); # An indication as to the uniqueness of a card used in relation to others
	 *
	 * also see: https://www.paymentexpress.com/technical_resources/ecommerce_hosted/error_codes.html
	 **/

	function processRequestAndReturnResultsAsObject() {
		#getResponse method in PxPay object returns PxPayResponse object
		#which encapsulates all the response data
		return $this->PxPayObject->getResponse($_REQUEST["result"]);
	}



	function debug() {
		debug::show("debugging DpsPxPayComs");
		print_r($this);
		print_r($this->PxPayObject);
	}
}
