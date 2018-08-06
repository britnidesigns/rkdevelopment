<?php
/*
Plugin Name: Sprout Invoices Add-on - Embeds
Plugin URI: http://docs.sproutapps.co/article/104-invoice-and-estimate-shortcode-embeds
Description: Embed an invoice/estimate view into any WordPress page. Button to duplicate an invoice/estimate with a single click.
Author: Sprout Apps
Version: 1
ID: 0
Author URI: https://sproutapps.co
*/

/**
 * Plugin Info for updates
 */
define( 'SA_ADDON_EMBEDS_VERSION', '1.0' );
define( 'SA_ADDON_EMBEDS_DOWNLOAD_ID', 0 );
define( 'SA_ADDON_EMBEDS_NAME', 'Sprout Invoices Embeds' );
define( 'SA_ADDON_EMBEDS_FILE', __FILE__ );
define( 'SA_ADDON_EMBEDS_PATH', dirname( __FILE__ ) );
define( 'SA_ADDON_EMBEDS_URL', plugins_url( '', __FILE__ ) );

// Load up after SI is loaded.
add_action( 'sprout_invoices_loaded', 'sa_load_embeds_addon' );
function sa_load_embeds_addon() {
	if ( class_exists( 'SI_Embeds' ) ) {
		return;
	}

	require_once( 'inc/Embeds.php' );
	SI_Embeds::init();
}

if ( ! apply_filters( 'is_bundle_addon', false ) ) {
	// Load up the updater after si is completely loaded
	add_action( 'sprout_invoices_loaded', 'sa_load_dynamic_text_updates' );
	function sa_load_dynamic_text_updates() {
		if ( class_exists( 'SI_Updates' ) ) {
			require_once( 'inc/sa-updates/SA_Updates.php' );
			SI_Embeds_Updates::init();
		}
	}
}
