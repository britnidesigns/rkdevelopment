<?php
/*
Plugin Name: Sprout Invoices Add-on - Advanced ID Generation for Invoices and Estimates
Plugin URI: https://sproutapps.co/marketplace/advanced-id-generation/
Description: ID generation options for estimates and invoices.
Author: Sprout Apps
Version: 1.7
Author URI: https://sproutapps.co
Auto Active: true
*/

/**
 * Plugin Info for updates
 */
define( 'SA_ADDON_ID_GENERATION_VERSION', '1.7' );
define( 'SA_ADDON_ID_GENERATION_DOWNLOAD_ID', 108035 );
define( 'SA_ADDON_ID_GENERATION_FILE', __FILE__ );
define( 'SA_ADDON_ID_GENERATION_NAME', 'Sprout Invoices ID Generation' );
define( 'SA_ADDON_ID_GENERATION_URL', plugins_url( '', __FILE__ ) );

if ( ! function_exists( 'sa_load_id_generation_addon' ) ) {

	// Load up after SI is loaded.
	add_action( 'sprout_invoices_loaded', 'sa_load_id_generation_addon' );
	function sa_load_id_generation_addon() {
		// Controller
		require_once( 'inc/SI_Advanced_Id_Generation.php' );
		// Updates
		if ( class_exists( 'SI_Updates' ) ) {
			require_once( 'inc/sa-updates/SA_Updates.php' );
		}
	}
}
