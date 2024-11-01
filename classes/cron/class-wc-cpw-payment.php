<?php

if ( !defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

class WC_CPW_Payment {

	public static function check_all_addresses_for_matching_payment( $transactionLifetime ) {
		$paymentRepo = new WC_CPW_Payment_Repo();

		// get a unique list of unpaid "payments" to crypto addresses
		$addressesToCheck = $paymentRepo->get_distinct_unpaid_addresses();

		$cryptos = WC_CPW_Currencies::get();

		foreach ( $addressesToCheck as $record ) {
			$address = $record[ 'address' ];

			$cryptoId	 = $record[ 'cryptocurrency' ];
			$crypto		 = $cryptos[ $cryptoId ];

			self::check_address_transactions_for_matching_payments( $crypto, $address, $transactionLifetime );
		}
	}

	private static function check_address_transactions_for_matching_payments( $crypto, $address, $transactionLifetime ) {
		global $woocommerce;
		$paymentRepo = new WC_CPW_Payment_Repo();

		$transactionRepo = new WC_CPW_Transaction_Repo();

		$cryptoId = $crypto->get_id();

		WC_CPW_Logger::info( __FILE__, __LINE__, '===========================================================================' );
		WC_CPW_Logger::info( __FILE__, __LINE__, 'Starting payment verification for: ' . $cryptoId . ' - ' . $address );

		try {
			$transactions = $crypto->get_address_transactions( $address );

			if ( $transactions[ 'result' ] === 'error' ) {
				WC_CPW_Logger::info( __FILE__, __LINE__, 'Unable to get transactions for ' . $cryptoId );
				return;
			}

			$transactions = $transactions[ 'transactions' ];
		} catch ( \Exception $e ) {
			WC_CPW_Logger::info( __FILE__, __LINE__, 'Unable to get transactions for ' . $cryptoId );
			return;
		}

		WC_CPW_Logger::info( __FILE__, __LINE__, 'Transcations found for ' . $cryptoId . ' - ' . $address . ': ' . print_r( $transactions, true ) );

		foreach ( $transactions as $transaction ) {
			$txHash				 = $transaction->get_hash();
			$transactionAmount	 = $transaction->get_amount();

			$requiredConfirmations	 = $crypto->get_autopay_required_confirmations();
			$txConfirmations		 = $transaction->get_confirmations();

			WC_CPW_Logger::info( __FILE__, __LINE__, '---confirmations: ' . $txConfirmations . ' Required: ' . $requiredConfirmations );
			if ( $txConfirmations < $requiredConfirmations ) {
				continue;
			}

			$txTimeStamp = $transaction->get_time_stamp();
			$timeSinceTx = time() - $txTimeStamp;

			WC_CPW_Logger::info( __FILE__, __LINE__, '---time since transaction: ' . $timeSinceTx . ' TX Lifetime: ' . $transactionLifetime );
			if ( $timeSinceTx > $transactionLifetime ) {
				continue;
			}

			if ( $transactionRepo->tx_already_consumed( $cryptoId, $txHash ) ) {
				WC_CPW_Logger::info( __FILE__, __LINE__, '---Collision occurred for old transaction, skipping....' );
				continue;
			}

			$paymentRecords = $paymentRepo->get_unpaid_for_address( $cryptoId, $address );

			$matchingPaymentRecords = [];
			foreach ( $paymentRecords as $record ) {



				$paymentAmount				 = $record[ 'order_amount' ];
				$paymentAmountSmallestUnit	 = $paymentAmount * (10 ** $crypto->get_round_precision());

				$autoPaymentPercent = apply_filters( 'cpw_autopay_percent', $crypto->get_autopay_processing_percent(), $paymentAmount, $cryptoId, $address );

				$percentDifference = abs( $transactionAmount - $paymentAmountSmallestUnit ) / $transactionAmount;

				if ( $percentDifference <= (1 - $autoPaymentPercent) ) {
					$matchingPaymentRecords[] = $record;
				}

				WC_CPW_Logger::info( __FILE__, __LINE__, '---CryptoId, paymentAmount, paymentAmountSmallestUnit, transactionAmount, percentDifference:' . $cryptoId . ',' . $paymentAmount . ',' . $paymentAmountSmallestUnit . ',' . $transactionAmount . ',' . $percentDifference );
			}

			// Transaction does not match any order payment
			if ( count( $matchingPaymentRecords ) == 0 ) {
				// Do nothing
			}
			if ( count( $matchingPaymentRecords ) > 1 ) {
				// We have a collision, send admin note to each order
				foreach ( $matchingPaymentRecords as $matchingRecord ) {
					$orderId = $matchingRecord[ 'order_id' ];
					if ( !wc_get_order( $orderId ) ) {
						continue;
					}
					$order = new WC_Order( $orderId );
					$order->add_order_note( sprintf( __( 'This order has a matching %s transaction but we cannot verify it due to other orders with similar payment totals. Please reconcile manually. Transaction Hash: %s', 'wc-crypto-payments' ), $cryptoId, $txHash ) );
					update_post_meta( $orderId, 'cpw_collision', '' );
                                        update_post_meta( $orderId, 'collisions_orders', array_map(function ($item) { return $item['order_id']; }, $matchingPaymentRecords));
                                }


				$transactionRepo->add_consumed_tx( $txHash, $transactionAmount, $cryptoId, $address );
			}
			if ( count( $matchingPaymentRecords ) == 1 ) {
				// We have validated a transaction: update database to paid, update order to processing, add transaction to consumed transactions
				$orderId	 = $matchingPaymentRecords[ 0 ][ 'order_id' ];
				$orderAmount = $matchingPaymentRecords[ 0 ][ 'order_amount' ];

				$paymentRepo->set_status( $orderId, $orderAmount, 'paid' );
				$paymentRepo->set_hash( $orderId, $orderAmount, $txHash );

				$transactionRepo->add_consumed_tx( $txHash, $transactionAmount, $cryptoId, $address );

				if ( !wc_get_order( $orderId ) ) {
					continue;
				}

				$order		 = new WC_Order( $orderId );
				$orderNote	 = sprintf(
				__( 'Order payment of %s %s verified at %s. Transaction Hash: %s', 'wc-crypto-payments' ),
		$crypto->get_price_string( $transactionAmount / (10 ** $crypto->get_round_precision()) ),
							 $cryptoId,
							 date( 'Y-m-d H:i:s', time() ),
			  apply_filters( 'cpw_order_txhash', $txHash, $cryptoId ) );

				$order->payment_complete();
				$order->add_order_note( $orderNote );

				update_post_meta( $orderId, 'transaction_hash', $txHash );

			}

                        if ( isset( WC()->mailer()->emails['WC_Email_Crypto_Transaction'] ) ) {
                            WC()->mailer()->emails['WC_Email_Crypto_Transaction']->trigger( count( $matchingPaymentRecords ) == 1 ? wc_get_order($matchingPaymentRecords[ 0 ][ 'order_id' ]) : null, array(
                                'amount' => $transactionAmount,
                                'cryptocurrency' => $cryptoId,
                                'wallet' => $address,
                                'transaction' => $txHash,
                                'collisions_orders' => array_map(function ($item) { return $item['order_id']; }, $matchingPaymentRecords),
                            ) );
                        }
		}
	}

	public static function cancel_expired_payments() {
		global $woocommerce;

		$paymentRepo	 = new WC_CPW_Payment_Repo();
		$unpaidPayments	 = $paymentRepo->get_unpaid();
		$cryptos		 = WC_CPW_Currencies::get();

		foreach ( $unpaidPayments as $paymentRecord ) {
			$orderTime					 = $paymentRecord[ 'ordered_at' ];
			$cryptoId					 = $paymentRecord[ 'cryptocurrency' ];
			$crypto						 = $cryptos[ $cryptoId ];
			$paymentCancellationTimeHr	 = $crypto->get_autopay_cancellation_time();
			$paymentCancellationTimeSec	 = $paymentCancellationTimeHr * 60 * 60;
			$timeSinceOrder				 = time() - $orderTime;
			WC_CPW_Logger::info( __FILE__, __LINE__, 'cryptoID: ' . $cryptoId . ' payment cancellation time sec: ' . $paymentCancellationTimeSec . ' time since order: ' . $timeSinceOrder );

			if ( $timeSinceOrder > $paymentCancellationTimeSec ) {
				$orderId	 = $paymentRecord[ 'order_id' ];
				$orderAmount = $paymentRecord[ 'order_amount' ];
				$paymentRepo->set_status( $orderId, $orderAmount, 'cancelled' );
				$address	 = $paymentRecord[ 'address' ];
				WC_CPW_Logger::info( __FILE__, __LINE__, 'Cancelled ' . $cryptoId . ' payment: ' . $orderId . ' which was using address: ' . $address . 'due to non-payment.' );

				if ( !wc_get_order( $paymentRecord[ 'order_id' ] ) ) {
					continue;
				}
				$order		 = new WC_Order( $orderId );

				$orderNote = sprintf(
				__( 'Your %s order was <strong>cancelled</strong> because you were unable to pay for %s hour(s). Please do not send any funds to the payment address.', 'wc-crypto-payments' ),
		$cryptoId,
		round( $paymentCancellationTimeSec / 3600, 1 ),
		 $address );

				add_filter( 'woocommerce_email_subject_customer_note', function ( $subject, $order ) {
					$subject = sprintf( __( 'Order %s has been cancelled due to non-payment', 'wc-crypto-payments' ), $order->get_id() );
					return $subject;
				}, 1, 2 );
				add_filter( 'woocommerce_email_heading_customer_note', function ( $heading, $order ) {
					$heading = __( "Your order has been cancelled. Do not send any cryptocurrency to the payment address.", 'wc-crypto-payments' );
					return $heading;
				}, 1, 2 );

				$order->update_status( 'wc-cancelled' );
				$order->add_order_note( $orderNote, true );
			}
		}
	}

}

?>