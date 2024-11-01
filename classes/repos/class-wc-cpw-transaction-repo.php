<?php

if ( !defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

class WC_CPW_Transaction_Repo {

	private $tableName;

	public function __construct() {
		$this->tableName = WC_CPW_Loader::$transaction_table;
	}

	public function add_consumed_tx( $txHash, $txAmount, $currency, $address ) {
		WC_CPW_Logger::info( __FILE__, __LINE__, 'inserting ' . $txHash . ' into db as ' . $address . ' with order amount of: ' . $txAmount );
		global $wpdb;
		$currentTime = time();
		$query		 = "INSERT INTO `$this->tableName`
					(`tx_hash`,  `tx_amount`,  `created_at`, `currency`, `wallet_address`) VALUES
					('$txHash', '$txAmount', '$currentTime', '$currency', '$address')";

		$wpdb->query( $query );
	}

	public function tx_already_consumed($currency,$txHash) {
		global $wpdb;

		$query = "SELECT *
				  FROM `$this->tableName`
				  WHERE `currency` = '$currency' AND `tx_hash` = '$txHash'";

		$results = $wpdb->get_results( $query, ARRAY_A );

		return $results;
	}


}

?>