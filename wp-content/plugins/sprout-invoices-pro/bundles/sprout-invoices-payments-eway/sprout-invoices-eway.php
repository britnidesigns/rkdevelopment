<?php
/*
Plugin Name: Sprout Invoices Add-on - eWay iFrame Payments
Plugin URI: https://sproutapps.co/marketplace/authorize-payments/
Description: Accept Authorize.net Payments with Sprout Invoices.
Author: Sprout Apps
Version: 1.0
Author URI: https://sproutapps.co
*/

/**
 * Plugin File
 */
define( 'SA_ADDON_EWAY_VERSION', '1.0' );
define( 'SA_ADDON_EWAY_DOWNLOAD_ID', 263156 );
define( 'SA_ADDON_EWAY_FILE', __FILE__ );
define( 'SA_ADDON_EWAY_NAME', 'Sprout Invoices Authorize.net Payments' );
define( 'SA_ADDON_EWAY_URL', plugins_url( '', __FILE__ ) );


// Load up the processor before updates
add_action( 'si_payment_processors_loaded', 'sa_load_eway' );
function sa_load_eway() {
	require_once( 'inc/SA_eWay.php' );
}

// Load up the updater after si is completely loaded
add_action( 'sprout_invoices_loaded', 'sa_load_eway_updates' );
function sa_load_eway_updates() {
	if ( class_exists( 'SI_Updates' ) ) {
		require_once( 'inc/sa-updates/SA_Updates.php' );
	}
}
