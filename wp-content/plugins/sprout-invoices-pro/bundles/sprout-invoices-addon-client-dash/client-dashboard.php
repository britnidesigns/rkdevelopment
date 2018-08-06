<?php
/*
Plugin Name: Sprout Invoices Add-on - Client Dashboard
Plugin URI: https://sproutapps.co/marketplace/client-dashboard/
Description: Allows for a client to login and view their invoices, estimates and payments.
Author: Sprout Apps
Version: 1.1
ID: 10589
Author URI: https://sproutapps.co
Auto Active: true
*/

/**
 * Plugin Info for updates
 */
define( 'SA_ADDON_CLIENT_DASH_VERSION', '1.1' );
define( 'SA_ADDON_CLIENT_DASH_DOWNLOAD_ID', 10589 );
define( 'SA_ADDON_CLIENT_DASH_NAME', 'Sprout Invoices Client Dashboard' );
define( 'SA_ADDON_CLIENT_DASH_FILE', __FILE__ );
define( 'SA_ADDON_CLIENT_DASH_PATH', dirname( __FILE__ ) );
define( 'SA_ADDON_CLIENT_DASH_URL', plugins_url( '', __FILE__ ) );

// Load up after SI is loaded.
add_action( 'sprout_invoices_loaded', 'sa_load_client_dash_addon' );
function sa_load_client_dash_addon() {
	if ( class_exists( 'SI_Client_Dashboard' ) ) {
		return;
	}

	require_once( 'inc/Client_Dashboard.php' );
	require_once( 'inc/Client_Dashboard_Notifications.php' );

	SI_Client_Dashboard::init();
	Client_Dashboard_Notifications::init();
}

if ( ! apply_filters( 'is_bundle_addon', false ) ) {
	// Load up the updater after si is completely loaded
	add_action( 'sprout_invoices_loaded', 'sa_load_client_dash_updates' );
	function sa_load_client_dash_updates() {
		if ( class_exists( 'SI_Updates' ) ) {
			require_once( 'inc/sa-updates/SA_Updates.php' );
		}
	}
}
