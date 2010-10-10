<?php

class DpsPxPayStoredCard extends DataObject {

	public static $db = array(
		'CardName' => 'Varchar',
		'CardHolder' => 'Varchar',
		'CardNumber' => 'Varchar',
		'BillingID' => 'Varchar'
	);

	public static $has_one = array(
		'Member' => 'Member'
	);

	public static $searchable_fields = array(
		'CardHolder' => 'PartialMatchFilter',
		'CardNumber' => 'PartialMatchFilter'
	);

	//database related settings
	public static $field_labels = array(
		'CardName' => 'Card Name',
		'CardHolder' => 'Card Holder',
		'CardNumber' => 'Card Number',
		'MemberID' => 'Card Owner'
	);
	public static $summary_fields = array(
		'CardName' => 'Card Name',
		'CardHolder' => 'Card Holder',
		'CardNumber' => 'Card Number',
		'Member.Title' => 'Card Owner'
	);

	public static $singular_name = "DPS PX Pay Stored Card";

	public static $plural_name = "DPS PX Pay Stored Cards";

	public static $default_sort = "Created DESC";

	public static $defaults = array();//use fieldName => Default Value

	public function populateDefaults() {
		parent::populateDefaults();
	}

	static $can_create = false;
	public function canCreate() {return false;}
	public function canView($member = null) {if(!$member) {$member = Member::CurrentMember();} if($member) {return $member->IsAdmin();}}
	public function canEdit() {return false;}
	public function canDelete($member = null) {return $this->canView($member = null);}


}
