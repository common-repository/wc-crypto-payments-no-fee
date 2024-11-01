<?php

if ( !defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

new WC_CPW_Currency_BTC( 'BTC', 'BTC', 'Bitcoin', 8, 'bitcoin_logo_small.png', 60, '₿', true, true, true, '', [ '/^[13][a-km-zA-HJ-NP-Z0-9]{24,42}|bc[a-z0-9]{8,87}/' ], 2 );

class WC_CPW_Currency_BTC extends WC_CPW_Currency {

	public static function is_dirty_address( $address ) {
		$primaryResult = self::get_blockchaininfo_total_received_for_btc_address( $address, 0 );
		if ( $primaryResult[ 'result' ] === 'success' ) {
			// if we get a non zero balance from first source then address is dirty
			if ( $primaryResult[ 'total_received' ] >= 0.00000001 ) {
				return true;
			} else {
				$secondaryResult = self::get_blockexplorer_total_received_for_btc_address( $address );

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
			$secondaryResult = self::get_blockexplorer_total_received_for_btc_address( $address );
			if ( $secondaryResult[ 'result' ] === 'success' ) {
				return $secondaryResult[ 'total_received' ] >= 0.00000001;
			}
		}

		$fallbackResult = self::get_chainso_total_received_for_btc_address( $address );
		if ( $fallbackResult[ 'result' ] === 'success' ) {
			return $fallbackResult[ 'total_received' ] >= 0.00000001;
		}
		throw new \Exception( "Unable to get BTC address total amount received to verify is address is unused." );
	}

	public function get_address_transactions( $address ) {
		$userAgentString = self::get_user_agent_string();

		$request = 'https://blockexplorer.com/api/txs/?address=' . $address;

		$args = array(
			'user-agent' => $userAgentString
		);

		$response = wp_remote_get( $request, $args );

		if ( is_wp_error( $response ) || $response[ 'response' ][ 'code' ] !== 200 ) {
			WC_CPW_Logger::info( __FILE__, __LINE__, 'FAILED API CALL ( ' . $request . ' ): ' . print_r( $response, true ) );
			$request2	 = 'https://api.blockcypher.com/v1/btc/main/addrs/' . $address;
			$response2	 = wp_remote_get( $request2, $args );
			if ( is_wp_error( $response2 ) || $response2[ 'response' ][ 'code' ] !== 200 ) {
				$result = array(
					'result'		 => 'error',
					'total_received' => '',
				);

				return $result;
			}

			$body = json_decode( $response2[ 'body' ] );

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

		$body = json_decode( $response[ 'body' ] );

		$rawTransactions = $body->txs;
		if ( !is_array( $rawTransactions ) ) {
			$result = array(
				'result'	 => 'error',
				'message'	 => 'No transactions found',
			);

			return $result;
		}
		$transactions = array();
		foreach ( $rawTransactions as $rawTransaction ) {
			foreach ( $rawTransaction->vout as $vout ) {
				if ( $vout->scriptPubKey->addresses[ 0 ] === $address ) {
					$transactions[] = new WC_CPW_Transaction( $vout->value * 100000000,
											$rawTransaction->confirmations,
											$rawTransaction->time,
											$rawTransaction->txid );
				}
			}
		}

		$result = array(
			'result'		 => 'success',
			'transactions'	 => $transactions,
		);

		return $result;
	}

	public function get_total_received_for_address( $address, $requiredConfirmations ) {

		$primaryResult = self::get_blockchaininfo_total_received_for_btc_address( $address, $requiredConfirmations );

		if ( $primaryResult[ 'result' ] === 'success' ) {
			return $primaryResult[ 'total_received' ];
		}

		$secondaryResult = self::get_blockexplorer_total_received_for_btc_address( $address );

		if ( $secondaryResult[ 'result' ] === 'success' ) {
			return $secondaryResult[ 'total_received' ];
		}

		$fallbackResult = self::get_chainso_total_received_for_btc_address( $address );

		if ( $fallbackResult[ 'result' ] === 'success' ) {
			return $fallbackResult[ 'total_received' ];
		}

		throw new \Exception( "Unable to get BTC HD address information from external sources." );
	}

	public static function get_blockchaininfo_total_received_for_btc_address( $address, $requiredConfirmations ) {
		$userAgentString = self::get_user_agent_string();
		$request		 = 'https://blockchain.info/q/getreceivedbyaddress/' . $address . '?confirmations=' . $requiredConfirmations;

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

		$totalReceivedSatoshi	 = (float) json_decode( $response[ 'body' ] );
		$result					 = array(
			'result'		 => 'success',
			'total_received' => $totalReceivedSatoshi / 100000000,
		);

		return $result;
	}

	public static function get_blockexplorer_total_received_for_btc_address( $address ) {
		$userAgentString = self::get_user_agent_string();

		// blockexplorer is DOWN
		//$request = 'https://blockexplorer.com/api/addr/' . $address . '/totalReceived';
		$request = 'https://api.blockchair.com/bitcoin/dashboards/address/' . $address;
		$args	 = array(
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

		$reply					 = json_decode( $response[ 'body' ] );
		$totalReceivedSatoshi	 = $reply->data->$address->address->received;

		$result = array(
			'result'		 => 'success',
			'total_received' => $totalReceivedSatoshi / 100000000,
		);

		return $result;
	}

	public static function get_chainso_total_received_for_btc_address( $address ) {
		$userAgentString = self::get_user_agent_string();

		$request = 'https://chain.so/api/v2/get_address_received/BTC/' . $address;

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

		$totalReceivedSatoshi = (float) json_decode( $response[ 'body' ] )->data->confirmed_received_value;

		$result = array(
			'result'		 => 'success',
			'total_received' => $totalReceivedSatoshi,
		);

		return $result;
	}

	public function is_valid_mpk() {
		return $this->is_valid_xpub() || $this->is_valid_ypub() || $this->is_valid_zpub();
	}

}
