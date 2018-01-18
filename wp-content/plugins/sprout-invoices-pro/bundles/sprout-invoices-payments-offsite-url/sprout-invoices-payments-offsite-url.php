<?php
/*
Plugin Name: Sprout Invoices Payments - URL Redirect
Plugin URI: https://sproutapps.co/marketplace/
Description: Redirect the client to a url to pay
Author: Sprout Apps
Version: 1.0
Author URI: https://sproutapps.co
*/

/**
 * Plugin File
 */
define( 'SA_ADDON_PAYMENTREDIRECT_VERSION', '1.0' );
define( 'SA_ADDON_PAYMENTREDIRECT_DOWNLOAD_ID', 0000 );
define( 'SA_ADDON_PAYMENTREDIRECT_FILE', __FILE__ );
define( 'SA_ADDON_PAYMENTREDIRECT_PATH', dirname( __FILE__ ) );
define( 'SA_ADDON_PAYMENTREDIRECT_NAME', 'Sprout Invoices Payments URL Redirect' );
define( 'SA_ADDON_PAYMENTREDIRECT_URL', plugins_url( '', __FILE__ ) );


// Load up the processor before updates
add_action( 'si_estimate_submission', 'sa_load_url_redirect' );
function sa_load_url_redirect() {
	require_once( 'inc/SA_Payment_Redirect.php' );
}

// Load up the updater after si is completely loaded
add_action( 'sprout_invoices_loaded', 'sa_load_paymentredirect_updates' );
function sa_load_paymentredirect_updates() {
	if ( class_exists( 'SI_Updates' ) ) {
		require_once( 'inc/sa-updates/SA_Updates.php' );
	}
}
