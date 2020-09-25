<?php

namespace Sunnysideup\PaymentDps;

use SilverStripe\CMS\Controllers\ContentController;
use SilverStripe\CMS\Model\SiteTree;
use SilverStripe\Control\Email\Email;
use SilverStripe\Core\Manifest\ModuleResourceLoader;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\LiteralField;
use SilverStripe\Forms\ReadonlyField;
use SilverStripe\ORM\FieldType\DBField;
use SilverStripe\View\Requirements;
use Sunnysideup\Ecommerce\Forms\OrderForm;
use Sunnysideup\Ecommerce\Model\Money\EcommercePayment;
use Sunnysideup\Ecommerce\Money\Payment\PaymentResults\EcommercePaymentFailure;
use Sunnysideup\Ecommerce\Money\Payment\PaymentResults\EcommercePaymentProcessing;
use Sunnysideup\PaymentDps\Control\DpsPxPayPaymentHandler;

/**
 *@author nicolaas[at]sunnysideup.co.nz
 *@description: OrderNumber and PaymentID
 *
 *
 **/

class DpsPxPayPaymentRandomAmount extends DpsPxPayPayment
{

    private static $max_random_deduction = 1;

    private static $db = [
        'RandomDeduction' => 'Currency',
    ];

    protected function hasRandomDeduction() : bool
    {
        return true;
    }

    protected function setAndReturnRandomDeduction() : float
    {
        $max = $this->Config()->get('max_random_deduction');
        $amount = round($max * (mt_rand() / mt_getrandmax()), 2);
        $this->RandomDeduction = $amount;
        $this->write;

        return floatval($this->RandomDeduction);
    }
}
