<?php
/*
Plugin Name: Sprout Invoices Add-on - Client Set Payment Amounts
Plugin URI: https://sproutapps.co/marketplace/predefined-client_pricing/
Description: Allows the client to set the payment amount.
Author: Sprout Apps
Version: 1.2
Author URI: https://sproutapps.co
*/

/**
 * Plugin Info for updates
 */
define( 'SA_ADDON_CLIENT_PRICE_VERSION', '1.2' );
define( 'SA_ADDON_CLIENT_PRICE_DOWNLOAD_ID', 41265 );
define( 'SA_ADDON_CLIENT_PRICE_NAME', 'Sprout Invoices Client Set Pricing' );
define( 'SA_ADDON_CLIENT_PRICE_FILE', __FILE__ );
define( 'SA_ADDON_CLIENT_PRICE_PATH', dirname( __FILE__ ) );
define( 'SA_ADDON_CLIENT_PRICE_URL', plugins_url( '', __FILE__ ) );

if ( ! defined( 'SI_DEV' ) ) {
	define( 'SI_DEV', false );
}

if ( ! function_exists( 'sa_load_invoicing_client_pricing_addon' ) ) {

	// Load up after SI is loaded.
	add_action( 'sprout_invoices_loaded', 'sa_load_invoicing_client_pricing_addon' );
	function sa_load_invoicing_client_pricing_addon() {
		if ( class_exists( 'Client_Set_Price' ) ) {
			return;
		}

		require_once( 'inc/Client_Set_Price.php' );
		Client_Set_Price::init();
	}

	if ( ! apply_filters( 'is_bundle_addon', false ) ) {
		if ( SI_DEV ) { error_log( 'not bundled: sa_load_invoicing_client_pricing_updates' ); }
		// Load up the updater after si is completely loaded
		add_action( 'sprout_invoices_loaded', 'sa_load_invoicing_client_pricing_updates' );
		function sa_load_invoicing_client_pricing_updates() {
			if ( class_exists( 'SI_Updates' ) ) {
				require_once( 'inc/sa-updates/SA_Updates.php' );
			}
		}
	}
}
