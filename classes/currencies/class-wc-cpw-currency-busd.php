<?php

if ( !defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

new WC_CPW_Currency_BUSD( 'BUSD', 'BUSD', 'BUSD', 6, 'binanceusd_logo_small.png', 60, '', false, false, true, '', [ '/^bnb[a-zA-Z0-9]{37,48}/', '/^0x[a-fA-F0-9]{40,42}/' ], 5 );

class WC_CPW_Currency_BUSD extends WC_CPW_Currency {

	public function get_address_transactions( $address ) {
		return $this->get_erc20_address_transactions( $address );
	}

}
