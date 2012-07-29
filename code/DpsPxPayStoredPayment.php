<?php

/**
 *@author nicolaas [at] sunny side up. co . nz
 *
 *
 **/


class DpsPxPayStoredPayment extends DpsPxPayPayment {

	protected static $pxaccess_url = 'https://sec.paymentexpress.com/pxpay/pxaccess.aspx';

	protected static $pxpost_url = 'https://sec.paymentexpress.com/pxpost.aspx';

	protected static $username = '';
		static function set_username($v) {self::$username = $v;}

	protected static $password = '';
		static function set_password($v) {self::$password = $v;}

	protected static $add_card_explanation = "Storing a Card means your Credit Card will be kept on file for your next purchase. ";
		function set_add_card_explanation($v) {self::$add_card_explanation = $v;}
		function get_add_card_explanation() {return self::$add_card_explanation;}

	function getPaymentFormFields() {
		$logo = '<img src="' . self::$logo . '" alt="Credit Card Payments Powered by DPS"/>';
		$privacyLink = '<a href="' . self::$privacy_link . '" target="_blank" title="Read DPS\'s privacy policy">' . $logo . '</a><br/>';
		$paymentsList = '';
		foreach(self::$credit_cards as $name => $image) {
			$paymentsList .= '<img src="' . $image . '" alt="' . $name . '"/>';
		}

		$fields = new FieldSet();
		$storedCards = null;
		if($m = Member::currentMember()) {
			$storedCards = DataObject::get('DpsPxPayStoredCard', 'MemberID = '.$m->ID);
		}

		$cardsDropdown = array('' => ' --- Select Stored Card ---');

		if($storedCards) {
			foreach($storedCards as $card) {
				$cardsDropdown[$card->BillingID] = $card->CardHolder.' - '.$card->CardNumber.' ('.$card->CardName.')';
			}
			$s = "";
			if($storedCards->count()>1) {
				$s = "s";
			}
			$cardsDropdown["deletecards"] = " --- Delete Stored Card$s --- ";
			$fields->push(new DropdownField('DPSUseStoredCard', 'Use a stored card?', $cardsDropdown, $value = $card->BillingID, $form = null, $emptyString = "--- use new Credit Card ---"));
		}
		else {
			$fields->push(new DropdownField('DPSStoreCard', '', array(1 => 'Store Credit Card', 0 => 'Do NOT Store Credit Card')));
			$fields->push(new LiteralField("AddCardExplanation", "<p>".self::get_add_card_explanation()."</p>"));
		}
		$fields->push(new LiteralField('DPSInfo', $privacyLink));
		$fields->push(new LiteralField('DPSPaymentsList', $paymentsList));
		Requirements::javascript(THIRDPARTY_DIR."/jquery/jquery.js");
		//Requirements::block(THIRDPARTY_DIR."/jquery/jquery.js");
		//Requirements::javascript(Director::protocol()."ajax.googleapis.com/ajax/libs/jquery/1.7.2/jquery.min.js");
		Requirements::javascript("payment_dps/javascript/DpxPxPayStoredPayment.js");
		return $fields;
	}

