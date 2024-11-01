<?php

if ( !defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

new WC_CPW_Currency_LTC( 'LTC', 'LTC', 'Litecoin', 8, 'litecoin_logo_small.png', 60, 'Ł', true, true, true, '', [ '/^[LM3][a-km-zA-HJ-NP-Z1-9]{26,33}|l[a-z0-9]{8,87}/' ], 4 );

class WC_CPW_Currency_LTC extends WC_CPW_Currency {

	public static function is_dirty_address( $address ) {
		$primaryResult = self::get_chainso_total_received_for_ltc_address( $address );

		if ( $primaryResult[ 'result' ] === 'success' ) {
			// if we get a non zero balance from first source then address is dirty
			if ( $primaryResult[ 'total_received' ] >= 0.00000001 ) {
				return true;
			} else {
				$secondaryResult = self::get_blockcypher_total_received_for_ltc_address( $address, 0 );

				// we have a primary resource saying address is clean and backup source failed, so return clean
				if ( $secondaryResult[ 'result' ] === 'error' ) {
					return false;
				}
				// backup source gave us data
				else {
					// primary source is clean but if we see a balance we return dirty
					if ( $secondaryResult[ 'total_received' ] >= 0.00000001 ) {
						return true;
					}
					// both sources return clean
					else {
						return false;
					}
				}
			}
		} else {
			$secondaryResult = self::get_blockcypher_total_received_for_ltc_address( $address, 0 );

			if ( $secondaryResult[ 'result' ] === 'success' ) {
				return $secondaryResult[ 'total_received' ] >= 0.00000001;
			}
		}

		throw new \Exception( "Unable to get LTC address total amount received to verify is address is unused." );
	}

	public function get_address_transactions( $address ) {
		$userAgentString = self::get_user_agent_string();

		$request = 'https://api.blockcypher.com/v1/ltc/main/addrs/' . $address;

		$args = array(
			'user-agent' => $userAgentString
		);

		$response = wp_remote_get( $request, $args );

		if ( is_wp_error( $response ) || $response[ 'response' ][ 'code' ] !== 200 ) {
			$result = array(
				'result'		 => 'error',
				'total_received' => '',
			);

			return $result;
		}

		$body = json_decode( $response[ 'body' ] );

		$rawTransactions = $body->txrefs;
		if ( !is_array( $rawTransactions ) ) {
			$result = array(
				'result'	 => 'error',
				'message'	 => 'No transactions found',
			);

			return $result;
		}
		$transactions = array();
		foreach ( $rawTransactions as $rawTransaction ) {
			if ( $rawTransaction->tx_input_n == -1 ) {
				$transactions[] = new WC_CPW_Transaction(
				$rawTransaction->value,
	$rawTransaction->confirmations,
	$rawTransaction->confirmed,
	$rawTransaction->tx_hash );
			}
		}
		$result = array(
			'result'		 => 'success',
			'transactions'	 => $transactions,
		);

		return $result;
	}

	function get_total_received_for_address( $address, $requiredConfirmations ) {
		$primaryResult = self::get_blockcypher_total_received_for_ltc_address( $address, $requiredConfirmations );

		if ( $primaryResult[ 'result' ] === 'success' ) {
			return $primaryResult[ 'total_received' ];
		}

		$secondaryResult = self::get_chainso_total_received_for_ltc_address( $address );

		if ( $secondaryResult[ 'result' ] === 'success' ) {
			return $secondaryResult[ 'total_received' ];
		}

		throw new \Exception( "Unable to get LTC HD address information from external sources." );
	}

	public static function get_blockcypher_total_received_for_ltc_address( $address, $requiredConfirmations ) {
		$userAgentString = self::get_user_agent_string();

		$request = 'https://api.blockcypher.com/v1/ltc/main/addrs/' . $address . '?confirmations=' . $requiredConfirmations;

		$args = array(
			'user-agent' => $userAgentString
		);

		$response = wp_remote_get( $request, $args );
		if ( is_wp_error( $response ) || $response[ 'response' ][ 'code' ] !== 200 ) {
			WC_CPW_Logger::info( __FILE__, __LINE__, 'FAILED API CALL ( ' . $request . ' ): ' . print_r( $response, true ) );
			$result = array(
				'result'		 => 'error',
				'total_received' => '',
			);

			return $result;
		}

		$totalReceivedMmltc	 = json_decode( $response[ 'body' ] )->total_received;
		$totalReceived		 = $totalReceivedMmltc / 100000000;

		$result = array(
			'result'		 => 'success',
			'total_received' => $totalReceived,
		);

		return $result;
	}

	public static function get_chainso_total_received_for_ltc_address( $address ) {
		$userAgentString = self::get_user_agent_string();

		$request = 'https://chain.so/api/v2/get_address_received/LTC/' . $address;

		$args = array(
			'user-agent' => $userAgentString
		);

		$response = wp_remote_get( $request, $args );
		if ( is_wp_error( $response ) || $response[ 'response' ][ 'code' ] !== 200 ) {
			WC_CPW_Logger::info( __FILE__, __LINE__, 'FAILED API CALL ( ' . $request . ' ): ' . print_r( $response, true ) );

			$result = array(
				'result'		 => 'error',
				'total_received' => '',
			);

			return $result;
		}

		$totalReceived = (float) json_decode( $response[ 'body' ] )->data->confirmed_received_value;

		$result = array(
			'result'		 => 'success',
			'total_received' => $totalReceived,
		);

		return $result;
	}

	public function is_valid_mpk() {
		return $this->is_valid_xpub() || $this->is_valid_ypub() || $this->is_valid_zpub();
	}

}
