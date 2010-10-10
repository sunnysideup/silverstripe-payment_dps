<?php
#******************************************************************************
#* Name          	: PxPay_Sample_Curl.php
#* Description   	: Direct Payment Solutions Payment Express PxPay PHP cURL Sample
#* Copyright	 	: Direct Payment Solutions 2009(c)
#* Date          	: 2009-04-10
#* References    	: http://www.paymentexpress.com/technical_resources/ecommerce_hosted/pxpay.html
#*@version 			: 1.0
#* Author 			: Thomas Treadwell
#******************************************************************************

# This file is a sample demonstrating integration with the PxPay interface using PHP with the cURL extension installed.  
#Inlcude PxPay objects
include "PxPay_Curl.inc.php";

  $PxPay_Url    = "https://www.paymentexpress.com/pxpay/pxaccess.aspx";
  $PxPay_Userid = "UserId"; #Important! Update with your UserId
  $PxPay_Key    =  "Encryptionkey"; #Important! Update with your Key
  
  #
  # MAIN
  #

  $pxpay = new PxPay_Curl( $PxPay_Url, $PxPay_Userid, $PxPay_Key );

  if (isset($_REQUEST["result"]))
  {
    # this is a redirection from the payments page.
    print_result();
  }
  elseif (isset($_REQUEST["Submit"]))
  {
    # this is a post back -- redirect to payments page.
    redirect_form();
  }
  else
  {
    # this is a fresh request -- display the purchase form.
    print_form();
  }


#******************************************************************************
# This function receives information back from the payments page,
# and displays it to the user.
#******************************************************************************
function print_result()
{
  global $pxpay;

  $enc_hex = $_REQUEST["result"];
  #getResponse method in PxPay object returns PxPayResponse object
  #which encapsulates all the response data
  $rsp = $pxpay->getResponse($enc_hex);

  if ($rsp->getSuccess() == "1")
  {
    $result = "The transaction was approved.";
  }
  else
  {
    $result = "The transaction was declined.";
  }

  # the following are the fields available in the PxPayResponse object
  $Success           = $rsp->getSuccess();   # =1 when request succeeds
  $AmountSettlement  = $rsp->getAmountSettlement();
  $AuthCode          = $rsp->getAuthCode();  # from bank
  $CardName          = $rsp->getCardName();  # e.g. "Visa"
  $CardNumber        = $rsp->getCardNumber(); # Truncated card number
  $DateExpiry        = $rsp->getDateExpiry(); # in mmyy format
  $DpsBillingId      = $rsp->getDpsBillingId();
  $BillingId    	 = $rsp->getBillingId();
  $CardHolderName    = $rsp->getCardHolderName();
  $DpsTxnRef	     = $rsp->getDpsTxnRef();
  $TxnType           = $rsp->getTxnType();
  $TxnData1          = $rsp->getTxnData1();
  $TxnData2          = $rsp->getTxnData2();
  $TxnData3          = $rsp->getTxnData3();
  $CurrencySettlement= $rsp->getCurrencySettlement();
  $ClientInfo        = $rsp->getClientInfo(); # The IP address of the user who submitted the transaction
  $TxnId             = $rsp->getTxnId();
  $CurrencyInput     = $rsp->getCurrencyInput();
  $EmailAddress      = $rsp->getEmailAddress();
  $MerchantReference = $rsp->getMerchantReference();
  $ResponseText		 = $rsp->getResponseText();
  $TxnMac            = $rsp->getTxnMac(); # An indication as to the uniqueness of a card used in relation to others


  print <<<HTMLEOF
<html>
<head>
<title>Direct Payment Solutions PxPay transaction result</title>
</head>
<body>
<h1>Direct Payment Solutions PxPay transaction result</h1>
<p>$result</p>
  <table border=1>
	<tr><th>Name</th>				<th>Value</th> </tr>
	<tr><td>Success</td>			<td>$Success</td></tr>
	<tr><td>TxnType</td>			<td>$TxnType</td></tr>
	<tr><td>CurrencyInput</td>		<td>$CurrencyInput</td></tr>
	<tr><td>MerchantReference</td>	<td>$MerchantReference</td></tr>
	<tr><td>TxnData1</td>			<td>$TxnData1</td></tr>
	<tr><td>TxnData2</td>			<td>$TxnData2</td></tr>
	<tr><td>TxnData3</td>			<td>$TxnData3</td></tr>
	<tr><td>AuthCode</td>			<td>$AuthCode</td></tr>
	<tr><td>CardName</td>			<td>$CardName</td></tr>
	<tr><td>CardHolderName</td>		<td>$CardHolderName</td></tr>
	<tr><td>CardNumber</td>			<td>$CardNumber</td></tr>
	<tr><td>DateExpiry</td>			<td>$DateExpiry</td></tr>
	<tr><td>CardHolderName</td>		<td>$CardHolderName</td></tr>
	<tr><td>ClientInfo</td>			<td>$ClientInfo</td></tr>
	<tr><td>TxnId</td>				<td>$TxnId</td></tr>
	<tr><td>EmailAddress</td>		<td>$EmailAddress</td></tr>
	<tr><td>DpsTxnRef</td>			<td>$DpsTxnRef</td></tr>
	<tr><td>BillingId</td>			<td>$BillingId</td></tr>
	<tr><td>DpsBillingId</td>		<td>$DpsBillingId</td></tr>
	<tr><td>AmountSettlement</td>	<td>$AmountSettlement</td></tr>
	<tr><td>CurrencySettlement</td>	<td>$CurrencySettlement</td></tr>
	<tr><td>TxnMac</td>				<td>$TxnMac</td></tr>
	<tr><td>ResponseText</td>		<td>$ResponseText</td></tr>
</table>
</body>
</html>
HTMLEOF;
}

