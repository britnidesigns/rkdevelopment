<?php
/*
Plugin Name: Sprout Invoices Add-on - Mercadopago
Plugin URI: https://sproutapps.co/marketplace/mercadopago-invoice-payments/
Description: Accept Mercadopago Payments with Sprout Invoices.
Author: Sprout Apps
Version: 1
Author URI: https://sproutapps.co
*/

/**
 * Plugin File
 */
define( 'SA_ADDON_MERCADOPAGO_VERSION', '1' );
define( 'SA_ADDON_MERCADOPAGO_DOWNLOAD_ID', 47863 );
define( 'SA_ADDON_MERCADOPAGO_FILE', __FILE__ );
define( 'SA_ADDON_MERCADOPAGO_NAME', 'Sprout Invoices Paypal EC Payments' );
define( 'SA_ADDON_MERCADOPAGO_URL', plugins_url( '', __FILE__ ) );


// Load up the processor before updates
add_action( 'si_payment_processors_loaded', 'sa_load_mercadopago' );
function sa_load_mercadopago() {
	if ( ! class_exists( 'SI_Mercadopago' ) ) {
		require_once( 'SI_Mercadopago.php' );
	}
	else {
		// deactivate plugin if the pro version is installed.
		require_once ABSPATH.'/wp-admin/includes/plugin.php';
		deactivate_plugins( __FILE__ );
	}
}

// Load up the updater after si is completely loaded
add_action( 'sprout_invoices_loaded', 'sa_load_mercadopago_updates' );
function sa_load_mercadopago_updates() {
	if ( class_exists( 'SI_Updates' ) ) {
		require_once( 'inc/sa-updates/SA_Updates.php' );
	}
}