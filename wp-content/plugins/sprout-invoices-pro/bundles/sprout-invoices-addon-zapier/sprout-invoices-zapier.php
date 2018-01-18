<?php
/*
Plugin Name: Sprout Invoices Add-on - Zapier
Plugin URI: https://sproutapps.co/marketplace/time-tracking/
Description: Integrates Zapier with the Sprout Invoices
Author: Sprout Apps
Version: 1
Author URI: https://sproutapps.co
*/

/**
 * Plugin Info for updates
 */
define( 'SA_ADDON_ZAPIER_VERSION', '1' );
define( 'SA_ADDON_ZAPIER_DOWNLOAD_ID', 1111 );
define( 'SA_ADDON_ZAPIER_NAME', 'Sprout Invoices Zapier' );
define( 'SA_ADDON_ZAPIER_FILE', __FILE__ );
define( 'SA_ADDON_ZAPIER_PATH', dirname( __FILE__ ) );
define( 'SA_ADDON_ZAPIER_URL', plugins_url( '', __FILE__ ) );

if ( ! defined( 'SI_DEV' ) ) {
	define( 'SI_DEV', false );
}

// Load up after SI is loaded.
add_action( 'sprout_invoices_loaded', 'sa_load_si_zapier_addon' );
function sa_load_si_zapier_addon() {
	if ( class_exists( 'Zapier_Controller' ) ) {
		return;
	}

	require_once( 'inc/Zapier_Controller.php' );
	require_once( 'inc/Zapier_Settings.php' );
	require_once( 'inc/Zapier_API.php' );
	require_once( 'inc/Zapier_Routes.php' );

	Zapier_Controller::init();
		// init sub classes
		Zapier_Settings::init();
		Zapier_Routes::init();
}

if ( ! apply_filters( 'is_bundle_addon', false ) ) {
	if ( SI_DEV ) { error_log( 'not bundled: sa_load_si_zapier_updates' ); }
	// Load up the updater after si is completely loaded
	add_action( 'sprout_invoices_loaded', 'sa_load_si_zapier_updates' );
	function sa_load_si_zapier_updates() {
		if ( class_exists( 'SI_Updates' ) ) {
			require_once( 'inc/sa-updates/SA_Updates.php' );
		}
	}
}
