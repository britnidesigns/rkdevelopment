<?php
/*
Plugin Name: Sprout Invoices Add-on - Shipping Address
Plugin URI: https://sproutapps.co/marketplace/shipping-address/
Description: Allow for clients to set their shipping, and allow them to modify it per invoice.
Author: Sprout Apps
Version: 1.0
Author URI: https://sproutapps.co
*/

/**
 * Plugin Info for updates
 */
define( 'SA_ADDON_SHIPPING_ADDRESS_VERSION', '1.0' );
define( 'SA_ADDON_SHIPPING_ADDRESS_DOWNLOAD_ID', 0 );
define( 'SA_ADDON_SHIPPING_ADDRESS_NAME', 'Sprout Invoices Shipping' );
define( 'SA_ADDON_SHIPPING_ADDRESS_FILE', __FILE__ );
define( 'SA_ADDON_SHIPPING_ADDRESS_PATH', dirname( __FILE__ ) );
define( 'SA_ADDON_SHIPPING_ADDRESS_URL', plugins_url( '', __FILE__ ) );
if ( ! defined( 'SI_DEV' ) ) {
	define( 'SI_DEV', false );
}

// Load up after SI is loaded.
add_action( 'sprout_invoices_loaded', 'sa_load_shipping_address_addon' );
function sa_load_shipping_address_addon() {
	if ( class_exists( 'SI_Shipping_Addy' ) ) {
		return;
	}

	require_once( 'inc/Shipping.php' );
	require_once( 'inc/Shipping_Options.php' );
	require_once( 'inc/Shipping_Edit.php' );
	require_once( 'inc/Shipping_Display.php' );

	SI_Shipping_Addy::init();
	SI_Shipping_Options::init();
	SI_Shipping_Addy_Edit::init();
	SI_Shipping_Addy_Display::init();

}

if ( ! apply_filters( 'is_bundle_addon', false ) ) {
	if ( SI_DEV ) { error_log( 'not bundled: sa_load_shipping_addy_updates' ); }
	// Load up the updater after si is completely loaded
	add_action( 'sprout_invoices_loaded', 'sa_load_shipping_addy_updates' );
	function sa_load_shipping_addy_updates() {
		if ( class_exists( 'SI_Updates' ) ) {
			require_once( 'inc/sa-updates/SA_Updates.php' );
		}
	}
}
