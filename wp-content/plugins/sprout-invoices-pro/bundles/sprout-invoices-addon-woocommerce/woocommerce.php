<?php
/*
Plugin Name: Sprout Invoices Add-on - WooCommerce Tools
Plugin URI: https://sproutapps.co/sprout-invoices/woocommerce/
Description: Ability to have your clients pay via Woocommerce checkout, Sprout Invoices payment option for WooCommerce checkout, add WooCommerce products to your items list, and many more tools for Sprout Invoices and WooCommerce.
Author: Sprout Apps
ID: 1
Version: 1.0
Author URI: https://sproutapps.co
*/

/**
 * Plugin Info for updates
 */
define( 'SA_ADDON_WOOCOMMERCE_VERSION', '1.0' );
define( 'SA_ADDON_WOOCOMMERCE_DOWNLOAD_ID', 1 );
define( 'SA_ADDON_WOOCOMMERCE_NAME', 'Sprout Invoices WooCommerce Products' );
define( 'SA_ADDON_WOOCOMMERCE_FILE', __FILE__ );
define( 'SA_ADDON_WOOCOMMERCE_PATH', dirname( __FILE__ ) );
define( 'SA_ADDON_WOOCOMMERCE_URL', plugins_url( '', __FILE__ ) );

if ( ! defined( 'SI_DEV' ) ) {
	define( 'SI_DEV', false );
}

// Load up after SI is loaded.
add_action( 'sprout_invoices_loaded', 'sa_load_woocommerce_tools' );
function sa_load_woocommerce_tools() {
	if ( ! version_compare( phpversion(), '5.6', '>=' ) ) {
		add_action( 'admin_head', 'si_woo_compatibility_check_fail_notices' );
		return;
	}
	if ( ! class_exists( 'WC_Product' ) ) {
		return;
	}

	require_once( 'inc/Woo_Product_Line_Items.php' );
	Woo_Product_Line_Items::init();

	require_once( 'inc/Woo_Invoice_Payments_Integration.php' );
	require_once( 'inc/Woo_Invoice_Payments.php' );
	Woo_Payments_Integration::init();

	require_once( 'template-tags/vat.php' );
	require_once( 'inc/Woo_Tools.php' );
	Woo_Tools::init();
}

function si_woo_compatibility_check_fail_notices() {
	if ( ! version_compare( phpversion(), '5.6', '>=' ) ) {
		printf( '<div class="error"><p><strong>Sprout Invoices WooCommerce Tools</strong> requires PHP version %s or higher to be installed on your server. Talk to your web host about using a secure version of PHP.</p></div>', 5.6 );
	}
}

if ( ! apply_filters( 'is_bundle_addon', false ) ) {
	if ( SI_DEV ) { error_log( 'not bundled: sa_load_woocommerce_updates' ); }
	// Load up the updater after si is completely loaded
	add_action( 'sprout_invoices_loaded', 'sa_load_woocommerce_updates' );
	function sa_load_woocommerce_updates() {
		if ( class_exists( 'SI_Updates' ) ) {
			require_once( 'inc/sa-updates/SA_Updates.php' );
		}
	}
}
