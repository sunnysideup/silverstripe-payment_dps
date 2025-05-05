<?php

namespace Sunnysideup\PaymentDps\Model;

use SilverStripe\ORM\DataObject;
use SilverStripe\Security\Member;
use SilverStripe\Security\Permission;
use SilverStripe\Security\Security;

/**
 * Class \Sunnysideup\PaymentDps\Model\DpsPxPayStoredCard
 *
 * @property string $CardName
 * @property string $CardHolder
 * @property string $CardNumber
 * @property string $BillingID
 * @property int $MemberID
 * @method \SilverStripe\Security\Member Member()
 */
class DpsPxPayStoredCard extends DataObject
{
    private static $table_name = 'DpsPxPayStoredCard';

    private static $db = [
        'CardName' => 'Varchar',
        'CardHolder' => 'Varchar',
        'CardNumber' => 'Varchar',
        'BillingID' => 'Varchar',
    ];

    private static $has_one = [
        'Member' => Member::class,
    ];

    private static $searchable_fields = [
        'CardHolder' => 'PartialMatchFilter',
        'CardNumber' => 'PartialMatchFilter',
    ];

    //database related settings
    private static $field_labels = [
        'CardName' => 'Card Name',
        'CardHolder' => 'Card Holder',
        'CardNumber' => 'Card Number',
        'MemberID' => 'Card Owner',
    ];

    private static $summary_fields = [
        'CardName' => 'Card Name',
        'CardHolder' => 'Card Holder',
        'CardNumber' => 'Card Number',
        'Member.Title' => 'Card Owner',
    ];

    private static $singular_name = 'DPS PX Pay Stored Card';

    private static $plural_name = 'DPS PX Pay Stored Cards';

    private static $default_sort = 'ID DESC';

    private static $defaults = []; //use fieldName => Default Value

    private static $can_create = false;

    public function canCreate($member = null, $context = [])
    {
        return false;
    }

    public function canView($member = null, $context = [])
    {
        if (! $member) {
            $member = Security::getCurrentUser();
        }
        $extended = $this->extendedCan(__FUNCTION__, $member);
        if (false === $extended) {
            return false;
        }
        if ($member) {
            return Permission::checkMember(
                $member->ID,
                'ADMIN',
                'any'
            );
        }

        return false;
    }

    public function canEdit($member = null, $context = [])
    {
        return false;
    }

    public function canDelete($member = null)
    {
        $extended = $this->extendedCan(__FUNCTION__, $member);
        if (null !== $extended) {
            return $extended;
        }

        return $this->canView($member = null, $context = []);
    }
}
