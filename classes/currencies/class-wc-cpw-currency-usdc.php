<?php

if ( !defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

new WC_CPW_Currency_USDC( 'USDC', 'USDC', 'USDC (ERC20)', 6, 'usdc_logo_small.png', 60, '', false, true, true, '0xa0b86991c6218b36c1d19d4a2e9eb0ce3606eb48', [ '/^0x[a-fA-F0-9]{40,42}/' ], 6 );

class WC_CPW_Currency_USDC extends WC_CPW_Currency {

}
