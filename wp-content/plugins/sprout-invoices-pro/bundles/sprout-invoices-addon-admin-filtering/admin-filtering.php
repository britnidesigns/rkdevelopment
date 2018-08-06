<?php
/*
Plugin Name: Sprout Invoices Add-on - Estimate & Invoice Filtering
Plugin URI: https://sproutapps.co/marketplace/time-tracking/
Description: Allows for the estimate and invoice admins to be filtered and adds some bulk actions
Author: Sprout Apps
Version: 1
ID: 0
Author URI: https://sproutapps.co
Auto Active: true
*/

/**
 * Plugin Info for updates
 */
define( 'SA_ADDON_ADMIN_BULK_FILTERING_VERSION', '1' );
define( 'SA_ADDON_ADMIN_BULK_FILTERING_DOWNLOAD_ID', 0 );
define( 'SA_ADDON_ADMIN_BULK_FILTERING_NAME', 'Sprout Invoices Bulk Actions and Filtering' );
define( 'SA_ADDON_ADMIN_BULK_FILTERING_FILE', __FILE__ );
define( 'SA_ADDON_ADMIN_BULK_FILTERING_PATH', dirname( __FILE__ ) );
define( 'SA_ADDON_ADMIN_BULK_FILTERING_URL', plugins_url( '', __FILE__ ) );
if ( ! defined( 'SI_DEV' ) ) {
	define( 'SI_DEV', false );
}

// Load up after SI is loaded.
add_action( 'sprout_invoices_loaded', 'sa_load_admin_bulk_filtering_addon' );
function sa_load_admin_bulk_filtering_addon() {
	if ( class_exists( 'Admin_Filtering' ) ) {
		return;
	}

	require_once( 'inc/Admin_Filtering.php' );
	SI_Admin_Filtering::init();
}

if ( ! apply_filters( 'is_bundle_addon', false ) ) {
	if ( SI_DEV ) { error_log( 'not bundled: sa_load_admin_bulk_filtering_updates' ); }
	// Load up the updater after si is completely loaded
	add_action( 'sprout_invoices_loaded', 'sa_load_admin_bulk_filtering_updates' );
	function sa_load_admin_bulk_filtering_updates() {
		if ( class_exists( 'SI_Updates' ) ) {
			require_once( 'inc/sa-updates/SA_Updates.php' );
		}
	}
}
