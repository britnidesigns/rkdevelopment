<?php
/*
Plugin Name: Sprout Invoices Add-on - Recurring Invoices
Plugin URI: https://sproutapps.co/marketplace/recurring-invoices/
Description: Allows for invoices to be duplicated on a schedule.
Author: Sprout Apps
ID: 0
Version: 1.0.1
Author URI: https://sproutapps.co
Auto Active: true
*/

/**
 * Plugin Info for updates
 */
define( 'SA_ADDON_RECURRING_INVOICES_VERSION', '1.0.1' );
define( 'SA_ADDON_RECURRING_INVOICES_DOWNLOAD_ID', 1111 );
define( 'SA_ADDON_RECURRING_INVOICES_NAME', 'Sprout Invoices Recurring Invoices' );
define( 'SA_ADDON_RECURRING_INVOICES_FILE', __FILE__ );
define( 'SA_ADDON_RECURRING_INVOICES_PATH', dirname( __FILE__ ) );
define( 'SA_ADDON_RECURRING_INVOICES_URL', plugins_url( '', __FILE__ ) );

// Load up after SI is loaded.
add_action( 'sprout_invoices_loaded', 'sa_load_recurring_invoices_addon' );
function sa_load_recurring_invoices_addon() {
	if ( class_exists( 'SI_Invoices_Recurring' ) ) {
		return;
	}

	require_once( 'inc/Invoices_Recurring.php' );
	require_once( 'inc/Invoices_Recurring_Admin.php' );
	require_once( 'inc/Invoices_Recurring_Settings.php' );
	require_once( 'inc/Invoices_Recurring_Tasks.php' );
	require_once( 'inc/Invoices_Recurring_Addons.php' );
	require_once( 'inc/Invoices_Recurring_Notifications.php' );

	SI_Invoices_Recurring::init();
}

if ( ! apply_filters( 'is_bundle_addon', false ) ) {
	if ( SI_DEV ) { error_log( 'not bundled: sa_load_recurring_invoices_updates' ); }
	// Load up the updater after si is completely loaded
	add_action( 'sprout_invoices_loaded', 'sa_load_recurring_invoices_updates' );
	function sa_load_recurring_invoices_updates() {
		if ( class_exists( 'SI_Updates' ) ) {
			require_once( 'inc/sa-updates/SA_Updates.php' );
		}
	}
}
