<?php

if ( !defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}


WC_CPW_Currencies::init();

class WC_CPW_Currencies {

	static $currencies		 = array();
	static $currency_labels	 = array();

	static function init() {
		include WC_CPW_PLUGIN_PATH . "classes/currencies/abstract-class-wc-cpw-currency.php";
		foreach ( glob( WC_CPW_PLUGIN_PATH . "classes/currencies/class-*.php" ) as $currency_class_file )
			include $currency_class_file;

		self::$currencies = apply_filters( "cpw_get_currencies", array() );

		//re-order currencies
		uasort(self::$currencies, function($a,$b){
			if( $a->get_display_order() == $b->get_display_order() )
				return strcmp($a->get_name(), $b->get_name());
			else
				return $a->get_display_order() - $b->get_display_order(); // works like strcmp :)
		});

		self::$currency_labels			 = array();
		foreach ( self::$currencies as $key => $currency )
			self::$currency_labels[ $key ]	 = $currency->get_name();
	}

	static function get_currencies() {
		return self::$currency_labels;
	}

	static function get_currency( $key ) {
		return self::$currencies[ $key ];
	}

	static function get() {
		return self::$currencies;
	}

}
