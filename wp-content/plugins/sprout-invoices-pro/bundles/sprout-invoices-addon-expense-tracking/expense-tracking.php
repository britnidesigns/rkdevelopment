<?php
/*
Plugin Name: Sprout Invoices Add-on - Expense Tracking
Plugin URI: https://sproutapps.co/marketplace/expense-tracking/
Description: Adds the ability to track expense on projects and then import expenses to invoices.
Author: Sprout Apps
ID: 413528
Version: 1.0.2
Author URI: https://sproutapps.co
*/

/**
 * Plugin Info for updates
 */
define( 'SA_ADDON_EXPENSE_TRACKING_VERSION', '1.0.2' );
define( 'SA_ADDON_EXPENSE_TRACKING_DOWNLOAD_ID', 413528 );
define( 'SA_ADDON_EXPENSE_TRACKING_NAME', 'Sprout Invoices Expense Tracker' );
define( 'SA_ADDON_EXPENSE_TRACKING_FILE', __FILE__ );
define( 'SA_ADDON_EXPENSE_TRACKING_PATH', dirname( __FILE__ ) );
define( 'SA_ADDON_EXPENSE_TRACKING_URL', plugins_url( '', __FILE__ ) );

if ( ! defined( 'SI_DEV' ) ) {
	define( 'SI_DEV', false );
}

if ( ! function_exists( 'sa_load_expense_tracking_addon' ) ) {

	// Load up after SI is loaded.
	add_action( 'sprout_invoices_loaded', 'sa_load_expense_tracking_addon' );
	function sa_load_expense_tracking_addon() {
		if ( class_exists( 'SI_Expense' ) ) {
			return;
		}

		require_once( 'inc/Expense.php' );
		require_once( 'inc/Expense_Tracking.php' );
		require_once( 'template-tags/expenses.php' );

		SI_Expense::init();
		SI_Expense_Tracking_Premium::init();
	}

	if ( ! apply_filters( 'is_bundle_addon', false ) ) {
		if ( SI_DEV ) { error_log( 'not bundled: sa_load_expense_tracking_updates' ); }
		// Load up the updater after si is completely loaded
		add_action( 'sprout_invoices_loaded', 'sa_load_expense_tracking_updates' );
		function sa_load_expense_tracking_updates() {
			if ( class_exists( 'SI_Updates' ) ) {
				require_once( 'inc/sa-updates/SA_Updates.php' );
			}
		}
	}
}
