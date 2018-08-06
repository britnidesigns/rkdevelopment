<?php
/*
Plugin Name: Sprout Invoices Add-on - Point of Contact for Clients
Plugin URI: https://sproutapps.co/marketplace/
Description: Select a single associated user as the point of contact for all automated notifications.
Author: Sprout Apps
ID: 564648
Version: 1.0
Author URI: https://sproutapps.co
*/

/**
 * Plugin Info for updates
 */
define( 'SA_ADDON_CLIENT_POC_VERSION', '1.0' );
define( 'SA_ADDON_CLIENT_POC_DOWNLOAD_ID', 564648 );
define( 'SA_ADDON_CLIENT_POC_FILE', __FILE__ );
define( 'SA_ADDON_CLIENT_POC_NAME', 'Sprout Invoices Point of Contact' );
define( 'SA_ADDON_CLIENT_POC_URL', plugins_url( '', __FILE__ ) );
define( 'SA_ADDON_CLIENT_POC_PATH', dirname( __FILE__ ) );

if ( ! function_exists( 'sa_load_point_of_contact_addon' ) ) {

	// Load up after SI is loaded.
	add_action( 'sprout_invoices_loaded', 'sa_load_point_of_contact_addon' );
	function sa_load_point_of_contact_addon() {
		// Controller
		require_once( 'inc/SI_Point_of_Contact.php' );
		// Updates
		if ( class_exists( 'SI_Updates' ) ) {
			require_once( 'inc/sa-updates/SA_Updates.php' );
		}
	}
}
