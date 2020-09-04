<?php

namespace Sunnysideup\PaymentDps\Thirdparty;

#******************************************************************************
#* Name          : PxPayCurl.inc.php
#* Description   : Classes used interact with the PxPay interface using PHP with the cURL extension installed
#* Copyright	 : Payment Express 2017(c)
#* Date          : 2017-04-10
#*@version 		 : 2.0
#* Author 		 : Payment Express DevSupport
#******************************************************************************
# Use this class to parse an XML document

#******************************************************************************
# Class for PxPay request messages.
#******************************************************************************

class PxPayRequest extends PxPayMessage
{
    public $UrlFail;

    public $UrlSuccess;

    public $AmountInput;

    public $EnableAddBillCard;

    public $PxPayUserId;

    public $PxPayKey;

    public $Opt;

    #Constructor
    public function __construct()
    {
        parent::__construct();
    }

    public function setEnableAddBillCard($EnableBillAddCard)
    {
        $this->EnableAddBillCard = $EnableBillAddCard;
    }

    public function setUrlFail($UrlFail)
    {
        $this->UrlFail = $UrlFail;
    }

    public function setUrlSuccess($UrlSuccess)
    {
        $this->UrlSuccess = $UrlSuccess;
    }

    public function setAmountInput($AmountInput)
    {
        $this->AmountInput = sprintf('%9.2f', $AmountInput);
    }

    public function setUserId($UserId)
    {
        $this->PxPayUserId = $UserId;
    }

    public function setKey($Key)
    {
        $this->PxPayKey = $Key;
    }

    public function setOpt($Opt)
    {
        $this->Opt = $Opt;
    }

    #******************************************************************
    #Data validation
    #******************************************************************
    public function validData()
    {
        $msg = '';
        if ($this->TxnType !== 'Purchase') {
            if ($this->TxnType !== 'Auth') {
                $msg = "Invalid TxnType[{$this->TxnType}]<br>";
            }
        }

        if (strlen($this->MerchantReference) > 64) {
            $msg = "Invalid MerchantReference [{$this->MerchantReference}]<br>";
        }

        if (strlen($this->TxnId) > 16) {
            $msg = "Invalid TxnId [{$this->TxnId}]<br>";
        }
        if (strlen($this->TxnData1) > 255) {
            $msg = "Invalid TxnData1 [{$this->TxnData1}]<br>";
        }
        if (strlen($this->TxnData2) > 255) {
            $msg = "Invalid TxnData2 [{$this->TxnData2}]<br>";
        }
        if (strlen($this->TxnData3) > 255) {
            $msg = "Invalid TxnData3 [{$this->TxnData3}]<br>";
        }

        if (strlen($this->EmailAddress) > 255) {
            $msg = "Invalid EmailAddress [{$this->EmailAddress}]<br>";
        }

        if (strlen($this->UrlFail) > 255) {
            $msg = "Invalid UrlFail [{$this->UrlFail}]<br>";
        }
        if (strlen($this->UrlSuccess) > 255) {
            $msg = "Invalid UrlSuccess [{$this->UrlSuccess}]<br>";
        }
        if (strlen($this->BillingId) > 32) {
            $msg = "Invalid BillingId [{$this->BillingId}]<br>";
        }

        if ($msg !== '') {
            trigger_error($msg, E_USER_ERROR);
            return false;
        }
        return true;
    }
}
