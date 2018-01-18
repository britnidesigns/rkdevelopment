<?php
/*
Plugin Name: Sprout Invoices Add-on - Payment Terms
Plugin URI: https://sproutapps.co/marketplace/service-fee/
Description: Allows for a Payment Terms to be set per invoice.
Author: Sprout Apps
Version: 1.0
Author URI: https://sproutapps.co
Auto Active: true
*/

/**
 * Plugin Info for updates
 */
define( 'SA_ADDON_PAYMENT_TERMS_VERSION', '1.0' );
define( 'SA_ADDON_PAYMENT_TERMS_DOWNLOAD_ID', 0000000 );
define( 'SA_ADDON_PAYMENT_TERMS_NAME', 'Sprout Invoices Payment Terms' );
define( 'SA_ADDON_PAYMENT_TERMS_FILE', __FILE__ );
define( 'SA_ADDON_PAYMENT_TERMS_PATH', dirname( __FILE__ ) );
define( 'SA_ADDON_PAYMENT_TERMS_URL', plugins_url( '', __FILE__ ) );
if ( ! defined( 'SI_DEV' ) ) {
	define( 'SI_DEV', false );
}

// Load up after SI is loaded.
add_action( 'sprout_invoices_loaded', 'sa_load_Payment_Terms_addon' );
function sa_load_payment_terms_addon() {
	if ( class_exists( 'SI_Payment_Terms' ) ) {
		return;
	}

	require_once( 'inc/Payment_Terms.php' );
	require_once( 'inc/Payment_Term.php' );
	require_once( 'inc/Payment_Terms_Admin.php' );
	require_once( 'inc/Payment_Terms_Fees.php' );
	require_once( 'inc/Payment_Terms_Deposits.php' );
	require_once( 'inc/Payment_Terms_Notification.php' );

	SI_Payment_Terms::init();

	if ( ! class_exists( 'SI_Sprout_Billings' ) ) {
		return;
	}

	// Sprout Billings Compat.
	require_once( 'inc/Payment_Terms_Billings.php' );
	SI_Payment_Terms_Billings::init();
}

if ( ! apply_filters( 'is_bundle_addon', false ) ) {
	if ( SI_DEV ) { error_log( 'not bundled: sa_load_payment_terms_updates' ); }
	// Load up the updater after si is completely loaded
	add_action( 'sprout_invoices_loaded', 'sa_load_payment_terms_updates' );
	function sa_load_payment_terms_updates() {
		if ( class_exists( 'SI_Updates' ) ) {
			require_once( 'inc/sa-updates/SA_Updates.php' );
		}
	}
}
