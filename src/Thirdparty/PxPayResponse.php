<?php

namespace Sunnysideup\PaymentDps\Thirdparty;

//******************************************************************************
//* Name          : PxPayCurl.inc.php
//* Description   : Classes used interact with the PxPay interface using PHP with the cURL extension installed
//* Copyright     : Payment Express 2017(c)
//* Date          : 2017-04-10
//*@version          : 2.0
//* Author          : Payment Express DevSupport
//******************************************************************************
// Use this class to parse an XML document

//******************************************************************************
// Class for PxPay response messages.
//******************************************************************************

class PxPayResponse extends PxPayMessage
{
    public $Success;

    public $AuthCode;

    public $CardName;

    public $CardHolderName;

    public $CardNumber;

    public $DateExpiry;

    public $ClientInfo;

    public $DpsTxnRef;

    public $DpsBillingId;

    public $AmountSettlement;

    public $CurrencySettlement;

    public $TxnMac;

    public $ResponseText;

    public function __construct($xml)
    {
        $msg = new MifMessage($xml);
        parent::__construct();

        $this->Success = $msg->get_element_text('Success');
        $this->setTxnType($msg->get_element_text('TxnType'));
        $this->CurrencyInput = $msg->get_element_text('CurrencyInput');
        $this->setMerchantReference($msg->get_element_text('MerchantReference'));
        $this->setTxnData1($msg->get_element_text('TxnData1'));
        $this->setTxnData2($msg->get_element_text('TxnData2'));
        $this->setTxnData3($msg->get_element_text('TxnData3'));
        $this->AuthCode = $msg->get_element_text('AuthCode');
        $this->CardName = $msg->get_element_text('CardName');
        $this->CardHolderName = $msg->get_element_text('CardHolderName');
        $this->CardNumber = $msg->get_element_text('CardNumber');
        $this->DateExpiry = $msg->get_element_text('DateExpiry');
        $this->ClientInfo = $msg->get_element_text('ClientInfo');
        $this->TxnId = $msg->get_element_text('TxnId');
        $this->setEmailAddress($msg->get_element_text('EmailAddress'));
        $this->DpsTxnRef = $msg->get_element_text('DpsTxnRef');
        $this->BillingId = $msg->get_element_text('BillingId');
        $this->DpsBillingId = $msg->get_element_text('DpsBillingId');
        $this->AmountSettlement = $msg->get_element_text('AmountSettlement');
        $this->CurrencySettlement = $msg->get_element_text('CurrencySettlement');
        $this->TxnMac = $msg->get_element_text('TxnMac');
        $this->ResponseText = $msg->get_element_text('ResponseText');
    }

    public function getSuccess()
    {
        return $this->Success;
    }

    public function getAuthCode()
    {
        return $this->AuthCode;
    }

    public function getCardName()
    {
        return $this->CardName;
    }

    public function getCardHolderName()
    {
        return $this->CardHolderName;
    }

    public function getCardNumber()
    {
        return $this->CardNumber;
    }

    public function getDateExpiry()
    {
        return $this->DateExpiry;
    }

    public function getClientInfo()
    {
        return $this->ClientInfo;
    }

    public function getDpsTxnRef()
    {
        return $this->DpsTxnRef;
    }

    public function getDpsBillingId()
    {
        return $this->DpsBillingId;
    }

    public function getAmountSettlement()
    {
        return $this->AmountSettlement;
    }

    public function getCurrencySettlement()
    {
        $this->CurrencySettlement;
    }

    public function getTxnMac()
    {
        return $this->TxnMac;
    }

    public function getResponseText()
    {
        return $this->ResponseText;
    }
}
