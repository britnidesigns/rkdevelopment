<?php
/*
Plugin Name: Sprout Invoices Add-on - Estimate Acceptance Actions
Plugin URI: https://sproutapps.co/marketplace/time-tracking/
Description: When an estimate is accepted there are a few things that can be done to the newly created invoice.
Author: Sprout Apps
Version: 1.0.4
ID: 48438
Author URI: https://sproutapps.co
Auto Active: true
*/

/**
 * Plugin Info for updates
 */
define( 'SA_ADDON_ESTIMATE_ACCEPTANCE_VERSION', '1.0.4' );
define( 'SA_ADDON_ESTIMATE_ACCEPTANCE_DOWNLOAD_ID', 48438 );
define( 'SA_ADDON_ESTIMATE_ACCEPTANCE_NAME', 'Sprout Invoices Estimates Action after Acceptance' );
define( 'SA_ADDON_ESTIMATE_ACCEPTANCE_FILE', __FILE__ );
define( 'SA_ADDON_ESTIMATE_ACCEPTANCE_PATH', dirname( __FILE__ ) );
define( 'SA_ADDON_ESTIMATE_ACCEPTANCE_URL', plugins_url( '', __FILE__ ) );
if ( ! defined( 'SI_DEV' ) ) {
	define( 'SI_DEV', false );
}

if ( ! function_exists( 'sa_load_estimate_acceptance_addon' ) ) {

	// Load up after SI is loaded.
	add_action( 'sprout_invoices_loaded', 'sa_load_estimate_acceptance_addon' );
	function sa_load_estimate_acceptance_addon() {
		if ( class_exists( 'Estimate_Acceptance' ) ) {
			return;
		}

		require_once( 'inc/Estimate_Acceptance.php' );
		SI_Estimate_Acceptance::init();
	}

	if ( ! apply_filters( 'is_bundle_addon', false ) ) {
		if ( SI_DEV ) { error_log( 'not bundled: sa_load_estimate_acceptance_updates' ); }
		// Load up the updater after si is completely loaded
		add_action( 'sprout_invoices_loaded', 'sa_load_estimate_acceptance_updates' );
		function sa_load_estimate_acceptance_updates() {
			if ( class_exists( 'SI_Updates' ) ) {
				require_once( 'inc/sa-updates/SA_Updates.php' );
			}
		}
	}
}