	function autoProcessPayment($amount, $ref) {
		$DPSUrl = $this->buildURL($amount, $ref, false);
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

	function processPayment($data, $form) {
		if(!isset($data["DPSUseStoredCard"])) {$data["DPSUseStoredCard"] = null;}
		if(!isset($data["DPSStoreCard"])) {$data["DPSStoreCard"] = null;}
		if(!isset($data["Amount"])) {USER_ERROR("There was no amount information for processing the payment.", E_USER_WARNING);}
		if($data["DPSUseStoredCard"] == "deletecards") {
			//important!!!
			$data["DPSUseStoredCard"] = null;
			if($m = Member::currentMember()) {
				$storedCards = DataObject::get('DpsPxPayStoredCard', 'MemberID = '.$m->ID);
				if($storedCards) {
					foreach($storedCards as $card) {
						$card->delete();
					}
					if($storedCards = DataObject::get('DpsPxPayStoredCard', 'MemberID = '.$m->ID)) {
						DB::query("DELETE FROM DpsPxPayStoredCard WHERE MemberID = ".$m->ID);
					}
				}
			}
		}
		elseif($data["DPSUseStoredCard"]) {
			return $this->processViaPostRatherThanPxPay($data, $form, $data["DPSUseStoredCard"]);
		}
		$url = $this->buildURL($data["Amount"], $data["DPSUseStoredCard"], $data["DPSStoreCard"]);
		return $this->executeURL($url);
	}




	function processViaPostRatherThanPxPay($data, $form, $cardToUse) {

		// 1) Main Settings

		$inputs['PostUsername'] = self::$username;
		$inputs['PostPassword'] = self::$password;

		// 2) Payment Informations

		$inputs['Amount'] = $this->Amount->Amount;
		$inputs['InputCurrency'] = $this->Amount->Currency;
		$inputs['TxnId'] = $this->ID;
		$inputs['TxnType'] = 'Purchase';
		$inputs["MerchantReference"] = $this->ID;

		// 3) Credit Card Informations
		$inputs["DpsBillingId"] = $cardToUse;


		// 4) DPS Transaction Sending

		$responseFields = $this->doPayment($inputs);
		// 5) DPS Response Management

		if(isset($responseFields['SUCCESS']) && $responseFields['SUCCESS']) {
			$this->Status = 'Success';
			$result = new Payment_Success();
		}
		else {
			$this->Status = 'Failure';
			$result = new Payment_Failure();
		}
		if(isset($responseFields['DPSTXNREF'])) {
			if($transactionRef = $responseFields['DPSTXNREF']) $this->TxnRef = $transactionRef;
		}

		if(isset($responseFields['HELPTEXT'])) {
			if($helpText = $responseFields['HELPTEXT']) {
				$this->Message = $helpText;
			}
		}
		if(isset($responseFields['RESPONSETEXT'])) {
			if($responseText = $responseFields['RESPONSETEXT']) {
				$this->Message .= $responseText;
			}
		}

		$this->write();
		return $result;
	}

	function doPayment(array $inputs) {

		// 1) Transaction Creation
		$transaction = "<Txn>";
		foreach($inputs as $name => $value) {
			if($name == "Amount") {
				$value = number_format($value, 2, '.', '');
			}
			$transaction .= "<$name>$value</$name>";
		}
		$transaction .= "</Txn>";

		// 2) CURL Creation
		$clientURL = curl_init();
		curl_setopt($clientURL, CURLOPT_URL, self::$pxpost_url);
		curl_setopt($clientURL, CURLOPT_POST, 1);
		curl_setopt($clientURL, CURLOPT_POSTFIELDS, $transaction);
		curl_setopt($clientURL, CURLOPT_RETURNTRANSFER, 1);
		//curl_setopt($clientURL, CURLOPT_SSLVERSION, 3);

		// 3) CURL Execution

		$resultXml = curl_exec($clientURL);

		// 4) CURL Closing

		curl_close ($clientURL);

		// 5) XML Parser Creation

		$xmlParser = xml_parser_create();
		$values = null;
		$indexes = null;
		xml_parse_into_struct($xmlParser, $resultXml, $values, $indexes);
		xml_parser_free($xmlParser);

		// 6) XML Result Parsed In A PHP Array

		$resultPhp = array();
		$level = array();
		foreach($values as $xmlElement) {
			if($xmlElement['type'] == 'open') {
				if(array_key_exists('attributes', $xmlElement)) {
					$arrayValues = array_values($xmlElement['attributes']);
					list($level[$xmlElement['level']], $extra) = $arrayValues;
				}
				else {
					$level[$xmlElement['level']] = $xmlElement['tag'];
				}
			}
			else if ($xmlElement['type'] == 'complete') {
				$startLevel = 1;
				$phpArray = '$resultPhp';
				while($startLevel < $xmlElement['level']) $phpArray .= '[$level['. $startLevel++ .']]';
				$phpArray .= '[$xmlElement[\'tag\']] = array_key_exists(\'value\', $xmlElement)? $xmlElement[\'value\'] : null;';
				eval($phpArray);
			}
		}
		if(!isset($resultPhp['TXN'])) {
			return false;
		}
		$result = $resultPhp['TXN'];
		return $result;
	}



	protected function buildURL($amount, $cardToUse = '', $storeCard = false) {
		$commsObject = new DpsPxPayComs();

		/**
		* order details
		**/
		$commsObject->setTxnType('Purchase');
		$commsObject->setMerchantReference($this->ID);
		//replace any character that is NOT [0-9] or dot (.)
		$commsObject->setAmountInput(floatval(preg_replace("/[^0-9\.]/", "", $amount)));

		if(isset($cardToUse)) {
			$commsObject->setBillingId($cardToUse);
		}
		else if($storeCard) {
			$commsObject->setEnableAddBillCard(1);
		}

		/**
		* details of the redirection
		**/
		$link = DpsPxPayStoredPayment_Handler::absolute_complete_link();
		$commsObject->setUrlFail($link);
		$commsObject->setUrlSuccess($link);

		/**
		* process payment data (check if it is OK and go forward if it is...
		**/
		$url = $commsObject->startPaymentProcess();

		return $url;

	}


}

class DpsPxPayStoredPayment_Handler extends DpsPxPayPayment_Handler {

	protected static $url_segment = 'dpspxpaystoredpayment';
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
		if($payment = DataObject::get_by_id('DpsPxPayStoredPayment', $response->getMerchantReference())) {
			if($payment->Status != 'Success') {
				if(1 == $response->getSuccess()) {
					$payment->Status = 'Success';

					if($response->DpsBillingId) {
						$existingCard = DataObject::get_one('DpsPxPayStoredCard', 'BillingID = '.$response->DpsBillingId);

						if($existingCard == false) {
							$storedCard = new DpsPxPayStoredCard();
							$storedCard->BillingID = $response->DpsBillingId;
							$storedCard->CardName = $response->CardName;
							$storedCard->CardHolder = $response->CardHolderName;
							$storedCard->CardNumber = $response->CardNumber;
							$storedCard->MemberID = $payment->Order()->MemberID;
							$storedCard->write();
						}
					}
				}
				else {
					$payment->Status = 'Failure';
				}
				if($DpsTxnRef = $response->getDpsTxnRef()) $payment->TxnRef = $DpsTxnRef;
				if($ResponseText = $response->getResponseText()) $payment->Message = $ResponseText;
				$payment->write();
			}
			$payment->redirectToOrder();
		}
		else {
			USER_ERROR("could not find payment with matching ID", E_USER_WARNING);
		}
		return;
	}
}
