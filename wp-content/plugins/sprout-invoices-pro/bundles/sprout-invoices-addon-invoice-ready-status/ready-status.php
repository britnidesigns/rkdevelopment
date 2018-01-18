<?php
/*
Plugin Name: Sprout Invoices Add-on - Ready Status
Plugin URI: https://sproutapps.co/marketplace/service-fee/
Description: Ability to easily mark an invoice “ready for payment”, which will send a notification to all clients
Author: Sprout Apps
Version: 1.0
Author URI: https://sproutapps.co
*/

/**
 * Plugin Info for updates
 */
define( 'SA_ADDON_READY_STATUS_VERSION', '1.0' );
define( 'SA_ADDON_READY_STATUS_DOWNLOAD_ID', 0 );
define( 'SA_ADDON_READY_STATUS_NAME', 'Sprout Invoices Ready Status' );
define( 'SA_ADDON_READY_STATUS_FILE', __FILE__ );
define( 'SA_ADDON_READY_STATUS_PATH', dirname( __FILE__ ) );
define( 'SA_ADDON_READY_STATUS_URL', plugins_url( '', __FILE__ ) );
if ( ! defined( 'SI_DEV' ) ) {
	define( 'SI_DEV', false );
}

// Load up after SI is loaded.
add_action( 'sprout_invoices_loaded', 'sa_load_ready_status_addon' );
function sa_load_ready_status_addon() {
	if ( class_exists( 'SI_Ready_Status' ) ) {
		return;
	}

	require_once( 'inc/Ready_Status.php' );
	require_once( 'inc/Ready_Status_Notification.php' );

	SI_Ready_Status::init();
	SI_Ready_Status_Notification::init();

}

if ( ! apply_filters( 'is_bundle_addon', false ) ) {
	if ( SI_DEV ) { error_log( 'not bundled: sa_load_ready_status_updates' ); }
	// Load up the updater after si is completely loaded
	add_action( 'sprout_invoices_loaded', 'sa_load_ready_status_updates' );
	function sa_load_ready_status_updates() {
		if ( class_exists( 'SI_Updates' ) ) {
			require_once( 'inc/sa-updates/SA_Updates.php' );
		}
	}
}
