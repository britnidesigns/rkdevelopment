<?php
/*
Plugin Name: Sprout Invoices Add-on - PaymentExpress CC Payments
Plugin URI: https://sproutapps.co/marketplace/paymentexpress-credit-card-payments/
Description: Accept PaymentExpress Payments with Sprout Invoices.
Author: Sprout Apps
Version: 1.0
Author URI: https://sproutapps.co
*/

/**
 * Plugin File
 */
define( 'SA_ADDON_PX_POST_VERSION', '1' );
define( 'SA_ADDON_PX_POST_DOWNLOAD_ID', 6074 );
define( 'SA_ADDON_PX_POST_FILE', __FILE__ );
define( 'SA_ADDON_PX_POST_NAME', 'Sprout Invoices PaymentExpress CC Payments' );
define( 'SA_ADDON_PX_POST_URL', plugins_url( '', __FILE__ ) );


// Load up the processor before updates
add_action( 'si_payment_processors_loaded', 'sa_load_px_post' );
function sa_load_px_post() {
	require_once( 'SA_PaymentExpress_CC.php' );
}

// Load up the updater after si is completely loaded
add_action( 'sprout_invoices_loaded', 'sa_load_px_post_updates' );
function sa_load_px_post_updates() {
	if ( class_exists( 'SI_Updates' ) ) {
		require_once( 'inc/sa-updates/SA_Updates.php' );
	}
}