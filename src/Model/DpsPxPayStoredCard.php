<?php

namespace Sunnysideup\PaymentDps\Model;

use DataObject;
use Member;


class DpsPxPayStoredCard extends DataObject
{

/**
  * ### @@@@ START REPLACEMENT @@@@ ###
  * OLD: private static $db (case sensitive)
  * NEW: 
    private static $table_name = '[SEARCH_REPLACE_CLASS_NAME_GOES_HERE]';

    private static $db (COMPLEX)
  * EXP: Check that is class indeed extends DataObject and that it is not a data-extension!
  * ### @@@@ STOP REPLACEMENT @@@@ ###
  */
    
    private static $table_name = 'DpsPxPayStoredCard';

    private static $db = array(
        'CardName' => 'Varchar',
        'CardHolder' => 'Varchar',
        'CardNumber' => 'Varchar',
        'BillingID' => 'Varchar'
    );

    private static $has_one = array(
        'Member' => 'Member'
    );

    private static $searchable_fields = array(
        'CardHolder' => 'PartialMatchFilter',
        'CardNumber' => 'PartialMatchFilter'
    );

    //database related settings
    private static $field_labels = array(
        'CardName' => 'Card Name',
        'CardHolder' => 'Card Holder',
        'CardNumber' => 'Card Number',
        'MemberID' => 'Card Owner'
    );
    private static $summary_fields = array(
        'CardName' => 'Card Name',
        'CardHolder' => 'Card Holder',
        'CardNumber' => 'Card Number',
        'Member.Title' => 'Card Owner'
    );

    private static $singular_name = "DPS PX Pay Stored Card";

    private static $plural_name = "DPS PX Pay Stored Cards";

    private static $default_sort = "Created DESC";

    private static $defaults = [];//use fieldName => Default Value

    private static $can_create = false;

    public function canCreate($member = null, $context = [])
    {
        return false;
    }

    public function canView($member = null, $context = [])
    {
        if (!$member) {
            $member = Member::currentUser();
        }
        $extended = $this->extendedCan(__FUNCTION__, $member);
        if ($extended !== null) {
            return $extended;
        }
        if ($member) {
            return $member->IsAdmin();
        }
    }

    public function canEdit($member = null, $context = [])
    {
        return false;
    }

    public function canDelete($member = null, $context = [])
    {
        $extended = $this->extendedCan(__FUNCTION__, $member);
        if ($extended !== null) {
            return $extended;
        }
        return $this->canView($member = null, $context = []);
    }
}

