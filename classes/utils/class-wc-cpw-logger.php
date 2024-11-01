<?php

if ( !defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

WC_CPW_Logger::init();

class WC_CPW_Logger {

	private static $logger;
	private static $logger_context;

	public static function init() {
		self::$logger			 = function_exists( "wc_get_logger" ) ? wc_get_logger() : false; //new logger in 3.0+
		self::$logger_context	 = array( 'source' => 'crypto-payments' );
	}

	public static function info( $fileName, $lineNumber, $message ) {
		if ( self::$logger )
			self::$logger->info( $fileName . " at line " . $lineNumber . "\n" . $message, self::$logger_context );
	}

}
