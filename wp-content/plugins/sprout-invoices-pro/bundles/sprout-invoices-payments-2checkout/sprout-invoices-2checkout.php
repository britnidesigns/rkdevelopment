<?php
/*
Plugin Name: Sprout Invoices Add-on - 2 Checkout Payments
Plugin URI: https://sproutapps.co/marketplace/2checkout-payments/
Description: Accept 2 Checkout Payments with Sprout Invoices.
Author: Sprout Apps
Version: 1.0
Author URI: https://sproutapps.co
*/

/**
 * Plugin File
 */
define( 'SA_ADDON_2CO_VERSION', '1.0' );
define( 'SA_ADDON_2CO_DOWNLOAD_ID', 291673 );
define( 'SA_ADDON_2CO_FILE', __FILE__ );
define( 'SA_ADDON_2CO_NAME', 'Sprout Invoices 2 Checkout Payments' );
define( 'SA_ADDON_2CO_URL', plugins_url( '', __FILE__ ) );


// Load up the processor before updates
add_action( 'si_payment_processors_loaded', 'sa_load_twoco' );
function sa_load_twoco() {
	require_once( 'inc/SA_2CO.php' );
}

// Load up the updater after si is completely loaded
add_action( 'sprout_invoices_loaded', 'sa_load_2checkout_updates' );
function sa_load_2checkout_updates() {
	if ( class_exists( 'SI_Updates' ) ) {
		require_once( 'inc/sa-updates/SA_Updates.php' );
	}
}
