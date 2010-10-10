<?php

class DpsPxPayStoredCardModelAdmin extends ModelAdmin {

	public static $managed_models = array(
		'DpsPxPayStoredCard'
	);

	public static $url_segment = 'dps-cards';

	public static $menu_title = 'DPS Cards';

}