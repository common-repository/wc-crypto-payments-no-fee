<?php

if ( !defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

class WC_CPW_Loader {

	public static $hd_table;
	public static $payment_table;
	public static $transaction_table;

	public function __construct() {
		//need vars
		global $wpdb;
		self::$hd_table		 = "{$wpdb->prefix}wc_cpw_hd_addresses";
		self::$payment_table = "{$wpdb->prefix}wc_cpw_payments";
		self::$transaction_table = "{$wpdb->prefix}wc_cpw_transactions";

		add_action( 'plugins_loaded', function () {
			$vendor_classes = array(
				'WC_CPW_bcmath_Utils'=>'bcmath_Utils.php',
				'WC_CPW_CurveFp'=>'CurveFp.php',
				'WC_CPW_HdHelper'=>'HdHelper.php',
				'WC_CPW_gmp_Utils'=>'gmp_Utils.php',
				'WC_CPW_NumberTheory'=>'NumberTheory.php',
				'WC_CPW_Point'=>'Point.php',
				'\CashAddress\WC_CPW_CashAddress'=>'CashAddress.php',
				'WC_CPW_QRinput'=>'phpqrcode.php',
			);
			foreach($vendor_classes as $class_name=>$file_name) {
				if (!class_exists($class_name))
					include WC_CPW_PLUGIN_PATH . 'vendor/' . $file_name;
			}
			include WC_CPW_PLUGIN_PATH . "classes/utils/class-wc-cpw-logger.php";
			include WC_CPW_PLUGIN_PATH . "classes/utils/class-wc-cpw-exchange.php";
			include WC_CPW_PLUGIN_PATH . "classes/data/class-wc-cpw-transaction.php";
			include WC_CPW_PLUGIN_PATH . "classes/class-wc-cpw-currencies.php";
			include WC_CPW_PLUGIN_PATH . "classes/cron/class-wc-cpw-payment.php";
			include WC_CPW_PLUGIN_PATH . "classes/cron/class-wc-cpw-hd.php";
			include WC_CPW_PLUGIN_PATH . "classes/class-wc-cpw-gateway.php";
			foreach ( glob( WC_CPW_PLUGIN_PATH . "classes/repos/*.php" ) as $repo_class )
				include $repo_class;
		} );

		add_filter( 'woocommerce_payment_gateways', function ( $methods ) {
			$methods[] = 'WC_CPW_Gateway';
			return $methods;
		} );


		add_filter ('cron_schedules', function ($schedules) {
			$schedules['seconds_300'] = array('interval'=>300, 'display'=>'Each 5 mins[crypto]');
			return $schedules;
		});
		add_action( 'CPW_cron_hook', array( $this, 'do_cron_job' ) );

		add_action( 'wp_ajax_cpw_firstmpkaddress', array( $this, 'first_mpk_address_ajax' ) );

		add_filter( 'plugin_action_links_' . WC_CPW_PLUGIN_BASENAME, array( $this, 'add_action_links' ) );

                add_action( 'admin_notices', array( $this, 'general_admin_notice' ) );

                add_action( 'wp_loaded', array( $this, 'add_email_link' ) );
	}

	public function deactivate() {
		wp_clear_scheduled_hook( 'CPW_cron_hook' );
	}

	public function activate() {
		if (!wp_next_scheduled('CPW_cron_hook')) {
			wp_schedule_event(time(), 'seconds_300', 'CPW_cron_hook');
		}
		self::create_tables();
	}

	private static function create_tables() {
		global $wpdb;
		require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );

		$charset_collate = $wpdb->get_charset_collate();

		$table_name	 = self::$hd_table;
		$sql		 = "CREATE TABLE IF NOT EXISTS `$table_name`
        (
            `id` bigint(12) unsigned NOT NULL AUTO_INCREMENT,
            `mpk` char(150) NOT NULL,
            `mpk_index` bigint(20) NOT NULL DEFAULT '0',
            `address` char(199) NOT NULL,
            `cryptocurrency` char(7) NOT NULL,
            `status` char(24)  NOT NULL DEFAULT 'error',
            `total_received` decimal( 16, 8 ) NOT NULL DEFAULT '0.00000000',
            `last_checked` bigint(20) NOT NULL DEFAULT '0',
            `assigned_at` bigint(20) NOT NULL DEFAULT '0',
            `order_id` bigint(10) NULL,
            `order_amount` decimal(16, 8) NOT NULL DEFAULT '0.00000000',
            `all_order_ids` text NULL,
            `hd_mode` bigint(10) NOT NULL default '0',
            PRIMARY KEY (`id`),
            UNIQUE KEY `hd_address` (`cryptocurrency`, `address`),
            KEY `status` (`status`),
            KEY `mpk_index` (`mpk_index`),
            KEY `mpk` (`mpk`)
        ) $charset_collate;";
		dbDelta( $sql );

		$table_name	 = self::$payment_table;
		$sql		 = "CREATE TABLE IF NOT EXISTS `$table_name`
        (
            `id` bigint(12) unsigned NOT NULL AUTO_INCREMENT,
            `address` char(199) NOT NULL,
            `cryptocurrency` char(7) NOT NULL,
            `status` char(24)  NOT NULL DEFAULT 'error',
            `ordered_at` bigint(20) NOT NULL DEFAULT '0',
            `order_id` bigint(10) NOT NULL DEFAULT '0',
            `order_amount` decimal(32, 18) NOT NULL DEFAULT '0.000000000000000000',
            `tx_hash` char(255) NULL,
            `hd_address` tinyint(4) NOT NULL DEFAULT '0',
            PRIMARY KEY (`id`),
            UNIQUE KEY `unique_payment` (`order_id`, `order_amount`),
            KEY `status` (`status`)
        ) $charset_collate;";
		dbDelta( $sql );

                $table_name	 = self::$transaction_table;
		$sql		 = "CREATE TABLE IF NOT EXISTS `$table_name`
        (
            `currency` char(25)  NOT NULL,
            `tx_hash` char(255) NOT NULL,
            `tx_amount` decimal(32, 18) NOT NULL DEFAULT '0.000000000000000000',
            `created_at` bigint(20) NOT NULL DEFAULT '0',
            `wallet_address` char(199)  NOT NULL,
            PRIMARY KEY (`currency`,`tx_hash`)
        ) $charset_collate;";
		dbDelta( $sql );
	}

	public static function p_enabled() {
		return false;
	}

	function do_cron_job() {
		// Number of clean addresses in the database at all times for faster thank you page load times
		$hdBufferAddressCount = 4;

		// Only look at transactions in the past two hours
		$autoPaymentTransactionLifetimeSec = 3 * 60 * 60;

		$startTime = time();
		WC_CPW_Logger::info( __FILE__, __LINE__, 'Starting Cron Job...' );

		foreach ( WC_CPW_Currencies::get() as $crypto ) {
			$cryptoId = $crypto->get_id();
			if ( $crypto->hd_enabled() ) {
				WC_CPW_Logger::info( __FILE__, __LINE__, 'Starting Hd stuff for: ' . $cryptoId );
				$mpk						 = $crypto->get_mpk();
				$hdMode						 = $crypto->get_hd_mode();
				$hdPercentToVerify			 = $crypto->get_hd_processing_percent();
				$hdRequiredConfirmations	 = $crypto->get_hd_required_confirmations();
				$hdOrderCancellationTimeHr	 = $crypto->get_hd_cancellation_time();
				$hdOrderCancellationTimeSec	 = round( $hdOrderCancellationTimeHr * 60 * 60, 0 );

				WC_CPW_Hd::check_all_pending_addresses_for_payment( $crypto, $mpk, $hdRequiredConfirmations, $hdPercentToVerify, $hdMode );
				WC_CPW_Hd::buffer_ready_addresses( $crypto, $mpk, $hdBufferAddressCount, $hdMode );
				WC_CPW_Hd::cancel_expired_addresses( $crypto, $mpk, $hdOrderCancellationTimeSec, $hdMode );
			}
		}

		WC_CPW_Payment::check_all_addresses_for_matching_payment( $autoPaymentTransactionLifetimeSec );
		WC_CPW_Payment::cancel_expired_payments();

		WC_CPW_Logger::info( __FILE__, __LINE__, 'total time for cron job: ' . ( time() - $startTime) );
	}

	public function first_mpk_address_ajax() {

		if ( !isset( $_POST ) || !is_array( $_POST ) || !array_key_exists( 'mpk', $_POST ) || !array_key_exists( 'cryptoId', $_POST ) ) {
			return;
		}

		$mpk		 = sanitize_text_field( $_POST[ 'mpk' ] );
		$cryptoId	 = sanitize_text_field( $_POST[ 'cryptoId' ] );
		$hdMode		 = sanitize_text_field( $_POST[ 'hdMode' ] );

		$crypto = WC_CPW_Currencies::get_currency( $cryptoId );

		$crypto->set_mpk( $mpk );

		if ( !$crypto->is_valid_mpk() ) {
			return;
		}

		if ( !WC_CPW_Loader::p_enabled() && ($crypto->is_valid_ypub() || $crypto->is_valid_zpub()) ) {
			$message	 = __( 'You have entered a valid Segwit MPK.', 'wc-crypto-payments' );
			$message2	 = '<a href="https://ourwebsite.debug/segwit-page" target="_blank">' . __( 'Segwit MPKs are coming soon!', 'wc-crypto-payments' ) . '</a>';

			echo json_encode( [ $message, $message2, '' ] );
			wp_die();
		} else {
			$firstAddress	 = $crypto->create_hd_address( 0 );
			$secondAddress	 = $crypto->create_hd_address( 1 );
			$thirdAddress	 = $crypto->create_hd_address( 2 );

			echo json_encode( [ $firstAddress, $secondAddress, $thirdAddress ] );

			wp_die();
		}
	}

	public function add_action_links( $links ) {
		$mylinks = array(
			'<a href="admin.php?page=wc-settings&tab=checkout&section=cpw_gateway">' . __( 'Settings', 'wc-crypto-payments' ) . '</a>',
		);

		return array_merge( $mylinks, $links );
	}

        public static function is_math_modules_installed() {
                return extension_loaded( 'GMP' ) || extension_loaded( 'BCMATH' );
        }

        function general_admin_notice() {
                global $pagenow;
                $screen = get_current_screen();
                if ( $screen->id == 'woocommerce_page_wc-settings' AND isset( $_GET[ 'section' ] ) AND $_GET[ 'section' ] == "cpw_gateway" AND!self::is_math_modules_installed() ) {
                        $warning_text = __( "To use HD wallets - you should install GMP or BCMath module for PHP", 'wc-crypto-payments' );
                        echo '<div class="notice notice-warning is-dismissible"><p>' . esc_html($warning_text) . '</p></div>';
                }
        }

        public function add_email_link() {
            if (isset($_GET['tab']) && $_GET['tab'] === 'checkout' && isset($_GET['section']) && $_GET['section'] === 'wc_email_crypto_transaction') {
                wp_redirect(admin_url( 'admin.php?page=wc-settings&tab=email&section=wc_email_crypto_transaction' ));
                exit();
            }
        }

}
