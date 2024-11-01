<?php

if ( !defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

new WC_CPW_Currency_ETH( 'ETH', 'ETH', 'Ethereum', 18, 'ethereum_logo_small.png', 60, 'Ξ', false, true, true, '', [ '/0x[a-fA-F0-9]{40,42}/' ], 3 );

class WC_CPW_Currency_ETH extends WC_CPW_Currency {

	public function get_address_transactions( $address ) {

		$request = 'http://api.etherscan.io/api?module=account&action=txlist&address=' . $address . '&startblock=0&endblock=99999999&sort=desc';

		$response = wp_remote_get( $request );

		if ( is_wp_error( $response ) || $response[ 'response' ][ 'code' ] !== 200 ) {
			WC_CPW_Logger::info( __FILE__, __LINE__, 'FAILED API CALL ( ' . $request . ' ): ' . print_r( $response, true ) );

			$result = array(
				'result'		 => 'error',
				'total_received' => '',
			);

			return $result;
		}

		$body = json_decode( $response[ 'body' ] );

		$rawTransactions = $body->result;

		if ( !is_array( $rawTransactions ) ) {
			$result = array(
				'result'	 => 'error',
				'message'	 => 'No transactions found',
			);

			return $result;
		}

		$transactions = array();
		foreach ( $rawTransactions as $rawTransaction ) {

			if ( strtolower( $rawTransaction->to ) === strtolower( $address ) ) {

				$transactions[] = new WC_CPW_Transaction( $rawTransaction->value,
										   $rawTransaction->confirmations,
										   $rawTransaction->timeStamp,
										   $rawTransaction->hash );
			}
		}

		$result = array(
			'result'		 => 'success',
			'transactions'	 => $transactions,
		);

		return $result;
	}

}
