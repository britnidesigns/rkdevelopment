<?php
/*
Plugin Name: Sprout Invoices Add-on - Departments
Plugin URI: https://sproutapps.co/marketplace/predefined-departments/
Description: Add department selections to invoices and estimates
Author: Sprout Apps
Version: 1.1
Author URI: https://sproutapps.co
*/

/**
 * Plugin Info for updates
 */
define( 'SA_ADDON_DEPARTMENTS_VERSION', '1.1' );
define( 'SA_ADDON_DEPARTMENTS_DOWNLOAD_ID', 44585 );
define( 'SA_ADDON_DEPARTMENTS_NAME', 'Sprout Invoices Pre-defined Departments' );
define( 'SA_ADDON_DEPARTMENTS_FILE', __FILE__ );
define( 'SA_ADDON_DEPARTMENTS_PATH', dirname( __FILE__ ) );
define( 'SA_ADDON_DEPARTMENTS_URL', plugins_url( '', __FILE__ ) );

if ( ! defined( 'SI_DEV' ) ) {
	define( 'SI_DEV', false );
}

// Load up after SI is loaded.
add_action( 'sprout_invoices_loaded', 'sa_load_invoicing_departments_addon' );
function sa_load_invoicing_departments_addon() {
	if ( class_exists( 'SI_Department' ) ) {
		return;
	}

	require_once( 'inc/Department.php' );
	require_once( 'inc/Invoice_Departments.php' );

	SI_Department::init();
	Invoice_Departments::init();
}

if ( ! apply_filters( 'is_bundle_addon', false ) ) {
	if ( SI_DEV ) { error_log( 'not bundled: sa_load_invoicing_departments_updates' ); }
	// Load up the updater after si is completely loaded
	add_action( 'sprout_invoices_loaded', 'sa_load_invoicing_departments_updates' );
	function sa_load_invoicing_departments_updates() {
		if ( class_exists( 'SI_Updates' ) ) {
			require_once( 'inc/sa-updates/SA_Updates.php' );
		}
	}
}
