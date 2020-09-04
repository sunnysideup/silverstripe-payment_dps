2020-06-26 10:50

# running php upgrade upgrade see: https://github.com/silverstripe/silverstripe-upgrader
cd /var/www/upgrades/payment_dps
php /var/www/upgrader/vendor/silverstripe/upgrader/bin/upgrade-code upgrade /var/www/upgrades/payment_dps/payment_dps  --root-dir=/var/www/upgrades/payment_dps --write -vvv
Writing changes for 11 files
Running upgrades on "/var/www/upgrades/payment_dps/payment_dps"
[2020-06-26 10:50:58] Applying RenameClasses to PaymentDpsTest.php...
[2020-06-26 10:50:58] Applying ClassToTraitRule to PaymentDpsTest.php...
[2020-06-26 10:50:58] Applying UpdateConfigClasses to config.yml...
[2020-06-26 10:50:58] Applying UpdateConfigClasses to routes.yml...
[2020-06-26 10:50:58] Applying RenameClasses to DpsPxPayment_Handler.php...
[2020-06-26 10:50:58] Applying ClassToTraitRule to DpsPxPayment_Handler.php...
[2020-06-26 10:50:58] Applying RenameClasses to DpsPxPayPayment_Handler.php...
[2020-06-26 10:50:58] Applying ClassToTraitRule to DpsPxPayPayment_Handler.php...
[2020-06-26 10:50:58] Applying RenameClasses to DpsPxPayStoredPayment_Handler.php...
[2020-06-26 10:50:58] Applying ClassToTraitRule to DpsPxPayStoredPayment_Handler.php...
[2020-06-26 10:50:58] Applying RenameClasses to PxPayMessage.php...
[2020-06-26 10:50:58] Applying ClassToTraitRule to PxPayMessage.php...
[2020-06-26 10:50:58] Applying RenameClasses to PxPayResponse.php...
[2020-06-26 10:50:58] Applying ClassToTraitRule to PxPayResponse.php...
[2020-06-26 10:50:58] Applying RenameClasses to MifMessage.php...
[2020-06-26 10:50:58] Applying ClassToTraitRule to MifMessage.php...
[2020-06-26 10:50:58] Applying RenameClasses to PxPayRequest.php...
[2020-06-26 10:50:58] Applying ClassToTraitRule to PxPayRequest.php...
[2020-06-26 10:50:58] Applying RenameClasses to PxPayCurl.php...
[2020-06-26 10:50:58] Applying ClassToTraitRule to PxPayCurl.php...
[2020-06-26 10:50:58] Applying RenameClasses to PxPay_Sample_Curl.php...
[2020-06-26 10:50:58] Applying ClassToTraitRule to PxPay_Sample_Curl.php...
[2020-06-26 10:50:58] Applying RenameClasses to PxPayCurl.inc.php...
[2020-06-26 10:50:58] Applying ClassToTraitRule to PxPayCurl.inc.php...
[2020-06-26 10:50:58] Applying RenameClasses to DpsPxPayStoredPayment.php...
[2020-06-26 10:50:58] Applying ClassToTraitRule to DpsPxPayStoredPayment.php...
[2020-06-26 10:50:58] Applying RenameClasses to DpsPxPost.php...
[2020-06-26 10:50:58] Applying ClassToTraitRule to DpsPxPost.php...
[2020-06-26 10:50:58] Applying RenameClasses to DpsPxPayComs.php...
[2020-06-26 10:50:58] Applying ClassToTraitRule to DpsPxPayComs.php...
[2020-06-26 10:50:58] Applying RenameClasses to DpsPxPayPayment.php...
[2020-06-26 10:50:58] Applying ClassToTraitRule to DpsPxPayPayment.php...
[2020-06-26 10:50:58] Applying RenameClasses to DpsPxPayStoredCard.php...
[2020-06-26 10:50:58] Applying ClassToTraitRule to DpsPxPayStoredCard.php...
[2020-06-26 10:50:58] Applying RenameClasses to _config.php...
[2020-06-26 10:50:58] Applying ClassToTraitRule to _config.php...
modified:	tests/PaymentDpsTest.php
@@ -1,4 +1,6 @@
 <?php
