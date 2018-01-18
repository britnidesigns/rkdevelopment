<?php
/*
Plugin Name: Sprout Invoices Add-on - Authorize.net Payments
Plugin URI: https://sproutapps.co/marketplace/authorize-payments/
Description: Accept Authorize.net Payments with Sprout Invoices.
Author: Sprout Apps
Version: 1.2
Author URI: https://sproutapps.co
*/

/**
 * Plugin File
 */
define( 'SA_ADDON_AUTHORIZENET_VERSION', '1.2' );
define( 'SA_ADDON_AUTHORIZENET_DOWNLOAD_ID', 6074 );
define( 'SA_ADDON_AUTHORIZENET_FILE', __FILE__ );
define( 'SA_ADDON_AUTHORIZENET_NAME', 'Sprout Invoices Authorize.net Payments' );
define( 'SA_ADDON_AUTHORIZENET_URL', plugins_url( '', __FILE__ ) );


// Load up the processor before updates
add_action( 'si_payment_processors_loaded', 'sa_load_authorizenet' );
function sa_load_authorizenet() {
	require_once( 'SA_AuthorizeNet.php' );
}

// Load up the updater after si is completely loaded
add_action( 'sprout_invoices_loaded', 'sa_load_authorize_updates' );
function sa_load_authorize_updates() {
	if ( class_exists( 'SI_Updates' ) ) {
		require_once( 'inc/sa-updates/SA_Updates.php' );
	}
}
