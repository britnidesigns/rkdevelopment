<?php
/*
Plugin Name: Sprout Invoices Add-on - Braintree Payments
Plugin URI: https://sproutapps.co/marketplace/braintree-payments/
Description: Accept Braintree Payments with Sprout Invoices.
Author: Sprout Apps
Version: 1
Author URI: https://sproutapps.co
*/

/**
 * Plugin File
 */
define( 'SA_ADDON_BRAINTREE_VERSION', '1' );
define( 'SA_ADDON_BRAINTREE_DOWNLOAD_ID', 291649 );
define( 'SA_ADDON_BRAINTREE_FILE', __FILE__ );
define( 'SA_ADDON_BRAINTREE_PATH', dirname( __FILE__ ) );
define( 'SA_ADDON_BRAINTREE_NAME', 'Sprout Invoices Braintree Payments' );
define( 'SA_ADDON_BRAINTREE_URL', plugins_url( '', __FILE__ ) );


// Load up the processor before updates
add_action( 'si_payment_processors_loaded', 'sa_load_braintree' );
function sa_load_braintree() {
	require_once( 'inc/SA_Braintree.php' );
}

// Load up the updater after si is completely loaded
add_action( 'sprout_invoices_loaded', 'sa_load_braintree_updates' );
function sa_load_braintree_updates() {
	if ( class_exists( 'SI_Updates' ) ) {
		require_once( 'inc/sa-updates/SA_Updates.php' );
	}
}
