<?php
if ( !defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

class WC_CPW_Gateway extends WC_Payment_Gateway {

	public $selected_cryptocurrencies	 = array();
	public $cryptocurrencies			 = array();
	public $selected_price_apis			 = array();
	public $price_apis					 = array();
	public $sections					 = array();

	/**
	 * Constructor for the gateway.
	 */
	public function __construct() {

		$this->id					 = 'cpw_gateway';
		$this->icon					 = "";
		$this->has_fields			 = false;
		$this->method_title			 = __( 'Crypto Payments', 'wc-crypto-payments' );
		$this->method_description	 = __( 'Allow customers to pay using cryptocurrency', 'wc-crypto-payments' );

		// Load the settings.
		$this->init_form_fields();
		$this->init_settings();

		// Define user set variables.
		$this->title		 = $this->get_option( 'title' );
		$this->description	 = $this->get_option( 'description' );
		$this->instructions	 = $this->get_option( 'instructions' );

		//
		$this->selected_cryptocurrencies = get_option( 'cpw_selected_cryptocurrencies', array() );
		$this->selected_price_apis		 = get_option( 'cpw_selected_price_apis', array( 0 ) );

		$this->cryptocurrencies	 = $this->get_cryptocurrencies();
		$this->sections			 = $this->get_sections();
		$this->price_apis		 = $this->get_price_apis();

		// Actions.
		add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
		add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'save_cryptocurrencies' ) );
		add_action( 'woocommerce_thankyou_' . $this->id, array( $this, 'thankyou_page' ) );

                add_filter( 'woocommerce_get_sections_checkout', array( $this, 'get_sections_checkout' ) );
                add_action( 'woocommerce_settings_checkout', array( $this, 'output_cryptocurrency_settings' ) );
		add_action( 'woocommerce_update_options_checkout', array( $this, 'save_cryptocurrency_settings' ) );

		add_action( 'admin_enqueue_scripts', array( $this, 'admin_enqueue_scripts' ) );

		if ( is_view_order_page() ) {
			add_action( 'woocommerce_order_details_before_order_table', array( $this, 'view_order' ) );
		}
	}

	/**
	 * Initialise Gateway Settings Form Fields.
	 */
	public function init_form_fields() {

		$this->form_fields = array(
			'enabled'			 => array(
				'title'		 => __( 'Enable/Disable', 'wc-crypto-payments' ),
				'type'		 => 'checkbox',
				'label'		 => __( 'Enable crypto payments', 'wc-crypto-payments' ),
				'default'	 => 'no',
			),
			'title'				 => array(
				'title'			 => __( 'Title', 'wc-crypto-payments' ),
				'type'			 => 'text',
				'description'	 => __( 'This controls the title which the user sees during checkout.', 'wc-crypto-payments' ),
				'default'		 => __( 'Cryptocurrency', 'wc-crypto-payments' ),
				'desc_tip'		 => true,
			),
			'description'		 => array(
				'title'			 => __( 'Description', 'wc-crypto-payments' ),
				'type'			 => 'textarea',
				'description'	 => __( 'Payment method description that the customer will see on your checkout.', 'wc-crypto-payments' ),
				'default'		 => __( 'Make your payment directly into our wallet. Your order will not be shipped until the funds have cleared in our account.', 'wc-crypto-payments' ),
				'desc_tip'		 => true,
			),
			'instructions'		 => array(
				'title'			 => __( 'Instructions', 'wc-crypto-payments' ),
				'type'			 => 'textarea',
				'description'	 => __( 'Instructions that will be added to the thank you page and emails. Supported tags: {currency}', 'wc-crypto-payments' ),
				'default'		 => __( '<p><strong>Only {currency} may be sent to this address.</strong><br>Network confirmations required: 2.</br>NOTE:Payments sent in any other cryptocurrency will not be credited.</p>', 'wc-crypto-payments' ),
				'desc_tip'		 => true,
			),
			'display_qr_code'	 => array(
				'title'			 => __( 'Display QR-code', 'wc-crypto-payments' ),
				'type'			 => 'checkbox',
				'description'	 => __( 'Display QR-code inside thank you and emails.', 'wc-crypto-payments' ),
				'label'			 => '&nbsp;',
				'default'		 => 'yes',
				'desc_tip'		 => true,
			)
		);

		if ( get_woocommerce_currency() !== 'USD' ) {
			$this->form_fields[ 'currency_converter_key' ] = array(
				'title'			 => sprintf( __( 'API key to convert %s to USD', 'wc-crypto-payments' ), get_woocommerce_currency() ),
				'type'			 => 'text',
				'description'	 => __( 'to get it - visit <a href="https://freecurrencyapi.net/register" target="_blank">https://freecurrencyapi.net/register</a>', 'wc-crypto-payments' ),
				'default'		 => '',
			);
		}

		$this->form_fields = array_merge( $this->form_fields, array(
			'selected_price_apis'		 => array(
				'type' => 'selected_price_apis',
			),
			'selected_cryptocurrencies'	 => array(
				'type' => 'selected_cryptocurrencies',
			),
		) );
	}

	/**
	 * Generate account details html.
	 *
	 * @return string
	 */
	public function generate_selected_cryptocurrencies_html() {

		ob_start();
		?>
		<tr valign="top">
			<th scope="row" class="titledesc"><?php esc_html_e( 'Active Cryptocurrency', 'wc-crypto-payments' ); ?></th>
			<td class="forminp" id="cryptocurrencies">
				<select name="cryptocurrency">
					<?php foreach ( $this->cryptocurrencies as $key => $label ): ?>
						<option value="<?php echo esc_attr( $key ) ?>" <?php echo in_array( $key, $this->selected_cryptocurrencies ) ? 'selected="selected"' : '' ?>>
							<?php esc_html_e( $label ) ?>
						</option>
					<?php endforeach; ?>
					<select>
						</td>
						</tr>
						<?php
						return ob_get_clean();
					}

					public function generate_selected_price_apis_html() {

						ob_start();
						?>
						<tr valign="top">
							<th scope="row" class="titledesc"><label><?php esc_html_e( 'APIs to get cryptocurrency rate', 'wc-crypto-payments' ); ?> <?php echo wc_help_tip( __( 'We use average of APIs selected. At least one must be selected. Adding more can slow down thank you page loading.', 'wc-crypto-payments' ) ) ?></label></th>
							<td class="forminp" id="price-apis">
								<?php foreach ( $this->price_apis as $key => $label ): ?>
									<label><input type="checkbox" name="price_apis[]" value="<?php echo esc_attr( $key ) ?>" <?php echo in_array( $key, $this->selected_price_apis ) ? 'checked' : '' ?>><?php esc_html_e( $label ) ?></label>
								<?php endforeach; ?>
							</td>
						</tr>
						<?php
						return ob_get_clean();
					}

					/**
					 * Save account details table.
					 */
					public function save_cryptocurrencies() {

						$cryptocurrencies = array();

						// phpcs:disable WordPress.Security.NonceVerification.Missing -- Nonce verification already handled in WC_Admin_Settings::save()
						if ( isset( $_POST[ 'cryptocurrency' ] ) ) {
							$cryptocurrencies = array( wc_clean( wp_unslash( $_POST[ 'cryptocurrency' ] ) ) );
						}
						// phpcs:enable

						update_option( 'cpw_selected_cryptocurrencies', $cryptocurrencies );

						$price_apis = array();

						// phpcs:disable WordPress.Security.NonceVerification.Missing -- Nonce verification already handled in WC_Admin_Settings::save()
						if ( isset( $_POST[ 'price_apis' ] ) ) {
							$price_apis = array_map('intval', wc_clean( wp_unslash( $_POST[ 'price_apis' ] ) ));
						}
						// phpcs:enable

						update_option( 'cpw_selected_price_apis', $price_apis );

						$currency_converter_key = '';

						// phpcs:disable WordPress.Security.NonceVerification.Missing -- Nonce verification already handled in WC_Admin_Settings::save()
						if ( isset( $_POST[ 'woocommerce_cpw_gateway_currency_converter_key' ] ) ) {
							$currency_converter_key = wc_clean ( wp_unslash( $_POST[ 'woocommerce_cpw_gateway_currency_converter_key' ] ) );
						}
						// phpcs:enable

						update_option( 'cpw_currency_converter_key', $currency_converter_key );
					}

					public function payment_fields() {
						try {
                                                        $selectOptions = $this->get_select_options_for_valid_cryptos();

                                                        if ( empty( $selectOptions ) ) {
                                                            _e('Please, configure wallets for cryptocurrencies', 'wc-crypto-payments');
                                                            return;
                                                        }

							$chosenCryptoId	 = current( array_keys($selectOptions) );
							$crypto			 = WC_CPW_Currencies::get_currency( $chosenCryptoId );
							$cryptoId		 = $crypto->get_id();

							$cryptoTotal = static::get_crypto_amount_order_total($cryptoId, WC()->cart->get_total( 'edit' ));
							//error_log('cryptoTotal post-dust: ' . $cryptoTotal);
							// format the crypto amount based on crypto
							$formattedCryptoTotal	 = $crypto->get_price_string( $cryptoTotal );

							echo '<img style="display:inline;height:23px;width:23px;vertical-align:middle;" src="' . esc_url($crypto->get_logo_file_path()) . '" />&nbsp;';
							echo '<span class="all-copy">' . esc_html($formattedCryptoTotal) . '</span>';
						} catch ( \Exception $e ) {
							WC_CPW_Logger::info( __FILE__, __LINE__, $e->getMessage() );
							wc_add_notice( $e->getMessage(), 'error' );
							echo esc_html($e->getMessage());
						}
					}

                                        /**
					 * Output for the order received page.
					 *
					 * @param int $order_id Order ID.
					 */
					public function thankyou_page( $order_id ) {
						$this->output_thank_you_html( WC_CPW_Currencies::get_currency( get_post_meta( $order_id, 'crypto_type_id', true ) ), get_post_meta( $order_id, 'wallet_address', true ), get_post_meta( $order_id, 'crypto_amount', true ), $order_id );
					}

					private function get_qr_prefix( $crypto ) {
						return strtolower( str_replace( ' ', '', $crypto->get_name() ) );
					}

					private function get_qr_code( $crypto, $walletAddress, $cryptoTotal, $orderId ) {

                                                $upload_dir = wp_upload_dir();

                                                $filename = 'cpw_qr_code_' . $orderId . '.png';
                                                $file     = $upload_dir['path'] . '/' . $filename;

                                                if ( file_exists( $file ) ) {
                                                    return $upload_dir['url'] . '/' . $filename;
                                                }

						$formattedName = $this->get_qr_prefix( $crypto );

						$qrData = $formattedName . ':' . $walletAddress . '?amount=' . $cryptoTotal;

						try {
							WC_CPW_QRcode::png( $qrData, $file, QR_ECLEVEL_H );
						} catch ( \Exception $e ) {
							WC_CPW_Logger::info( __FILE__, __LINE__, 'QR code generation failed, falling back...' );
							$endpoint = 'https://api.qrserver.com/v1/create-qr-code/?data=';
							return $endpoint . $qrData;
						}
						return $upload_dir['url'] . '/' . $filename;
					}

					public function save_order_meta_data( $order ) {

						try {
							$chosenCryptoId	 = $this->get_selected_cryptocurrency();
							$crypto			 = WC_CPW_Currencies::get_currency( $chosenCryptoId );
							$cryptoId		 = $crypto->get_id();

							update_post_meta( $order->get_id(), 'crypto_type_id', $cryptoId );
							// get current price of crypto

							$cryptoTotal = static::get_crypto_amount_order_total($cryptoId, $order->get_total());
                                                        // format the crypto amount based on crypto
                                                        $formattedCryptoTotal	 = $crypto->get_price_string( $cryptoTotal );

							update_post_meta( $order->get_id(), 'crypto_amount', $formattedCryptoTotal );

							WC_CPW_Logger::info( __FILE__, __LINE__, 'Crypto total: ' . $cryptoTotal . ' Formatted Total: ' . $formattedCryptoTotal );

							// if hd is enabled we have stuff to do
							if ( $crypto->hd_enabled() ) {
								$mpk	 = $crypto->get_mpk();
								$hdMode	 = $crypto->get_hd_mode();
								$hdRepo	 = new WC_CPW_Hd_Repo( $cryptoId, $mpk, $hdMode );

								// get fresh hd wallet
								$orderWalletAddress = $hdRepo->get_oldest_ready();

								// if we couldnt find a fresh one, force a new one
								if ( !$orderWalletAddress ) {

									try {
										$crypto->force_new_address();
										$orderWalletAddress = $hdRepo->get_oldest_ready();
									} catch ( \Exception $e ) {
										throw new \Exception( 'Unable to get payment address for order. This order has been cancelled. Please try again or contact the site administrator... Inner Exception: ' . $e->getMessage() );
									}
								}

								// update the database
								$hdRepo->set_status( $orderWalletAddress, 'assigned' );
								$hdRepo->set_order_id( $orderWalletAddress, $order->get_id() );
								$hdRepo->set_order_amount( $orderWalletAddress, $formattedCryptoTotal );

								$orderNote = sprintf(
								__( 'HD wallet address %s is awaiting payment of %s %s.', 'wc-crypto-payments' ),
			$orderWalletAddress,
			$formattedCryptoTotal,
			$cryptoId );
							}
							// HD is not enabled, just handle static wallet or carousel mode
							else {
								$orderWalletAddress = $crypto->get_next_carousel_address();

								// handle payment verification feature
								if ( $crypto->autopay_enabled() ) {
									$paymentRepo = new WC_CPW_Payment_Repo();

									$paymentRepo->insert( $orderWalletAddress, $cryptoId, $order->get_id(), $formattedCryptoTotal, 'unpaid' );
								}

								$orderNote = sprintf(
								__( 'Awaiting payment of %s %s to payment address %s.', 'wc-crypto-payments' ),
			$formattedCryptoTotal,
			$cryptoId,
			$orderWalletAddress );
							}

							// For customer reference and to handle refresh of thank you page
							update_post_meta( $order->get_id(), 'wallet_address', $orderWalletAddress );

							add_action( 'woocommerce_email_order_details', array( $this, 'additional_email_details' ), 10, 4 );

							$order->update_status( $this->get_order_status(), $orderNote );
						} catch ( \Exception $e ) {
							// cancel order if something went wrong
							$order->update_status( 'wc-failed', 'Error Message: ' . $e->getMessage() );
							WC_CPW_Logger::info( __FILE__, __LINE__, 'Something went wrong during checkout: ' . $e->getMessage() );
						}
					}

					protected function get_order_status() {
						return 'wc-on-hold';
					}

					protected function get_selected_cryptocurrency() {
                                                return current( array_keys($this->get_select_options_for_valid_cryptos()) );
					}

					public static function get_crypto_value_in_usd( $cryptoId, $updateInterval ) {

						if ( $cryptoId == "USDT" OR $cryptoId == "USDT_ERC20" OR $cryptoId == "USDC" OR $cryptoId == "BUSD" )
							return 1; // they match to USD

						$prices = array();

						$price_apis = get_option( 'cpw_selected_price_apis', array() );
						if ( !$price_apis ) {
							throw new \Exception( __( 'No price API selected. Please contact plug-in support.', 'wc-crypto-payments' ) );
						}

						$selectedPriceApis = $price_apis;

						if ( in_array( '0', $selectedPriceApis ) ) {
							$ccPrice = WC_CPW_Exchange::get_cryptocompare_price( $cryptoId, $updateInterval );

							if ( $ccPrice > 0 ) {
								$prices[] = $ccPrice;
							}
						}

						if ( in_array( '1', $selectedPriceApis ) ) {
							$hitbtcPrice = WC_CPW_Exchange::get_hitbtc_price( $cryptoId, $updateInterval );

							if ( $hitbtcPrice > 0 ) {
								$prices[] = $hitbtcPrice;
							}
						}

						if ( in_array( '2', $selectedPriceApis ) ) {
							$gateioPrice = WC_CPW_Exchange::get_gateio_price( $cryptoId, $updateInterval );

							if ( $gateioPrice > 0 ) {
								$prices[] = $gateioPrice;
							}
						}

						if ( in_array( '3', $selectedPriceApis ) ) {
							$bittrexPrice = WC_CPW_Exchange::get_bittrex_price( $cryptoId, $updateInterval );

							if ( $bittrexPrice > 0 ) {
								$prices[] = $bittrexPrice;
							}
						}

						if ( in_array( '4', $selectedPriceApis ) ) {
							$poloniexPrice = WC_CPW_Exchange::get_poloniex_price( $cryptoId, $updateInterval );

							// if there were no trades do not use this pricing method
							if ( $poloniexPrice > 0 ) {
								$prices[] = $poloniexPrice;
							}
						}
						$sum	 = 0;
						$count	 = count( $prices );

						if ( $count === 0 ) {
							throw new \Exception( __( 'No cryptocurrency exchanges could be reached, please try again.', 'wc-crypto-payments' ) );
						}

						foreach ( $prices as $price ) {
							$sum += $price;
						}

						$average_price = $sum / $count;

						return $average_price;
					}

					private function output_thank_you_html( $crypto, $orderWalletAddress, $cryptoTotal, $orderId ) {
						$formattedPrice	 = $crypto->get_price_string( $cryptoTotal );
						$customerMessage = apply_filters( 'cpw_customer_message', $this->get_customer_payment_message($crypto), $crypto, $orderId, $formattedPrice, $orderWalletAddress );

						$qrCode = $this->get_qr_code( $crypto, $orderWalletAddress, $cryptoTotal, $orderId );

						echo wp_kses_post($customerMessage);
						?>

						<h2><?php esc_html_e( 'Cryptocurrency payment details', 'wc-crypto-payments' ); ?></h2>
						<ul class="woocommerce-order-overview woocommerce-thankyou-order-details order_details">
							<?php if ( $this->get_option( 'display_qr_code' ) === 'yes' ): ?>
								<li class="woocommerce-order-overview__qr-code">
									<p style="word-wrap: break-word;"><?php esc_html_e( 'QR Code payment:', 'wc-crypto-payments' ); ?></p>
									<div class="qr-code-container">
                                                                            <img style="margin-top:3px;" src=<?php echo esc_url($qrCode); ?> />
									</div>
								</li>
							<?php endif; ?>
							<li>
                                                            <p style="word-wrap: break-word;"><?php esc_html_e( 'Wallet Address:', 'wc-crypto-payments' ); ?>
									<strong>
										<span class="woocommerce-Price-amount amount">
											<?php echo '<span class="all-copy">' . esc_html($orderWalletAddress) . '</span>' ?>
										</span>
									</strong>
								</p>
							</li>
							<li>
                                                            <p><?php esc_html_e( 'Currency:', 'wc-crypto-payments' ); ?>
									<strong>
										<?php
										echo '<img style="display:inline;height:23px;width:23px;vertical-align:middle;" src="' . esc_url($crypto->get_logo_file_path()) . '" />';
										?>
										<span style="padding-left: 4px; vertical-align: middle;" class="woocommerce-Price-amount amount" style="vertical-align: middle;">
											<?php esc_html_e($crypto->get_name()) ?>
										</span>
									</strong>
								</p>
							</li>
							<li>
                                                            <p style="word-wrap: break-word;"><?php esc_html_e( 'Total:', 'wc-crypto-payments' ); ?>
									<strong>
										<span class="woocommerce-Price-amount amount">
											<?php
											if ( $crypto->get_symbol() === '' ) {
												echo '<span class="all-copy">' . esc_html($formattedPrice) . '</span><span class="no-copy">&nbsp' . esc_html($crypto->get_code()) . '</span>';
											} else {
												echo '<span class="no-copy">' . esc_html($crypto->get_symbol()) . '</span>' . '<span class="all-copy">' . esc_html($formattedPrice) . '</span>';
											}
											?>
										</span>
									</strong>
								</p>
							</li>
						</ul>
						<?php
					}

					public function get_customer_payment_message($crypto) {
						if ($this->instructions)
							return str_replace('{currency}',  $crypto->get_id(), $this->instructions);
						return __( 'Once you have paid, please check your email for payment confirmation.', 'wc-crypto-payments' );
					}

					/**
					 * Process the payment and return the result.
					 *
					 * @param int $order_id Order ID.
					 * @return array
					 */
					public function process_payment( $order_id ) {

						$order = wc_get_order( $order_id );

						$this->save_order_meta_data( $order );

						// Return thankyou redirect.
						return array(
							'result'	 => 'success',
							'redirect'	 => $this->get_return_url( $order ),
						);
					}

					public function get_cryptocurrencies() {
						return WC_CPW_Currencies::get_currencies();
					}

					public function get_price_apis() {
						return array(
							'CryptoCompare',
							'HitBTC',
							'GateIO',
							'Bittrex',
							'Poloniex'
						);
					}

					public function get_sections() {

						$sections = array(
							$this->id => $this->method_title,
						);

						foreach ( $this->cryptocurrencies as $key => $label ) {
							$sections[ strtolower( $key ) ] = $label;
						}
						return $sections;
					}

					public function get_sections_checkout( $sections ) {

						global $current_section;

						if ( !isset( $this->sections[ $current_section ] ) ) {
							return $sections;
						}

						$output_sections = array_map( 'strtolower', array_merge( array( $this->id, 'wc_email_crypto_transaction' ), $this->selected_cryptocurrencies ) );
						$sections		 = array();

						foreach ( $this->sections as $key => $section ) {
							if ( in_array( $key, $output_sections ) ) {
								$sections[ $key ] = $section;
							}
						}

                                                return $sections;
                                        }

					public function output_cryptocurrency_settings() {

						global $current_section;

						if ( !isset( $this->sections[ $current_section ] ) || $current_section === $this->id ) {
							return;
						}

						$currency	 = WC_CPW_Currencies::get_currency( strtoupper( $current_section ) );
						if( defined("CRYPTOPAYMENTS_DISABLE_CURRENCY_EDITOR")) {
							$GLOBALS['hide_save_button'] = true;
							echo '<div class="notice notice-warning is-dismissible"><p>' . __( 'Read-only mode for currency. You won\'t be able to save changes.', 'wc-crypto-payments' ). '</p></div>';
						}
						?>
						<h2><?php echo esc_html( $this->sections[ $current_section ] ) ?></h2>
						<input type="hidden" name="cryptoId" value="<?php echo esc_attr($currency->get_id()) ?>">
                                                <input type="hidden" name="validMasks" value="<?php echo esc_attr(json_encode($currency->get_valid_masks())) ?>">
						<table class="form-table cpw-cryptocurrency-table-options">
							<tr>
								<th class="titledesc">
									<label>
										<?php esc_html_e( 'Mode', 'wc-crypto-payments' ) ?>
									</label>
								</th>
								<td class="forminp">
									<?php $mode		 = $currency->get_mode() ?>
									<fieldset>
										<ul>
											<li>
												<label>
													<input type="radio" value="0" name="cpw_options[mode]" <?php checked( $mode, '0' ) ?>>
													<?php esc_html_e( 'Manual Mode', 'wc-crypto-payments' ) ?>
												</label>
											</li>
											<?php if ( $currency->has_autopay() ): ?>
												<li>
													<label>
														<input type="radio" value="1" name="cpw_options[mode]" <?php checked( $mode, '1' ) ?>>
														<?php esc_html_e( 'Automatic Mode', 'wc-crypto-payments' ) ?>
													</label>
												</li>
											<?php endif; ?>
											<?php if ( $currency->has_hd() and WC_CPW_Loader::is_math_modules_installed() ): ?>
												<li>
													<label>
														<input type="radio" value="2" name="cpw_options[mode]" <?php checked( $mode, '2' ) ?>>
														<?php esc_html_e( 'Automatic Mode (HD Wallet)', 'wc-crypto-payments' ) ?>
													</label>
                                                                                                    <div style="font-style:italic"><?php esc_html_e( 'You can use any HD Wallet, for example: Ledger, Trezor, Bitcoin Core, Electrum, Mycelium and etc.', 'wc-crypto-payments' ) ?></div>
												</li>
											<?php endif; ?>
										</ul>
									</fieldset>
								</td>
							</tr>
							<tr class="mode mode-1 <?php echo in_array( $mode, array( '1' ) ) ? '' : 'hide' ?>">
								<td colspan="2">
									<div>
										<h3 class="autopay-disclaimer-title">
                                                                                    <b><?php esc_html_e( 'Autopay Disclaimer', 'wc-crypto-payments' ) ?></b></h3>
										<br>
										<div class="autopay-disclaimer-container">
											<?php esc_html_e( 'Please note Autopay Mode is still in <strong>beta</strong>. There is no guarantee every order will be processed correctly. If you have any questions contact us at https://algolplus.freshdesk.com/', 'wc-crypto-payments' ) ?>
                                                                                    <h3 class="autopay-disclaimer-adjustments"><?php esc_html_e( 'Adjusting the following settings can improve Autopay accuracy', 'wc-crypto-payments' ) ?></h3>
											<ul>
												<li>
                                                                                                    <strong><?php esc_html_e( 'Wallet Addresses:', 'wc-crypto-payments' ) ?> </strong>
													<?php esc_html_e( 'Adding more addresses greatly increases autopay reliability while increasing privacy.<br><i>We suggest having as many addresses as orders you get an hour in that cryptocurrency.</i>', 'wc-crypto-payments' ) ?>
												</li>
												<li>
                                                                                                    <strong><?php esc_html_e( 'Order Cancellation Timer:', 'wc-crypto-payments' ) ?> </strong>
													<?php esc_html_e( 'Reducing this will not only increase autopay reliability but also reduce the effects of volatility.<br><i>We suggest a value of 1 hour for high throughput stores.</i>', 'wc-crypto-payments' ) ?>
												</li>
												<li>
                                                                                                    <strong><?php esc_html_e( 'Auto-Confirm Percentage:', 'wc-crypto-payments' ) ?> </strong>
													<?php esc_html_e( 'Do not touch this unless you know what you are doing. <br><i>If you lower this setting you should have one wallet address for the maximum amount of orders you receive in an hour. (If you have a chance to get 30 orders in an hour in this cryptocurrency, we recommend 30 addresses.)</i>', 'wc-crypto-payments' ) ?>
												</li>
											</ul>
										</div>
										<p></p>
									</div>
								</td>
							</tr>
							<tr class="mode mode-0 mode-1 <?php echo in_array( $mode, array( '0', '1' ) ) ? '' : 'hide' ?>">
								<th class="titledesc">
									<label>
										<?php esc_html_e( 'Wallet Addresses', 'wc-crypto-payments' ) ?>
									</label>
								</th>
								<td class="forminp">
									<?php $addresses = $currency->get_addresses() ?>
									<?php if ( $addresses ): ?>
										<?php foreach ( $addresses as $address ): ?>
											<div class="address-item">
												<input type="text" name="cpw_options[addresses][]" value="<?php echo esc_attr( $address ) ?>">
												<a href="#" class="remove-button">
													<?php esc_html_e( 'Remove', 'wc-crypto-payments' ) ?>
												</a>
                                                                                                <div class="address-item-error-message"><?php esc_html_e( 'Please, enter valid wallet address', 'wc-crypto-payments' ) ?></div>
											</div>
										<?php endforeach; ?>
									<?php else: ?>
										<div class="address-item">
											<input type="text" name="cpw_options[addresses][]">
											<a href="#" class="remove-button">
												<?php esc_html_e( 'Remove', 'wc-crypto-payments' ) ?>
											</a>
                                                                                        <div class="address-item-error-message"><?php esc_html_e( 'Please, enter valid wallet address', 'wc-crypto-payments' ) ?></div>
										</div>
									<?php endif; ?>
                                                                    <button class="button-primary add-more-button"><?php esc_html_e( 'Add More', 'wc-crypto-payments' ) ?></button>
								</td>
							</tr>
							<tr class="mode mode-1 <?php echo in_array( $mode, array( '1' ) ) ? '' : 'hide' ?>">
								<th class="titledesc">
									<label>
										<?php esc_html_e( 'Auto-Confirm Percentage', 'wc-crypto-payments' ) ?>
									</label>
								</th>
								<td class="forminp">
									<input type="number" name="cpw_options[autopayment_percent_to_process]" step="0.0001" size="5" value="<?php echo esc_attr( $currency->get_autopay_processing_percent() ) ?>">
									<div class="note">
										<?php esc_html_e( 'Auto-Payment will automatically confirm payments that are within this percentage of the total amount requested. Contact https://algolplus.freshdesk.com/ before changing this value.', 'wc-crypto-payments' ) ?>
									</div>
								</td>
							</tr>
							<tr class="mode mode-1 <?php echo in_array( $mode, array( '1' ) ) ? '' : 'hide' ?>">
								<th class="titledesc">
									<label>
										<?php esc_html_e( 'Required Confirmations', 'wc-crypto-payments' ) ?>
									</label>
								</th>
								<td class="forminp">
									<input type="number" name="cpw_options[autopayment_required_confirmations]" step="1" min="0" size="5" value="<?php echo esc_attr( $currency->get_autopay_required_confirmations() ) ?>">
									<div class="note">
										<?php esc_html_e( 'This is the number of confirmations a payment needs to receive before it is considered a valid payment.', 'wc-crypto-payments' ) ?>
									</div>
								</td>
							</tr>
							<tr class="mode mode-1 <?php echo in_array( $mode, array( '1' ) ) ? '' : 'hide' ?>">
								<th class="titledesc">
									<label>
										<?php esc_html_e( 'Order Cancellation Timer (hr)', 'wc-crypto-payments' ) ?>
									</label>
								</th>
								<td class="forminp">
									<input type="number" name="cpw_options[autopayment_order_cancellation_time_hr]" step="0.1" size="5" value="<?php echo esc_attr( $currency->get_autopay_cancellation_time() ) ?>">
									<div class="note">
										<?php esc_html_e( 'This is the amount of time in hours that has to elapse before an order is cancelled automatically. (1.5 = 1 hour 30 minutes)', 'wc-crypto-payments' ) ?>
									</div>
								</td>
							</tr>
							<tr class="mode mode-2 <?php echo in_array( $mode, array( '2' ) ) ? '' : 'hide' ?>">
								<th class="titledesc">
									<label>
										<?php esc_html_e( 'Sample Addresses', 'wc-crypto-payments' ) ?>
									</label>
									<div class="note">
										<?php esc_html_e( '<span style="color: red;">PLEASE VERIFY YOU CONTROL THESE ADDRESSES BEFORE SAVING OR ELSE LOSS OF FUNDS WILL OCCUR!!!!</span><br> Addresses will be generated when a valid MPK is input.', 'wc-crypto-payments' ) ?>
									</div>
								</th>
								<td class="forminp">
									<?php $addresses = $currency->get_hd_mpk_sample_addresses() ?>
									<?php if ( $addresses ): ?>
										<?php foreach ( $addresses as $i => $address ): ?>
											<?php if ( $i ): ?>
												<br/>
											<?php endif; ?>
											<input type="text" name="cpw_options[hd_mpk_sample_addresses][]" readonly="readonly" value="<?php echo esc_attr( $address ) ?>">
										<?php endforeach; ?>
									<?php else: ?>
										<input type="text" name="cpw_options[hd_mpk_sample_addresses][]" readonly="readonly">
										<br/>
										<input type="text" name="cpw_options[hd_mpk_sample_addresses][]" readonly="readonly">
										<br/>
										<input type="text" name="cpw_options[hd_mpk_sample_addresses][]" readonly="readonly">
									<?php endif; ?>
									<div class="note">
										<?php esc_html_e( '<span style="color: red;">PLEASE VERIFY YOU OWN THESE ADDRESSES BEFORE SAVING OR ELSE LOSS OF FUNDS WILL OCCUR!!!!</span><br><br>Once you have entered a valid MPK we will generate these addresses.<br><br>Due to lack of convention around MPK xpub prefixes, it is not possible to guess which address format an xpub should generate. We currently ONLY GENERATE LEGACY ADDRESSES starting with a "1".', 'wc-crypto-payments' ) ?>
									</div>
								</td>
							</tr>
							<tr class="mode mode-2 <?php echo in_array( $mode, array( '2' ) ) ? '' : 'hide' ?>">
								<th class="titledesc">
									<label>
										<?php esc_html_e( 'MPK', 'wc-crypto-payments' ) ?>
									</label>
								</th>
								<td class="forminp">
									<textarea name="cpw_options[hd_mpk]" rows="6"><?php echo esc_attr( $currency->get_mpk() ) ?></textarea>
									<div class="note">
										<?php esc_html_e( 'Your HD Wallet Master Public Key. We highly recommend using a brand new MPK for each store you run. You run the risk of address reuse and incorrectly processed orders if you use your MPK for multiple stores and/or purposes (such as donations on another platform).', 'wc-crypto-payments' ) ?>
									</div>
								</td>
							</tr>
							<tr class="mode mode-2 <?php echo in_array( $mode, array( '2' ) ) ? '' : 'hide' ?>">
								<th class="titledesc">
									<label>
										<?php esc_html_e( 'Auto-Confirm Percentage', 'wc-crypto-payments' ) ?>
									</label>
								</th>
								<td class="forminp">
									<input type="number" name="cpw_options[hd_percent_to_process]" step="0.001" size="5" value="<?php echo esc_attr( $currency->get_hd_processing_percent() ) ?>">
									<div class="note">
										<?php esc_html_e( 'This mode will automatically confirm payments that are this percentage of the total amount requested. (1 = 100%), (0.94 = 94%)', 'wc-crypto-payments' ) ?>
									</div>
								</td>
							</tr>
							<tr class="mode mode-2 <?php echo in_array( $mode, array( '2' ) ) ? '' : 'hide' ?>">
								<th class="titledesc">
									<label>
										<?php esc_html_e( 'Required Confirmations', 'wc-crypto-payments' ) ?>
									</label>
								</th>
								<td class="forminp">
									<input type="number" name="cpw_options[hd_required_confirmations]" step="1" size="5" value="<?php echo esc_attr( $currency->get_hd_required_confirmations() ) ?>">
									<div class="note">
										<?php esc_html_e( 'This is the number of confirmations a payment needs to receive before it is considered a valid payment.', 'wc-crypto-payments' ) ?>
									</div>
								</td>
							</tr>
							<tr class="mode mode-2 <?php echo in_array( $mode, array( '2' ) ) ? '' : 'hide' ?>">
								<th class="titledesc">
									<label>
										<?php esc_html_e( 'Order Cancellation Timer (hr)', 'wc-crypto-payments' ) ?>
									</label>
								</th>
								<td class="forminp">
									<input type="number" name="cpw_options[hd_order_cancellation_time_hr]" step="0.1" size="5" value="<?php echo esc_attr( $currency->get_hd_cancellation_time() ) ?>">
									<div class="note">
										<?php esc_html_e( 'This is the amount of time in hours that has to elapse before an order is cancelled automatically. (1.5 = 1 hour 30 minutes)', 'wc-crypto-payments' ) ?>
									</div>
								</td>
							</tr>
						</table>

						<?php
					}

					public function save_cryptocurrency_settings() {

						global $current_section;

						if ( !isset( $this->sections[ $current_section ] ) || $current_section === $this->id ) {
							return;
						}
						if ( !defined("CRYPTOPAYMENTS_DISABLE_CURRENCY_EDITOR") && isset( $_POST[ 'cpw_options' ] ) && is_array( $_POST[ 'cpw_options' ] ) ) {
							$cryptocurrency_options	 = wc_clean ( wp_unslash( $_POST[ 'cpw_options' ] ) );
							$currency				 = WC_CPW_Currencies::get_currency( strtoupper( $current_section ) );
							$currency->save_settings( $cryptocurrency_options );
						}
					}

					public function admin_enqueue_scripts() {
						wp_enqueue_style( 'cpw-admin-css', WC_CPW_PLUGIN_URL . '/assets/css/admin.css', array(), WC_CPW_VERSION, 'all' );
						wp_enqueue_script( 'cpw-admin-js', WC_CPW_PLUGIN_URL . '/assets/js/admin.js', array( 'jquery' ), WC_CPW_VERSION, true );

						wp_localize_script( 'cpw-admin-js', 'cpwAdminData', array(
							'enter_valid_mpk_string'			 => __( 'Please enter a valid mpk', 'wc-crypto-payments' ),
							'generate_hd_addresses_string'		 => __( 'Generating HD Addresses...', 'wc-crypto-payments' ),
							'addresses_creation_failed_string'	 => __( 'Address creation failed, please check your mpk.', 'wc-crypto-payments' ),
							'entered_segwit_mpk_string'			 => __( 'You have entered a valid Segwit MPK.', 'wc-crypto-payments' ),
						) );
					}

					public function additional_email_details( $order, $sent_to_admin, $plain_text, $email ) {
						$crypto				 = WC_CPW_Currencies::get_currency( get_post_meta( $order->get_id(), 'crypto_type_id', true ) );
						$orderCryptoTotal	 = get_post_meta( $order->get_id(), 'crypto_amount', true );
						$orderWalletAddress	 = get_post_meta( $order->get_id(), 'wallet_address', true );

						$qrCode = $this->get_qr_code( $crypto, $orderWalletAddress, $orderCryptoTotal, $order->get_id() );
						?>
                                                <h2><?php esc_html_e( 'Additional Details', 'wc-crypto-payments' ); ?></h2>
						<?php if ( $this->get_option( 'display_qr_code' ) === 'yes' ): ?>
                                                <p><?php esc_html_e( 'QR Code Payment:', 'wc-crypto-payments' ); ?> </p>
							<div style="margin-bottom:12px;">
								<img  src=<?php echo esc_url($qrCode); ?> />
							</div>
						<?php endif; ?>
						<p>
							<?php esc_html_e( 'Wallet Address:', 'wc-crypto-payments' ); ?> <?php esc_html_e($orderWalletAddress) ?>
						</p>
						<p>
							<?php esc_html_e( 'Currency:', 'wc-crypto-payments' ); ?> <?php echo '<img src="' . esc_url($crypto->get_logo_file_path()) . '" alt="" />' . esc_html($crypto->get_name()); ?>
						</p>
						<p>
							<?php esc_html_e( 'Total:', 'wc-crypto-payments' ); ?>
							<?php
							if ( $crypto->get_symbol() === '' ) {
                                                            esc_html_e($crypto->get_price_string( $orderCryptoTotal ) . ' ' . $crypto->get_id());
							} else {
     esc_html_e($crypto->get_symbol() . $crypto->get_price_string( $orderCryptoTotal ));
							}
							?>
						</p>
                                                <?php if ($this->instructions): ?>
                                                    <?php echo wp_kses_post(str_replace('{currency}',  $crypto->get_id(), $this->instructions)) ?>
                                                <?php endif; ?>
						<?php
					}

					public static function get_currency_converter_key() {
						return get_option( 'cpw_currency_converter_key' );
					}

					public function view_order( $order ) {

						if ( $order->get_status() === 'cancelled' || !get_post_meta( $order->get_id(), 'crypto_type_id', true ) ) {
							return;
						}

						$crypto				 = WC_CPW_Currencies::get_currency( get_post_meta( $order->get_id(), 'crypto_type_id', true ) );
						$orderCryptoTotal	 = get_post_meta( $order->get_id(), 'crypto_amount', true );
						$orderWalletAddress	 = get_post_meta( $order->get_id(), 'wallet_address', true );

						$qrCode = $this->get_qr_code( $crypto, $orderWalletAddress, $orderCryptoTotal, $order->get_id() );
						?>
                                                <h2><?php esc_html_e( 'Additional Details', 'wc-crypto-payments' ); ?></h2>
						<?php if ( $this->get_option( 'display_qr_code' ) === 'yes' ): ?>
                                                <p><?php esc_html_e( 'QR Code Payment:', 'wc-crypto-payments' ); ?> </p>
							<div style="margin-bottom:12px;">
								<img  src=<?php echo esc_url($qrCode); ?> />
							</div>
						<?php endif; ?>
						<p>
							<?php esc_html_e( 'Wallet Address:', 'wc-crypto-payments' ); ?> <?php esc_html_e($orderWalletAddress) ?>
						</p>
						<p>
							<?php esc_html_e( 'Currency:', 'wc-crypto-payments' ); ?> <?php echo '<img src="' . esc_url($crypto->get_logo_file_path()) . '" alt="" />' . esc_html_e($crypto->get_name()); ?>
						</p>
						<p>
							<?php esc_html_e( 'Total:', 'wc-crypto-payments' ); ?>
							<?php
							if ( $crypto->get_symbol() === '' ) {
                                                            esc_html_e($crypto->get_price_string( $orderCryptoTotal ) . ' ' . $crypto->get_id());
							} else {
                                                            esc_html_e($crypto->get_symbol() . $crypto->get_price_string( $orderCryptoTotal ));
							}
							?>
						</p>

						<?php
					}

                                        public function validate_fields() {
                                                // if the currently selected gateway is this gateway we set transients related to conversions and if something goes wrong we prevent the customer from hitting the thank you page  by throwing the WooCommerce Error Notice.
                                                if ( WC()->session->get( 'chosen_payment_method' ) === $this->id ) {
                                                        try {
                                                                if ( ! $this->get_select_options_for_valid_cryptos() ) {
                                                                    throw new \Exception(__('Please, configure wallets for cryptocurrencies', 'wc-crypto-payments'));
                                                                }
                                                        } catch ( \Exception $e ) {
                                                                WC_CPW_Logger::info( __FILE__, __LINE__, $e->getMessage() );
                                                                wc_add_notice( $e->getMessage(), 'error' );
                                                        }
                                                }
                                        }

                                        protected function get_select_options_for_valid_cryptos() {
                                                $selectOptionArray = array();

                                                foreach ( WC_CPW_Currencies::get() as $crypto ) {
                                                        if ( $crypto->crypto_selected_and_valid() && in_array( $crypto->get_id(), $this->selected_cryptocurrencies ) ) {
                                                                $selectOptionArray[ $crypto->get_id() ] = $crypto->get_name();
                                                        }
                                                }

                                                return $selectOptionArray;
                                        }

                                        public static function get_crypto_amount_order_total($cryptoId, $orderTotal) {

                                            $crypto = WC_CPW_Currencies::get_currency( $cryptoId );

                                            // handle different woocommerce currencies and get the order total in USD
                                            $curr = get_woocommerce_currency();

                                            if ($curr === 'BTC' && $cryptoId === 'BTC') {
                                                return $orderTotal;
                                            }

                                            $cryptoPerUsd = static::get_crypto_value_in_usd( $crypto->get_code(), $crypto->get_update_interval() );

                                            $usdTotal = $curr === 'BTC' ? $orderTotal * static::get_crypto_value_in_usd( $curr, WC_CPW_Currencies::get_currency( $curr )->get_update_interval() ) : WC_CPW_Exchange::get_order_total_in_usd( $orderTotal, $curr );

                                            $cryptoMarkupPercent = $crypto->get_markup();

                                            if ( !is_numeric( $cryptoMarkupPercent ) ) {
                                                    $cryptoMarkupPercent = 0.0;
                                            }

                                            $cryptoMarkup			 = $cryptoMarkupPercent / 100.0;
                                            $cryptoPriceRatio		 = 1.0 + $cryptoMarkup;
                                            $cryptoTotalPreMarkup	 = round( $usdTotal / $cryptoPerUsd, $crypto->get_round_precision(), PHP_ROUND_HALF_UP );
                                            $cryptoTotal			 = $cryptoTotalPreMarkup * $cryptoPriceRatio;

                                            $dustAmount				 = apply_filters( 'cpw_dust_amount', 0.000000000000000000, $cryptoId, $cryptoPerUsd, $crypto->get_round_precision(), $usdTotal, $cryptoTotal );
                                            //error_log('filter dust amount: ' . $dustAmount);
                                            //error_log('cryptoTotal pre-dust: ' . $cryptoTotal);
                                            $cryptoTotal			 += $dustAmount;
                                            //error_log('cryptoTotal post-dust: ' . $cryptoTotal);

                                            return $cryptoTotal;
                                        }

				}
