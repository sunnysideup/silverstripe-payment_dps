#******************************************************************************
#* Name          	: README_PxPay_PHP_CURL.txt
#* Description   	: Direct Payment Solutions Payment Express PxPay PHP cURL Sample
#* Copyright	 	: Direct Payment Solutions 2009(c)
#* Date          	: 2009-04-10
#* References    	: http://www.paymentexpress.com/technical_resources/ecommerce_hosted/pxpay.html
#*@version 		: 1.0
#* Author 		: Thomas Treadwell
#******************************************************************************


Development Spec
================
Windows Xp with Service pack 2
PHP Version 5.0.2
Microsoft-IIS/5.1

Different specifications and configurations may encounter issues that need to be addressed.

OVERVIEW
========

This PHP script is a sample, intended to demonstrate how a merchant can use the DPS Secure Payment Page from a PHP website.

The Secure Payment Page is useful for sites that do not wish to purchase a digital certificate for their site or host the payment page on their site which reduces the cost to the merchant.

These sites can obtain order details via ordinary form requests, and
then obtain payment authorization via the DPS Secure Payments Page.

There are two files included in the release:
	1. pxpay.inc.php 
	   -- PHP include file which contains classes for Payments Page
	2. PxPay_Sample_Curl.php
	   -- For clients with the cURL PHP extension on their host
	   -- Sample code to post payment and process response using PxPay_Curl,
	      PxPayReQuest, PxPayResponse objects
	

There are two basic steps in using PxPay:

  1- Sending the transaction request to the Secure Payments Page.
  2- Handling the response that is sent back.

Sending the transaction request
===============================

To generate a request, follow these steps:
  1- Insert your PxPay_Key, PxPay_Userid and PxPay_URl into the PxPay sample code.
  2- Set up an PxpayRequest object by giving transaction details.
  3- use PXPay.makeRequest function to create an ASCII hex code encrypted MifMessage
  4- create a MifMessage from the response and extract the URL
  5- Redirect the client


These steps are shown in the redirect_form method in the sample.

Handling the response
=====================

  1- Get the ASCII hex representation of the result.
  2- use PxPay.getResponse method to get the PxPayResponse object with unencrypt response XML fields data.
  3- Create a result page for the client using pxPayResponse object.

These steps are shown in the print_result method in the sample.

PREREQUISITES
=============

   -  cURL extension

INSTALLATION
============

   1. Install PxPay_Sample_Curl.php and PxPay_Curl.inc.php wherever you put your PHP scripts.
   2. Set your PxPay UserId and PxPay Key in the PxPay_Sample_Curl.php file into the variables 
      $PxPay_Userid and $PxPay_Key

DISCLAIMER
============
Software downloaded from the Direct Payment Solutions web site is provided 'as is' without warranty of any kind,
either express or implied, including, but not limited to; the implied warranties of fitness for a purpose,
or the warranty of non-infringement.

Without limiting the foregoing, the Direct Payment Solutions makes no warranty that:

- The software will meet your requirements.

- The software will be uninterrupted, timely, secure or error-free.

- The quality of the software will meet your expectations.


Software and its documentation made available on the Direct Payment Solutions web site could include technical or other mistakes,
inaccuracies or typographical errors. Direct Payment Solutions may make changes at any time to the software or documentation made
available on its web site.


Direct Payment Solutions assumes no responsibility for errors or omissions in the software or documentation available from its web site.