+
+use SilverStripe\Dev\SapphireTest;

 class PaymentDpsTest extends SapphireTest
 {

modified:	_config/config.yml
@@ -1,5 +1,5 @@
-DpsPxPayPayment_Handler:
-    url_segment: dpspxpaypayment
-DpsPxPayStoredPayment_Handler:
-    url_segment: dpspxpaystoredpayment
+Sunnysideup\PaymentDps\Control\DpsPxPayPayment_Handler:
+  url_segment: dpspxpaypayment
+Sunnysideup\PaymentDps\Control\DpsPxPayStoredPayment_Handler:
+  url_segment: dpspxpaystoredpayment


modified:	_config/routes.yml
@@ -4,5 +4,6 @@
 ---
 SilverStripe\Control\Director:
   rules:
-    'dpspxpaypayment//$Action/$ID' : 'DpsPxPayPayment_Handler'
-    'dpspxpaystoredpayment//$Action/$ID' : 'DpsPxPayStoredPayment_Handler'
+    dpspxpaypayment//$Action/$ID: Sunnysideup\PaymentDps\Control\DpsPxPayPayment_Handler
+    dpspxpaystoredpayment//$Action/$ID: Sunnysideup\PaymentDps\Control\DpsPxPayStoredPayment_Handler
+

modified:	src/Control/DpsPxPayment_Handler.php
@@ -2,12 +2,20 @@

 namespace Sunnysideup\PaymentDps\Control;

-use Controller;
-use Config;
-use Director;
-use EcommercePayment;
-use DpsPxPayComs;
-use DpsPxPayPayment;
+
+
+
+
+
+
+use SilverStripe\Core\Config\Config;
+use Sunnysideup\PaymentDps\Control\DpsPxPayPayment_Handler;
+use SilverStripe\Control\Director;
+use Sunnysideup\Ecommerce\Model\Money\EcommercePayment;
+use Sunnysideup\PaymentDps\DpsPxPayComs;
+use Sunnysideup\PaymentDps\DpsPxPayPayment;
+use SilverStripe\Control\Controller;
+



@@ -23,7 +31,7 @@

     public static function complete_link()
     {
-        return Config::inst()->get('DpsPxPayPayment_Handler', 'url_segment') . '/paid/';
+        return Config::inst()->get(DpsPxPayPayment_Handler::class, 'url_segment') . '/paid/';
     }

     public static function absolute_complete_link()

modified:	src/Control/DpsPxPayPayment_Handler.php
@@ -2,12 +2,20 @@

 namespace Sunnysideup\PaymentDps\Control;

-use Controller;
-use Config;
-use Director;
-use EcommercePayment;
-use DpsPxPayComs;
-use DpsPxPayPayment;
+
+
+
+
+
+
+use SilverStripe\Core\Config\Config;
+use Sunnysideup\PaymentDps\Control\DpsPxPayPayment_Handler;
+use SilverStripe\Control\Director;
+use Sunnysideup\Ecommerce\Model\Money\EcommercePayment;
+use Sunnysideup\PaymentDps\DpsPxPayComs;
+use Sunnysideup\PaymentDps\DpsPxPayPayment;
+use SilverStripe\Control\Controller;
+



@@ -23,7 +31,7 @@

     public static function complete_link()
     {
-        return Config::inst()->get('DpsPxPayPayment_Handler', 'url_segment') . '/paid/';
+        return Config::inst()->get(DpsPxPayPayment_Handler::class, 'url_segment') . '/paid/';
     }

     public static function absolute_complete_link()

modified:	src/Control/DpsPxPayStoredPayment_Handler.php
@@ -2,11 +2,18 @@

 namespace Sunnysideup\PaymentDps\Control;

-use Config;
-use Director;
-use DpsPxPayComs;
-use DpsPxPayStoredPayment;
-use DpsPxPayStoredCard;
+
+
+
+
+
+use SilverStripe\Core\Config\Config;
+use Sunnysideup\PaymentDps\Control\DpsPxPayStoredPayment_Handler;
+use SilverStripe\Control\Director;
+use Sunnysideup\PaymentDps\DpsPxPayComs;
+use Sunnysideup\PaymentDps\DpsPxPayStoredPayment;
+use Sunnysideup\PaymentDps\Model\DpsPxPayStoredCard;
+


 class DpsPxPayStoredPayment_Handler extends DpsPxPayPayment_Handler
@@ -16,7 +23,7 @@

     public static function complete_link()
     {
-        return Config::inst()->get('DpsPxPayStoredPayment_Handler', 'url_segment') . '/paid/';
+        return Config::inst()->get(DpsPxPayStoredPayment_Handler::class, 'url_segment') . '/paid/';
     }

     public static function absolute_complete_link()

modified:	src/DpsPxPayStoredPayment.php
@@ -2,17 +2,30 @@

 namespace Sunnysideup\PaymentDps;

-use FieldList;
-use Member;
-use DpsPxPayStoredCard;
-use DropdownField;
-use LiteralField;
-use Config;
-use Requirements;
-use DB;
-use EcommercePaymentSuccess;
-use EcommercePaymentFailure;
-use DpsPxPayStoredPayment_Handler;
+
+
+
+
+
+
+
+
+
+
+
+use SilverStripe\Forms\FieldList;
+use SilverStripe\Security\Member;
+use Sunnysideup\PaymentDps\Model\DpsPxPayStoredCard;
+use SilverStripe\Forms\DropdownField;
+use SilverStripe\Core\Config\Config;
+use Sunnysideup\PaymentDps\DpsPxPayStoredPayment;
+use SilverStripe\Forms\LiteralField;
+use SilverStripe\View\Requirements;
+use SilverStripe\ORM\DB;
+use Sunnysideup\Ecommerce\Money\Payment\PaymentResults\EcommercePaymentSuccess;
+use Sunnysideup\Ecommerce\Money\Payment\PaymentResults\EcommercePaymentFailure;
+use Sunnysideup\PaymentDps\Control\DpsPxPayStoredPayment_Handler;
+


 /**
@@ -63,7 +76,7 @@
             $fields->push(new DropdownField('DPSUseStoredCard', 'Use a stored card?', $cardsDropdown, $value = $card->BillingID, $form = null, $emptyString = "--- use new Credit Card ---"));
         } else {
             $fields->push(new DropdownField('DPSStoreCard', '', array(1 => 'Store Credit Card', 0 => 'Do NOT Store Credit Card')));
-            $fields->push(new LiteralField("AddCardExplanation", "<p>".Config::inst()->get('DpsPxPayStoredPayment', 'add_card_explanation')."</p>"));
+            $fields->push(new LiteralField("AddCardExplanation", "<p>".Config::inst()->get(DpsPxPayStoredPayment::class, 'add_card_explanation')."</p>"));
         }
         $fields->push(new LiteralField('DPSInfo', $privacyLink));
         $fields->push(new LiteralField('DPSPaymentsList', $paymentsList));

modified:	src/DpsPxPost.php
@@ -2,12 +2,18 @@

 namespace Sunnysideup\PaymentDps;

-use EcommercePayment;
-use LiteralField;
-use Convert;
+
+
+
 use SimpleXMLElement;
-use EcommercePaymentSuccess;
-use EcommercePaymentFailure;
+
+
+use SilverStripe\Forms\LiteralField;
+use SilverStripe\Core\Convert;
+use Sunnysideup\Ecommerce\Money\Payment\PaymentResults\EcommercePaymentSuccess;
+use Sunnysideup\Ecommerce\Money\Payment\PaymentResults\EcommercePaymentFailure;
+use Sunnysideup\Ecommerce\Model\Money\EcommercePayment;
+


 /**

modified:	src/DpsPxPayComs.php
@@ -2,40 +2,49 @@

 namespace Sunnysideup\PaymentDps;

-use Config;
-use Director;
-use PxPayCurl;
-use PxPayRequest;
-use MifMessage;
+
+
+
+
+
 use debug;
+use SilverStripe\Core\Config\Config;
+use Sunnysideup\PaymentDps\DpsPxPayComs;
+use SilverStripe\Control\Director;
+use Sunnysideup\PaymentDps\Thirdparty\PxPayCurl;
+use Sunnysideup\PaymentDps\Thirdparty\PxPayRequest;
+use Sunnysideup\PaymentDps\Thirdparty\MifMessage;
+

 /**
  *@author nicolaas [at] sunnysideup.co.nz
  **/

-class DpsPxPayComs extends Object
+use SilverStripe\Core\Extensible;
+use SilverStripe\Core\Injector\Injectable;
+use SilverStripe\Core\Config\Configurable;
+/**
+ *@author nicolaas [at] sunnysideup.co.nz
+ **/
+class DpsPxPayComs
 {
+    use Extensible;
+    use Injectable;
+    use Configurable;
     private static $pxpay_url = 'https://sec.paymentexpress.com/pxaccess/pxpay.aspx';
-
     private static $pxpay_userid = "";
-
-    private static $pxpay_encryption_key =  "";
-
-    private static $alternative_thirdparty_folder =  "";
-
-    private static $overriding_txn_type =  ""; //e.g. AUTH
-
+    private static $pxpay_encryption_key = "";
+    private static $alternative_thirdparty_folder = "";
+    private static $overriding_txn_type = "";
+    //e.g. AUTH
     public static function get_txn_type()
     {
-        $overridingTxnType = Config::inst()->get("DpsPxPayComs", "overriding_txn_type");
-        return $overridingTxnType  ? $overridingTxnType : "Purchase";
-    }
-
-
-    /**
-    * customer details
-    **/
-
+        $overridingTxnType = Config::inst()->get(DpsPxPayComs::class, "overriding_txn_type");
+        return $overridingTxnType ? $overridingTxnType : "Purchase";
+    }
+    /**
+     * customer details
+     **/
     protected $TxnData1 = "";
     public function setTxnData1($v)
     {
@@ -56,43 +65,37 @@
     {
         $this->EmailAddress = $v;
     }
-
-    /**
-    * order details
-    **/
+    /**
+     * order details
+     **/
     protected $AmountInput = 0;
     public function setAmountInput($v)
     {
         $this->AmountInput = $v;
     }
-
     protected $MerchantReference = "";
     public function setMerchantReference($v)
     {
         $this->MerchantReference = $v;
     }
-
     protected $CurrencyInput = "NZD";
     public function setCurrencyInput($v)
     {
         $this->CurrencyInput = $v;
     }
-
     protected $TxnType = "Purchase";
     public function setTxnType($v)
     {
         $this->TxnType = $v;
     }
-
     protected $TxnId = "";
     public function setTxnId($v)
     {
         $this->TxnId = $v;
     }
-
-    /**
-    * details of the redirection
-    **/
+    /**
+     * details of the redirection
+     **/
     protected $UrlFail = "";
     public function setUrlFail($v)
     {
@@ -103,7 +106,6 @@
     {
         $this->UrlSuccess = $v;
     }
-
     /**
      * Details to use stored cards
      */
@@ -117,36 +119,28 @@
     {
         $this->BillingId = $v;
     }
-
-    /**
-    * external object
-    **/
+    /**
+     * external object
+     **/
     protected $PxPayObject = null;
     protected $response = null;
-
     public function __construct()
     {
         if (!self::$alternative_thirdparty_folder) {
-            self::$alternative_thirdparty_folder = Director::baseFolder().'/payment_dps/code/thirdparty';
-        }
-        require_once(self::$alternative_thirdparty_folder."/PxPayCurl.inc.php");
-        if (!Config::inst()->get("DpsPxPayComs", "pxpay_url")) {
-            user_error("error in DpsPxPayComs::__construct, self::$pxpay_url not set. ", E_USER_WARNING);
-        }
-        if (!Config::inst()->get("DpsPxPayComs", "pxpay_userid")) {
-            user_error("error in DpsPxPayComs::__construct, self::$pxpay_userid not set. ", E_USER_WARNING);
-        }
-        if (!Config::inst()->get("DpsPxPayComs", "pxpay_encryption_key")) {
-            user_error("error in DpsPxPayComs::__construct, self::$pxpay_encryption_key not set. ", E_USER_WARNING);
-        }
-
-        $this->PxPayObject = new PxPayCurl(
-            Config::inst()->get("DpsPxPayComs", "pxpay_url"),
-            Config::inst()->get("DpsPxPayComs", "pxpay_userid"),
-            Config::inst()->get("DpsPxPayComs", "pxpay_encryption_key")
-        );
-    }
-
+            self::$alternative_thirdparty_folder = Director::baseFolder() . '/payment_dps/code/thirdparty';
+        }
+        require_once self::$alternative_thirdparty_folder . "/PxPayCurl.inc.php";
+        if (!Config::inst()->get(DpsPxPayComs::class, "pxpay_url")) {
+            user_error("error in DpsPxPayComs::__construct, self::{$pxpay_url} not set. ", E_USER_WARNING);
+        }
+        if (!Config::inst()->get(DpsPxPayComs::class, "pxpay_userid")) {
+            user_error("error in DpsPxPayComs::__construct, self::{$pxpay_userid} not set. ", E_USER_WARNING);
+        }
+        if (!Config::inst()->get(DpsPxPayComs::class, "pxpay_encryption_key")) {
+            user_error("error in DpsPxPayComs::__construct, self::{$pxpay_encryption_key} not set. ", E_USER_WARNING);
+        }
+        $this->PxPayObject = new PxPayCurl(Config::inst()->get(DpsPxPayComs::class, "pxpay_url"), Config::inst()->get(DpsPxPayComs::class, "pxpay_userid"), Config::inst()->get(DpsPxPayComs::class, "pxpay_encryption_key"));
+    }
     /*
      * This function formats data into a request and returns redirection URL
      * NOTE: you will need to set all the variables prior to running this.
@@ -158,7 +152,6 @@
             $this->TxnId = uniqid("ID");
         }
         $request = new PxPayRequest();
-
         #Set PxPay properties
         if ($this->MerchantReference) {
             $request->setMerchantReference($this->MerchantReference);
@@ -211,27 +204,21 @@
         if ($this->BillingId) {
             $request->setBillingId($this->BillingId);
         }
-
         /* TODO:
-        $request->setEnableAddBillCard($EnableAddBillCard);
-        $request->setBillingId($BillingId);
-        $request->setOpt($Opt);
-        */
-
+           $request->setEnableAddBillCard($EnableAddBillCard);
+           $request->setBillingId($BillingId);
+           $request->setOpt($Opt);
+           */
         #Call makeRequest function to obtain input XML
         $request_string = $this->PxPayObject->makeRequest($request);
-
         #Obtain output XML
         $this->response = new MifMessage($request_string);
         #Parse output XML
         $url = $this->response->get_element_text("URI");
         //$valid = $this->response->get_attribute("valid");
-
         #Redirect to payment page
         return $url;
     }
