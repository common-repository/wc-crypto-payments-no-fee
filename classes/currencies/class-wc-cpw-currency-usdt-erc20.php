<?php

if ( !defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

new WC_CPW_Currency_USDT_ERC20( 'USDT_ERC20', 'USDT', 'USDT (ERC20)', 6, 'usdt_logo_small.png', 60, '', false, true, true, '0xa0b86991c6218b36c1d19d4a2e9eb0ce3606eb48', [ '/^0x[a-fA-F0-9]{40,42}/' ], 1 );

class WC_CPW_Currency_USDT_ERC20 extends WC_CPW_Currency {

}
