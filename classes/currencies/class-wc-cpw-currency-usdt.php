<?php

if ( !defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

new WC_CPW_Currency_USDT( 'USDT', 'USDT', 'USDT (TRC20 - Tron)', 6, 'usdt_logo_small.png', 60, '', false, true, true, '0xa0b86991c6218b36c1d19d4a2e9eb0ce3606eb48', [ '/^T[a-km-zA-HJ-NP-Z0-9]{26,42}/' ], 1);

class WC_CPW_Currency_USDT extends WC_CPW_Currency {

}
