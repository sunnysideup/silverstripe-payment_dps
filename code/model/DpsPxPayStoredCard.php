<?php

class DpsPxPayStoredCard extends DataObject {

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

	private static $defaults = array();//use fieldName => Default Value

	private static $can_create = false;
	public function canCreate($member = null) {return false;}
	public function canView($member = null) {if(!$member) {$member = Member::currentUser();} if($member) {return $member->IsAdmin();}}
	public function canEdit($member = null) {return false;}
	public function canDelete($member = null) {return $this->canView($member = null);}


}
