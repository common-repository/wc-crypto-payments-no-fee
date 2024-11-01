<?php
/**
 * Plugin Name: WC Crypto Payments (no fee)
 * Plugin URI:
 * Description: Accept crypto payments on your WooCommerce store with zero fees
 * Version: 1.0.1
 * Author: AlgolPlus
 * Author URI: https://algolplus.com/
 * WC requires at least: 5.0
 * WC tested up to: 5.9
 *
 * Text Domain: wc-crypto-payments
 * Domain Path: /languages
 */
if ( !defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

//Stop if another version is active!
if ( defined( 'WC_CPW_PLUGIN_FILE' ) ) {
	add_action( 'admin_notices', function () {
		?>
		<div class="notice notice-warning is-dismissible">
			<p><?php
                                esc_html_e( 'Please, <a href="plugins.php">deactivate</a> Free version of WC Crypto Payments!',
		'wc-crypto-payments' );
				?></p>
		</div>
		<?php
	} );

	return;
}

//main constants
define( 'WC_CPW_PLUGIN_FILE', basename( __FILE__ ) );
define( 'WC_CPW_PLUGIN_PATH', plugin_dir_path( __FILE__ ) );
define( 'WC_CPW_PLUGIN_URL', plugins_url( '', __FILE__ ) );
define( 'WC_CPW_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );
define( 'WC_CPW_MIN_PHP_VERSION', '7.0.0' );
define( 'WC_CPW_MIN_WC_VERSION', '5.0' );
define( 'WC_CPW_VERSION', '1.0.1' );

load_plugin_textdomain( 'wc-crypto-payments', false, WC_CPW_PLUGIN_PATH . 'languages' );

include 'classes/class-wc-cpw-loader.php';
$WC_CPW_Loader = new WC_CPW_Loader();

$extension_file = WC_CPW_PLUGIN_PATH . 'pro_version/classes/class-wc-cpw-loader-pro.php';
if ( file_exists( $extension_file ) ) {
	include_once $extension_file;
}

register_activation_hook( __FILE__, array( $WC_CPW_Loader, 'activate' ) );
register_deactivation_hook( __FILE__, array( $WC_CPW_Loader, 'deactivate' ) );
