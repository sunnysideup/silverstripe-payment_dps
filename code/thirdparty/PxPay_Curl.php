<?php

class PxPay_Curl
{
    public $PxPay_Key;
    public $PxPay_Url;
    public $PxPay_Userid;
    public function __construct($Url, $UserId, $Key)
    {
        error_reporting(E_ERROR);
        $this->PxPay_Key = $Key;
        $this->PxPay_Url = $Url;
        $this->PxPay_Userid = $UserId;
    }
    
    #******************************************************************************
    # Create a request for the PxPay interface
    #******************************************************************************
    public function makeRequest($request)
    {
        #Validate the Request
        if ($request->validData() == false) {
            return "" ;
        }

        $request->setUserId($this->PxPay_Userid);
        $request->setKey($this->PxPay_Key);
        
        $xml = $request->toXml();
        
        $result = $this->submitXml($xml);
  
        return $result;
    }
            
    #******************************************************************************
    # Return the transaction outcome details
    #******************************************************************************
    public function getResponse($result)
    {
        $inputXml = "<ProcessResponse><PxPayUserId>".$this->PxPay_Userid."</PxPayUserId><PxPayKey>".$this->PxPay_Key.
        "</PxPayKey><Response>".$result."</Response></ProcessResponse>";
        
        $outputXml = $this->submitXml($inputXml);
        
        $pxresp = new PxPayResponse($outputXml);
        return $pxresp;
    }
    
    #******************************************************************************
    # Actual submission of XML using cURL. Returns output XML
    #******************************************************************************
    public function submitXml($inputXml)
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $this->PxPay_Url);
        
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $inputXml);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
        
        #set up proxy, this may change depending on ISP, please contact your ISP to get the correct cURL settings
        #curl_setopt($ch,CURLOPT_PROXY , "proxy:8080");
        #curl_setopt($ch,CURLOPT_PROXYUSERPWD,"username:password");

        $outputXml = curl_exec($ch);
            
        curl_close($ch);
  
        return $outputXml;
    }
}
