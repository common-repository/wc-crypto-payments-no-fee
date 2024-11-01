<?php

if ( !defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

abstract class WC_CPW_Currency {

	private $id;
	private $code; //used by APIs!
	private $name;
	private $roundPrecision;
	private $logoFilePath;
	private $updateInterval;
	private $symbol;
	private $hasHd;
	private $autopay;
	private $needsConfirmations;
	private $erc20contract;
	private $valid_masks;
	private $display_order;
	private $settings;

	public function __construct( $id, $code, $name, $roundPrecision, $logoFilePath, $updateInterval, $symbol, $hasHd, $autopay,
							  $needsConfirmations, $erc20contract, $valid_masks, $display_order = PHP_INT_MAX ) {
		$this->id					 = $id;
		$this->code					 = $code;
		$this->name					 = $name;
		$this->roundPrecision		 = $roundPrecision;
		$this->logoFilePath			 = $logoFilePath;
		$this->updateInterval		 = $updateInterval;
		$this->symbol				 = $symbol;
		$this->hasHd				 = $hasHd;
		$this->autopay				 = $autopay;
		$this->needsConfirmations	 = $needsConfirmations;
		$this->erc20contract		 = $erc20contract;
		$this->valid_masks			 = $valid_masks;
		$this->display_order		 = $display_order;

		add_filter( "cpw_get_currencies", function ( $currencies ) {
			$currencies[ $this->id ] = $this;
			return $currencies;
		} );

		$this->settings = get_option( 'cpw_' . $id . '_cryptocurrency_options', array() );
	}

	public function get_id() {
		return $this->id;
	}

	public function get_code() {
		return $this->code;
	}

	public function get_name() {
		return $this->name;
	}

	public function get_round_precision() {
		return $this->roundPrecision;
	}

	public function get_logo_file_path() {
		return WC_CPW_PLUGIN_URL . '/assets/img/' . $this->logoFilePath;
	}

	public function get_update_interval() {
		return $this->updateInterval;
	}

	public function get_symbol() {
		return $this->symbol;
	}

	public function get_display_order() {
		return $this->display_order;
	}

	public function has_hd() {
		return $this->hasHd;
	}

	public function has_autopay() {
		return $this->autopay;
	}

	public function needs_confirmations() {
		return $this->needsConfirmations;
	}

	public function is_erc20_token() {
		return strlen( $this->erc20contract ) > 0;
	}

	public function get_erc20_contract() {
		return $this->erc20contract;
	}

        public function get_valid_masks() {
            return $this->valid_masks;
        }

        public function is_valid_wallet_address( $address ) {
		if ( empty( $this->valid_masks ) ) {
			WC_CPW_Logger::info( __FILE__, __LINE__, 'Can not validate wallet, contact plug-in developer.' );
			throw new Exception( 'Can not validate wallet, contact plug-in developer.' );
		}
		foreach ( $this->valid_masks as $mask )
			if ( preg_match( $mask, $address ) )
				return true;
		return false;
	}

	public static function is_dirty_address( $address ) {

	}

	public function get_total_received_for_address( $address, $requiredConfirmations ) {

	}

	// non-erc20 currency MUST override this method!
	public function get_address_transactions( $address ) {
		if ( $this->is_erc20_token() )
			return $this->get_erc20_address_transactions( $address );
		$result = array(
			'result'		 => 'error',
			'total_received' => '',
		);
		return $result;
	}

	public function get_erc20_address_transactions( $address ) {
		$request = 'http://api.etherscan.io/api?module=account&action=tokentx&address=' . $address . '&startblock=0&endblock=999999999&sort=asc';

		$response = wp_remote_get( $request );
		if ( is_wp_error( $response ) || $response[ 'response' ][ 'code' ] !== 200 ) {
			WC_CPW_Logger::info( __FILE__, __LINE__, 'FAILED API CALL ( ' . $request . ' ): ' . print_r( $response, true ) );
			$result = array(
				'result'		 => 'error',
				'total_received' => '',
			);
			return $result;
		}

		$body			 = json_decode( $response[ 'body' ] );
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
			if ( strtolower( $rawTransaction->to ) === strtolower( $address ) && $rawTransaction->tokenSymbol === $this->id ) {
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

	static function get_user_agent_string() {
		return 'Mozilla/5.0 (Windows; U; Windows NT 6.1; en-US) AppleWebKit/534.12 (KHTML, like Gecko) Chrome/9.0.576.1 Safari/534.12';
	}

	public function basic_enabled() {
		return $this->get_mode() === '0';
	}

	public function autopay_enabled() {
		return $this->get_mode() === '1';
	}

	public function hd_enabled() {
		return $this->get_mode() === '2';
	}

	public function get_addresses() {
		$addressesKey = 'addresses';
		if ( is_array( $this->settings ) ) {
			if ( array_key_exists( $addressesKey, $this->settings ) ) {
				if ( is_array( $this->settings[ $addressesKey ] ) ) {
					return $this->settings[ $addressesKey ];
				}
			}
		}

		return [];
	}

	public function get_next_carousel_address() {
		if ( empty( $this->settings[ "addresses" ] ) ) {
			WC_CPW_Logger::info( __FILE__, __LINE__, 'Invalid current index! Something went wrong, please contact plug-in support.' );
			return ""; // or Exception ?
		}
		//rebuild possible missed indexes
		$this->settings[ "addresses" ] = array_values( $this->settings[ "addresses" ] );

		//should select
		$key		 = 'cpw_' . $this->get_id() . '_cryptocurrency_next_index';
		$next_index	 = get_option( $key, 0 );
		if ( $next_index >= count( $this->settings[ "addresses" ] ) )
			$next_index	 = 0;
		//
		$wallet		 = $this->settings[ "addresses" ][ $next_index++ ];
		//save
		update_option( $key, $next_index );
		return $wallet;
	}

	public function get_mpk() {
		$mpkKey = 'hd_mpk';
		if ( is_array( $this->settings ) ) {
			if ( array_key_exists( $mpkKey, $this->settings ) ) {
				return trim( $this->settings[ $mpkKey ] );
			}
		}

		return '';
	}

	public function get_hd_mode() {
		return apply_filters( 'cpw_hd_mode', '0', $this->get_id() );
	}

	public function get_markup() {
		$markupKey = 'markup';
		if ( is_array( $this->settings ) ) {
			if ( array_key_exists( $markupKey, $this->settings ) ) {
				return trim( $this->settings[ $markupKey ] );
			}
		}

		return '0.0';
	}

	public function get_hd_processing_percent() {
		$hdPercentKey = 'hd_percent_to_process';

		if ( is_array( $this->settings ) ) {
			if ( array_key_exists( $hdPercentKey, $this->settings ) ) {
				return $this->settings[ $hdPercentKey ];
			}
		}

		return '0.99';
	}

	public function get_hd_required_confirmations() {
		$hdConfirmationsKey = 'hd_required_confirmations';

		if ( is_array( $this->settings ) ) {
			if ( array_key_exists( $hdConfirmationsKey, $this->settings ) ) {
				return round( $this->settings[ $hdConfirmationsKey ] );
			}
		}

		return '2';
	}

	public function get_hd_cancellation_time() {
		$hdCancellationKey = 'hd_order_cancellation_time_hr';

		if ( is_array( $this->settings ) ) {
			if ( array_key_exists( $hdCancellationKey, $this->settings ) ) {
				return $this->settings[ $hdCancellationKey ];
			}
		}

		return '24';
	}

	public function get_hd_mpk_sample_addresses() {
		$hdMpkSampleAddresses = 'hd_mpk_sample_addresses';

		if ( is_array( $this->settings ) ) {
			if ( array_key_exists( $hdMpkSampleAddresses, $this->settings ) ) {
				return $this->settings[ $hdMpkSampleAddresses ];
			}
		}

		return array();
	}

	public function get_autopay_processing_percent() {
		$autopayPercentKey = 'autopayment_percent_to_process';

		if ( is_array( $this->settings ) ) {
			if ( array_key_exists( $autopayPercentKey, $this->settings ) ) {
				return $this->settings[ $autopayPercentKey ];
			}
		}

		return '0.999';
	}

	public function get_autopay_required_confirmations() {
		$autopayConfirmationsKey = 'autopayment_required_confirmations';

		if ( is_array( $this->settings ) ) {
			if ( array_key_exists( $autopayConfirmationsKey, $this->settings ) ) {
				return round( $this->settings[ $autopayConfirmationsKey ] );
			}
		}

		return '2';
	}

	public function get_autopay_cancellation_time() {
		$autopayCancellationKey = 'autopayment_order_cancellation_time_hr';

		if ( is_array( $this->settings ) ) {
			if ( array_key_exists( $autopayCancellationKey, $this->settings ) ) {
				return $this->settings[ $autopayCancellationKey ];
			}
		}

		return '24';
	}

	public function get_mode() {
		$modeKey = 'mode';

		if ( is_array( $this->settings ) ) {
			if ( array_key_exists( $modeKey, $this->settings ) ) {
				return $this->settings[ $modeKey ];
			}
		}

		return '';
	}

	public function get_settings() {
		return $this->settings;
	}

	public function save_settings( $settings ) {
		update_option( 'cpw_' . $this->get_id() . '_cryptocurrency_options', $settings );
		$this->settings = get_option( 'cpw_' . $this->get_id() . '_cryptocurrency_options', array() );
	}

	public function is_configured() {
		$modeEnabled = $this->basic_enabled() || $this->autopay_enabled() || $this->hd_enabled();
		$validHd	 = $this->has_hd();
		if ( $this->hd_enabled() && !$validHd ) {
			return false;
		}
		return $modeEnabled;
	}

	public function force_new_address() {

		$hdRepo = new WC_CPW_Hd_Repo( $this->get_id(), $this->get_mpk(), $this->get_hd_mode() );

		$startIndex = $hdRepo->get_next_index();

		$address = $this->create_hd_address( $startIndex );

		try {
			while ( $this->is_dirty_address( $address ) ) {

				$hdRepo->insert( $address, $startIndex, 'dirty' );
				$startIndex	 = $startIndex + 1;
				$address	 = $this->create_hd_address( $startIndex );
				set_time_limit( 30 );
			}
		} catch ( \Exception $e ) {
                        WC_CPW_Logger::info( __FILE__, __LINE__, 'Could not create new addresses: ' . $e->getMessage() );
			throw new \Exception( $e );
		}

		$hdRepo->insert( $address, $startIndex, 'ready' );
	}

	public function create_hd_address( $index ) {

		try {
			if ( !WC_CPW_Loader::p_enabled() ) {
				if ( $this->is_valid_xpub() ) {
					return HdHelper::mpk_to_bc_address( $this->get_id(), $this->get_mpk(), $index, 2, false );
				}
			} else {
				if ( $this->is_valid_mpk() ) {
					return apply_filters( 'cpw_get_hd_address', $this->get_id(), $this->get_mpk(), $index, $this->get_hd_mode() );
				}
			}
		} catch ( \Exception $e ) {
			throw new \Exception( 'Invalid MPK for ' . $this->get_id() . '. ' . $e->getTraceAsString() );
		}
	}

	public function is_valid_xpub() {
		$mpkStart	 = substr( $this->get_mpk(), 0, 5 );
		$validMpk	 = strlen( $this->get_mpk() ) == 111 && $mpkStart === 'xpub6';
		return $validMpk;
	}

	public function is_valid_ypub() {
		$mpkStart	 = substr( $this->get_mpk(), 0, 5 );
		$validMpk	 = strlen( $this->get_mpk() ) == 111 && $mpkStart === 'ypub6';
		return $validMpk;
	}

	public function is_valid_zpub() {
		$mpkStart	 = substr( $this->get_mpk(), 0, 5 );
		$validMpk	 = strlen( $this->get_mpk() ) == 111 && $mpkStart === 'zpub6';
		return $validMpk;
	}

	public function is_valid_mpk() {
		throw new \Exception();
	}

	public function get_price_string( $amount ) {

		// Round based on smallest unit of crypto
		$roundedAmount = round( $amount, $this->get_round_precision(), PHP_ROUND_HALF_UP );

		// Forces displaying the number in decimal format, with as many zeroes as possible to display the smallest unit of crypto
		$formattedAmount = number_format( $roundedAmount, $this->get_round_precision(), '.', '' );

		// We probably have extra 0's on the right side of the string so trim those
		$amountWithoutZeroes = rtrim( $formattedAmount, '0' );

		// If it came out to an round whole number we have a dot on the right side, so take that off
		$amountWithoutTrailingDecimal = rtrim( $amountWithoutZeroes, '.' );

		return $amountWithoutTrailingDecimal;
	}

	public function set_mpk( $mpk ) {
		$this->settings[ 'hd_mpk' ] = $mpk;
	}

	public function crypto_selected_and_valid() {
		$modeEnabled = $this->basic_enabled() || $this->autopay_enabled() || $this->hd_enabled();
		$validHd	 = $this->has_hd();
		if ( $this->hd_enabled() && !$validHd ) {
			return false;
		}
		return $modeEnabled;
	}

}