#******************************************************************************
# This function prints a blank purchase form.
#******************************************************************************
function print_form()
{
  print <<<HTMLEOF
<html>
<head>
<title>Direct Payment Solutions PxPay transaction sample</title>
</head>
<body>
<h1>Direct Payment Solutions PxPay transaction result</h1>
<p>
You have indicated you would like to buy some widgets.
</p>
<p>
Please enter the number of widgets below, and enter your
shipping details.
</p>
<form method="post">
<table>
  <tr>
    <td>Quantity:</td>
    <td><input name="Quantity" type="text"/></td>
    <td>@ $19.95 ea</td>
  </tr>
  <tr>
    <td>Reference:</td>
    <td><input name="Reference" type="text"/></td>
  </tr>  
  <tr>
    <td>Ship to</td>
    <td></td>
  </tr>
  <tr>
    <td>Address line 1:</td>
    <td><input name="Address1" type="text"/></td>
  </tr>
  <tr>
    <td>Address line 2</td>
    <td><input name="Address2" type="text"/></td>
  </tr>
    <tr>
    <td>Address line 3</td>
    <td><input name="Address3" type="text"/></td>
  </tr>
</table>
<input name="Submit" type="submit" value="Submit"/>
Click submit to go to the secure payment page.
</form>
</body>
</html>
HTMLEOF;
}

#******************************************************************************
# This function formats data into a request and redirects to the
# Payments Page.
#******************************************************************************
function redirect_form()
{
  global $pxpay;

  $request = new PxPayRequest();

  $http_host   = getenv("HTTP_HOST");
  $request_uri = getenv("SCRIPT_NAME");
  $server_url  = "http://$http_host";
  #$script_url  = "$server_url/$request_uri"; //using this code before PHP version 4.3.4
  #$script_url  = "$server_url$request_uri"; //Using this code after PHP version 4.3.4
  $script_url = (version_compare(PHP_VERSION, "4.3.4", ">=")) ?"$server_url$request_uri" : "$server_url/$request_uri";


  # the following variables are read from the form
  $Quantity = $_REQUEST["Quantity"];
  $MerchantReference = $_REQUEST["Reference"];  
  $Address1 = $_REQUEST["Address1"];
  $Address2 = $_REQUEST["Address2"];
  $Address3 = $_REQUEST["Address3"];
  
  #Calculate AmountInput
  $AmountInput = 19.95 * $Quantity;
  
  #Generate a unique identifier for the transaction
  $TxnId = uniqid("ID");
  
  #Set PxPay properties
  $request->setMerchantReference($MerchantReference);
  $request->setAmountInput($AmountInput);
  $request->setTxnData1($Address1);
  $request->setTxnData2($Address2);
  $request->setTxnData3($Address3);
  $request->setTxnType("Purchase");
  $request->setCurrencyInput("NZD");
  $request->setEmailAddress("your_email@paymentexpress.com");
  $request->setUrlFail($script_url);			# can be a dedicated failure page
  $request->setUrlSuccess($script_url);			# can be a dedicated success page
  $request->setTxnId($TxnId);  
  
  #The following properties are not used in this case
  # $request->setEnableAddBillCard($EnableAddBillCard);    
  # $request->setBillingId($BillingId);
  # $request->setOpt($Opt);
  

  
  #Call makeRequest function to obtain input XML
  $request_string = $pxpay->makeRequest($request);
   
  #Obtain output XML
  $response = new MifMessage($request_string);
  
  #Parse output XML
  $url = $response->get_element_text("URI");
  $valid = $response->get_attribute("valid");
   
   #Redirect to payment page
   header("Location: ".$url);
}
?>
