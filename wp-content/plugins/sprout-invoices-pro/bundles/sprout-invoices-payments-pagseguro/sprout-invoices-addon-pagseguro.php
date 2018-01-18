<?php
/*
Plugin Name: Sprout Invoices Add-on - PagSeguro Payments
Plugin URI: https://sproutapps.co/marketplace/pagseguro-payments/
Description: Accept PagSeguro Payments with Sprout Invoices.
Author: Sprout Apps
Version: 1.0
Author URI: https://sproutapps.co
*/

/**
 * Plugin File
 */
define( 'SA_ADDON_PAGSEGURO_VERSION', '1.0' );
define( 'SA_ADDON_PAGSEGURO_DOWNLOAD_ID', 9385 );
define( 'SA_ADDON_PAGSEGURO_FILE', __FILE__ );
define( 'SA_ADDON_PAGSEGURO_NAME', 'Sprout Invoices PagSeguro Payments' );
define( 'SA_ADDON_PAGSEGURO_URL', plugins_url( '', __FILE__ ) );


// Load up the processor before updates
add_action( 'si_payment_processors_loaded', 'sa_load_pagseguro' );
function sa_load_pagseguro() {
	require_once( 'PagSeguro.php' );
}

add_action( 'sprout_invoices_loaded', 'sa_load_pagseguro_updates' );
function sa_load_pagseguro_updates() {
	if ( class_exists( 'SI_Updates' ) ) {
		require_once( 'inc/sa-updates/SA_Updates.php' );
	}
}