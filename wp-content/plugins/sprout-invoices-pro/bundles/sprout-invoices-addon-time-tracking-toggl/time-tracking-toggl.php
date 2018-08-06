<?php
/*
Plugin Name: Sprout Invoices Add-on - Time Tracking w/ Toggl
Plugin URI: https://sproutapps.co/marketplace/time-tracking/
Description: Integrates Toggl with the Time Tracking. Automatically imports time to your SI projects to be later invoiced.
Author: Sprout Apps
ID: 0
Version: 1 Beta
Author URI: https://sproutapps.co
*/

/**
 * Plugin Info for updates
 */
define( 'SA_ADDON_TIME_TRACKING_TOGGL_VERSION', '.9' );
define( 'SA_ADDON_TIME_TRACKING_TOGGL_DOWNLOAD_ID', 1111 );
define( 'SA_ADDON_TIME_TRACKING_TOGGL_NAME', 'Sprout Invoices Time Tracker w/ Toggl' );
define( 'SA_ADDON_TIME_TRACKING_TOGGL_FILE', __FILE__ );
define( 'SA_ADDON_TIME_TRACKING_TOGGL_PATH', dirname( __FILE__ ) );
define( 'SA_ADDON_TIME_TRACKING_TOGGL_URL', plugins_url( '', __FILE__ ) );

if ( ! defined( 'SI_DEV' ) ) {
	define( 'SI_DEV', false );
}

// Load up after SI is loaded.
add_action( 'sprout_invoices_loaded', 'sa_load_time_tracking_toggl_addon', 5 );
function sa_load_time_tracking_toggl_addon() {
	if ( class_exists( 'Time_Tracking_Toggl' ) ) {
		return;
	}

	require_once( 'inc/Toggl_Controller.php' );
	require_once( 'inc/Toggl_Settings.php' );
	require_once( 'inc/Toggl_API.php' );

	Toggl_Controller::init();
		// init sub classes
		Toggl_Settings::init();
		Toggl_API::init();
}

if ( ! apply_filters( 'is_bundle_addon', false ) ) {
	if ( SI_DEV ) { error_log( 'not bundled: sa_load_time_tracking_toggl_updates' ); }
	// Load up the updater after si is completely loaded
	add_action( 'sprout_invoices_loaded', 'sa_load_time_tracking_toggl_updates' );
	function sa_load_time_tracking_toggl_updates() {
		if ( class_exists( 'SI_Updates' ) ) {
			require_once( 'inc/sa-updates/SA_Updates.php' );
		}
	}
}
