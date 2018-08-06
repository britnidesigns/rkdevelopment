<?php
/*
Plugin Name: Sprout Invoices Add-on - Dynamic Text
Plugin URI: http://docs.sproutapps.co/article/105-dynamic-text-for-line-items
Description: Use dynamic variables within line items to be shown to your clients.
Author: Sprout Apps
Version: 1
ID: 0
Author URI: https://sproutapps.co
Auto Active: true
*/

/**
 * Plugin Info for updates
 */
define( 'SA_ADDON_DYNAMIC_TEXT_VERSION', '1.1' );
define( 'SA_ADDON_DYNAMIC_TEXT_DOWNLOAD_ID', 0 );
define( 'SA_ADDON_DYNAMIC_TEXT_NAME', 'Sprout Invoices Dynamic Text' );
define( 'SA_ADDON_DYNAMIC_TEXT_FILE', __FILE__ );
define( 'SA_ADDON_DYNAMIC_TEXT_PATH', dirname( __FILE__ ) );
define( 'SA_ADDON_DYNAMIC_TEXT_URL', plugins_url( '', __FILE__ ) );

// Load up after SI is loaded.
add_action( 'sprout_invoices_loaded', 'sa_load_dynamic_text_addon' );
function sa_load_dynamic_text_addon() {
	if ( class_exists( 'SI_Dynamic_Text' ) ) {
		return;
	}

	require_once( 'inc/Dynamic_Text.php' );

	SI_Dynamic_Text::init();
}

if ( ! apply_filters( 'is_bundle_addon', false ) ) {
	// Load up the updater after si is completely loaded
	add_action( 'sprout_invoices_loaded', 'sa_load_dynamic_text_updates' );
	function sa_load_dynamic_text_updates() {
		if ( class_exists( 'SI_Updates' ) ) {
			require_once( 'inc/sa-updates/SA_Updates.php' );
		}
	}
}
