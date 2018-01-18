<?php
/*
Plugin Name: Sprout Invoices Add-on - Square Cash Payments
Plugin URI: https://sproutapps.co/marketplace/square-cash/
Description: Accept Square Cash Payments with Sprout Invoices.
Author: Sprout Apps
Version: 1.0.1
Author URI: https://sproutapps.co
*/

/**
 * Plugin File
 */
define( 'SA_ADDON_SQUARECASH_VERSION', '1.0.1' );
define( 'SA_ADDON_SQUARECASH_DOWNLOAD_ID', 565307 );
define( 'SA_ADDON_SQUARECASH_FILE', __FILE__ );
define( 'SA_ADDON_SQUARECASH_PATH', dirname( __FILE__ ) );
define( 'SA_ADDON_SQUARECASH_NAME', 'Sprout Invoices Square Cash Payments' );
define( 'SA_ADDON_SQUARECASH_URL', plugins_url( '', __FILE__ ) );


// Load up the processor before updates
add_action( 'si_estimate_submission', 'sa_load_squarecash' );
function sa_load_squarecash() {
	require_once( 'inc/SA_Square_Cash.php' );
}

// Load up the updater after si is completely loaded
add_action( 'sprout_invoices_loaded', 'sa_load_squarecash_updates' );
function sa_load_squarecash_updates() {
	if ( class_exists( 'SI_Updates' ) ) {
		require_once( 'inc/sa-updates/SA_Updates.php' );
	}
}
