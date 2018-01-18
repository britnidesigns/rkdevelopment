<?php
/*
Plugin Name: Sprout Invoices Add-on - Pre-defined Items
Plugin URI: https://sproutapps.co/marketplace/pre-defined-line-items/
Description: Add pre-defined tasks and products to your items list
Author: Sprout Apps
Version: 2.0
Author URI: https://sproutapps.co
Auto Active: true
*/

/**
 * Plugin Info for updates
 */
define( 'SA_ADDON_PREDEFINED_ITEMS_VERSION', '2.0' );
define( 'SA_ADDON_PREDEFINED_ITEMS_DOWNLOAD_ID', 14782 );
define( 'SA_ADDON_PREDEFINED_ITEMS_NAME', 'Sprout Invoices Pre-defined Items' );
define( 'SA_ADDON_PREDEFINED_ITEMS_FILE', __FILE__ );
define( 'SA_ADDON_PREDEFINED_ITEMS_PATH', dirname( __FILE__ ) );
define( 'SA_ADDON_PREDEFINED_ITEMS_URL', plugins_url( '', __FILE__ ) );

if ( ! defined( 'SI_DEV' ) ) {
	define( 'SI_DEV', false );
}

// Load up after SI is loaded.
add_action( 'sprout_invoices_loaded', 'sa_load_predefined_items_addon' );
function sa_load_predefined_items_addon() {
	if ( class_exists( 'SI_Item' ) ) {
		return;
	}

	require_once( 'inc/Item.php' );
	require_once( 'inc/Predefined_Items.php' );

	SI_Item::init();
	Predefined_Items::init();
}

if ( ! apply_filters( 'is_bundle_addon', false ) ) {
	if ( SI_DEV ) { error_log( 'not bundled: sa_load_predefined_items_updates' ); }
	// Load up the updater after si is completely loaded
	add_action( 'sprout_invoices_loaded', 'sa_load_predefined_items_updates' );
	function sa_load_predefined_items_updates() {
		if ( class_exists( 'SI_Updates' ) ) {
			require_once( 'inc/sa-updates/SA_Updates.php' );
		}
	}
}