-
-
     /*
      * This function receives information back from the payments page as a response object
      * --------------------- RESPONSE DATA ---------------------
@@ -260,14 +247,12 @@
      *
      * also see: https://www.paymentexpress.com/technical_resources/ecommerce_hosted/error_codes.html
      **/
-
     public function processRequestAndReturnResultsAsObject()
     {
         #getResponse method in PxPay object returns PxPayResponse object
         #which encapsulates all the response data
         return $this->PxPayObject->getResponse($_REQUEST["result"]);
     }
-
     public function getDebugMessage()
     {
         $string = "<pre>";
@@ -276,7 +261,6 @@
         $string .= "</pre>";
         return $string;
     }
-
     public function debug()
     {
         debug::show("debugging DpsPxPayComs");

modified:	src/DpsPxPayPayment.php
@@ -2,18 +2,31 @@

 namespace Sunnysideup\PaymentDps;

-use EcommercePayment;
-use ReadonlyField;
-use FieldList;
-use LiteralField;
-use DpsPxPayPayment_Handler;
-use Email;
-use SiteTree;
-use ContentController;
-use Requirements;
-use EcommercePaymentProcessing;
-use EcommercePaymentFailure;
-use Convert;
+
+
+
+
+
+
+
+
+
+
+
+
+use SilverStripe\Forms\ReadonlyField;
+use SilverStripe\Forms\LiteralField;
+use SilverStripe\Forms\FieldList;
+use Sunnysideup\Ecommerce\Model\Money\EcommercePayment;
+use Sunnysideup\PaymentDps\Control\DpsPxPayPayment_Handler;
+use SilverStripe\Control\Email\Email;
+use SilverStripe\CMS\Model\SiteTree;
+use SilverStripe\CMS\Controllers\ContentController;
+use SilverStripe\View\Requirements;
+use Sunnysideup\Ecommerce\Money\Payment\PaymentResults\EcommercePaymentProcessing;
+use Sunnysideup\Ecommerce\Money\Payment\PaymentResults\EcommercePaymentFailure;
+use SilverStripe\Core\Convert;
+


 /**

modified:	src/Model/DpsPxPayStoredCard.php
@@ -2,8 +2,11 @@

 namespace Sunnysideup\PaymentDps\Model;

-use DataObject;
-use Member;
+
+
+use SilverStripe\Security\Member;
+use SilverStripe\ORM\DataObject;
+


 class DpsPxPayStoredCard extends DataObject
@@ -30,7 +33,7 @@
     );

     private static $has_one = array(
-        'Member' => 'Member'
+        'Member' => Member::class
     );

     private static $searchable_fields = array(

Writing changes for 11 files
✔✔✔