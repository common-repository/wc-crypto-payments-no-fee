<?php

if ( !defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

class WC_CPW_Hd {

	public static function buffer_ready_addresses( $crypto, $mpk, $amount, $hdMode ) {
		$hdRepo		 = new WC_CPW_Hd_Repo( $crypto->get_id(), $mpk, $hdMode );
		$readyCount	 = $hdRepo->count_ready();

		$neededAddresses = $amount - $readyCount;

		for ( $i = 0; $i < $neededAddresses; $i++ ) {

			try {
				$crypto->force_new_address();
			} catch ( \Exception $e ) {
				WC_CPW_Logger::info( __FILE__, __LINE__, $e->getMessage() );
			}
		}
	}

	public static function check_all_pending_addresses_for_payment( $crypto, $mpk, $requiredConfirmations,
																 $percentToVerify, $hdMode ) {
		global $woocommerce;
		$cryptoId	 = $crypto->get_id();
		$hdRepo		 = new WC_CPW_Hd_Repo( $cryptoId, $mpk, $hdMode );

		$pendingRecords = $hdRepo->get_pending();

		foreach ( $pendingRecords as $record ) {

			if ( !wc_get_order( $record[ 'order_id' ] ) ) {
				continue;
			}

			try {
				$blockchainTotalReceived = $crypto->get_total_received_for_address( $record[ 'address' ], $requiredConfirmations );
			} catch ( \Exception $e ) {
				// just go to next record if the endpoint is not responding
				continue;
			}

			$recordTotalReceived = $record[ 'total_received' ];
			$newPaymentAmount	 = $blockchainTotalReceived - $recordTotalReceived;

			// if we received a new payment
			// TODO: This should be 1 / 10*max digits
			if ( $newPaymentAmount > 0.0000001 ) {

				$address = $record[ 'address' ];

				$orderAmount = $record[ 'order_amount' ];
				WC_CPW_Logger::info( __FILE__, __LINE__, 'Address ' . $address . ' received a new payment of ' . $crypto->get_price_string( $newPaymentAmount ) . ' ' . $cryptoId );
				// set total in database because we received a payment
				$hdRepo->set_total_received( $address, $blockchainTotalReceived );

				$amountToVerify			 = ((float) $orderAmount) * $percentToVerify;
				$paymentAmountVerified	 = $blockchainTotalReceived >= $amountToVerify;

				// if new total is enough to process the order
				if ( $paymentAmountVerified ) {

					$orderId = $record[ 'order_id' ];
					$order	 = new WC_Order( $orderId );

					$orderNote = sprintf(
					__( 'Order payment of %s %s verified at %s.', 'wc-crypto-payments' ),
		 $crypto->get_price_string( $blockchainTotalReceived ),
							  $cryptoId,
							  date( 'Y-m-d H:i:s', time() ) );

					//$order->update_status('wc-processing', $orderNote);
					$order->payment_complete();
					$order->add_order_note( $orderNote );

					$hdRepo->set_status( $address, 'complete' );
				}
				// we received payment but it was not enough to meet store admin's processing requirement
				else {
					$orderId = $record[ 'order_id' ];
					$order	 = new WC_Order( $orderId );

					// handle multiple underpayments, just add a new note
					if ( $record[ 'status' ] === 'underpaid' ) {
						$orderNote = sprintf(
						__( 'New payment was received but is still under order total. Received payment of %s %s.<br>Remaining payment required: %s<br>Wallet Address: %s', 'wc-crypto-payments' ),
		  $crypto->get_price_string( $newPaymentAmount ),
							   $cryptoId,
							   $crypto->get_price_string( ((float) $orderAmount) - $blockchainTotalReceived ),
									 $address );

						add_filter( 'woocommerce_email_subject_customer_note', function ( $subject, $order ) {
							$subject = sprintf( __( 'Partial payment received for Order %s', 'wc-crypto-payments' ), $order->get_id() );
							return $subject;
						}, 1, 2 );
						add_filter( 'woocommerce_email_heading_customer_note', function ( $heading, $order ) {
							$heading = sprintf( __( 'Partial payment received for Order %s', 'wc-crypto-payments' ), $order->get_id() );
							return $heading;
						}, 1, 2 );

						$order->add_order_note( $orderNote, true );
					}
					// handle first underpayment, update status to pending payment (since we use on-hold for orders with no payment yet)
					else {
						$orderNote = sprintf(
						__( 'Payment of %s %s received at %s. This is under the amount required to process this order.<br>Remaining payment required: %s<br>Wallet Address: %s', 'wc-crypto-payments' ),
		  $crypto->get_price_string( $blockchainTotalReceived ),
							   $cryptoId,
							   date( 'm/d/Y g:i a', time() + (60 * 60 * get_option( 'gmt_offset' )) ),
															   $crypto->get_price_string( $amountToVerify - $blockchainTotalReceived ),
											 $address );

						add_filter( 'woocommerce_email_subject_customer_note', function ( $subject, $order ) {
							$subject = sprintf( __( 'Partial payment received for Order %s', 'wc-crypto-payments' ), $order->get_id() );
							return $subject;
						}, 1, 2 );
						add_filter( 'woocommerce_email_heading_customer_note', function ( $heading, $order ) {
							$heading = sprintf( __( 'Partial payment received for Order %s', 'wc-crypto-payments' ), $order->get_id() );
							return $heading;
						}, 1, 2 );

						$order->add_order_note( $orderNote, true );
						$hdRepo->set_status( $address, 'underpaid' );
					}
				}

                                $orderId = $record[ 'order_id' ];
                                $order	 = new WC_Order( $orderId );
			}
		}
	}

	public static function cancel_expired_addresses( $crypto, $mpk, $orderCancellationTimeSec, $hdMode ) {
		global $woocommerce;
		$cryptoId	 = $crypto->get_id();
		$hdRepo		 = new WC_CPW_Hd_Repo( $cryptoId, $mpk, $hdMode );

		$assignedRecords = $hdRepo->get_assigned();

		foreach ( $assignedRecords as $record ) {

			$assignedAt		 = $record[ 'assigned_at' ];
			$totalReceived	 = $record[ 'total_received' ];
			$address		 = $record[ 'address' ];
			$orderId		 = $record[ 'order_id' ];

			$assignedFor = time() - $assignedAt;
			WC_CPW_Logger::info( __FILE__, __LINE__, 'address ' . $address . ' has been assigned for ' . $assignedFor . '... cancel time: ' . $orderCancellationTimeSec );
			if ( $assignedFor > $orderCancellationTimeSec && $totalReceived == 0 ) {

				if ( !wc_get_order( $record[ 'order_id' ] ) ) {
					continue;
				}

				// since order was cancelled we can re-use the address, set status to ready
				$hdRepo->set_status( $address, 'ready' );
				$hdRepo->set_order_amount( $address, 0.0 );

				$order		 = new WC_Order( $orderId );
				$orderNote	 = sprintf(
				__( 'Your %s order was <strong>cancelled</strong> because you were unable to pay for %s hour(s). Please do not send any funds to the payment address.', 'wc-crypto-payments' ),
		$cryptoId,
		round( $orderCancellationTimeSec / 3600, 1 ),
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

				WC_CPW_Logger::info( __FILE__, __LINE__, 'Cancelled order: ' . $orderId . ' which was using address: ' . $address . 'due to non-payment.' );
			}
		}
	}

}

?>