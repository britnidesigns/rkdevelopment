<?php
/*
Plugin Name: Sprout Invoices Add-on - BluePay Payments
Plugin URI: https://sproutapps.co/marketplace/bluepay-payments/
Description: Accept Bluepay Payments with Sprout Invoices.
Author: Sprout Apps
Version: 1.1
Author URI: https://sproutapps.co
*/

/**
 * Plugin File
 */
define( 'SA_ADDON_BLUEPAY_VERSION', '1.1' );
define( 'SA_ADDON_BLUEPAY_DOWNLOAD_ID', 6129 );
define( 'SA_ADDON_BLUEPAY_FILE', __FILE__ );
define( 'SA_ADDON_BLUEPAY_NAME', 'Sprout Invoices BluePay Payments' );
define( 'SA_ADDON_BLUEPAY_URL', plugins_url( '', __FILE__ ) );


// Load up the processor before updates
add_action( 'si_payment_processors_loaded', 'sa_load_bluepay' );
function sa_load_bluepay() {
	require_once( 'SA_BluePay.php' );
}

// Load up the updater after si is completely loaded
add_action( 'sprout_invoices_loaded', 'sa_load_bluepay_updates' );
function sa_load_bluepay_updates() {
	if ( class_exists( 'SI_Updates' ) ) {
		require_once( 'inc/sa-updates/SA_Updates.php' );
	}
}