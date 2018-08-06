<?php
/*
Plugin Name: Sprout Invoices Add-on - Client Types
Plugin URI: https://sproutapps.co/marketplace/client-types/
Description: Custom Client types with a numbering tag option.
Author: Sprout Apps
Version: 1.0.4
ID: 108044
Author URI: https://sproutapps.co
*/

/**
 * Plugin Info for updates
 */
define( 'SA_ADDON_CLIENT_TYPE_VERSION', '1.0.4' );
define( 'SA_ADDON_CLIENT_TYPE_DOWNLOAD_ID', 108044 );
define( 'SA_ADDON_CLIENT_TYPE_FILE', __FILE__ );
define( 'SA_ADDON_CLIENT_TYPE_NAME', 'Sprout Invoices Client Types' );
define( 'SA_ADDON_CLIENT_TYPE_URL', plugins_url( '', __FILE__ ) );

// Load up after SI is loaded.
add_action( 'sprout_invoices_loaded', 'sa_load_client_types_addon', 5 );
function sa_load_client_types_addon() {
	// Controller
	require_once( 'inc/SI_Client_Categories.php' );
	require_once( 'inc/SI_Client_Edit.php' );
	require_once( 'inc/SI_Client_Type_Admin.php' );
	require_once( 'inc/SI_Client_Type_Numbering.php' );
	require_once( 'inc/SI_Client_Type_Color.php' );
	// Updates
	if ( class_exists( 'SI_Updates' ) ) {
		require_once( 'inc/sa-updates/SA_Updates.php' );
	}
}
