<?php
/*
Plugin Name: Sprout Invoices Add-on - Recurring (aka Subscription) Payments
Plugin URI: https://sproutapps.co/marketplace/recurring-subscription-payments/
Description: Setup an invoice to automatically bill a client over an extended period, like a subscription payment.
Author: Sprout Apps
ID: 0
Version: 1.1
Author URI: https://sproutapps.co
*/

/**
 * Plugin Info for updates
 */
define( 'SA_ADDON_SUBSCRIPTION_PAYMENTS_VERSION', '1.1' );
define( 'SA_ADDON_SUBSCRIPTION_PAYMENTS_DOWNLOAD_ID', 1111 );
define( 'SA_ADDON_SUBSCRIPTION_PAYMENTS_NAME', 'Sprout Invoices Time Tracker' );
define( 'SA_ADDON_SUBSCRIPTION_PAYMENTS_FILE', __FILE__ );
define( 'SA_ADDON_SUBSCRIPTION_PAYMENTS_PATH', dirname( __FILE__ ) );
define( 'SA_ADDON_SUBSCRIPTION_PAYMENTS_URL', plugins_url( '', __FILE__ ) );

// Load up after SI is loaded.
add_action( 'sprout_invoices_loaded', 'sa_load_subscription_payments_addon' );
function sa_load_subscription_payments_addon() {
	if ( class_exists( 'Subscription_Payments' ) ) {
		return;
	}

	require_once( 'inc/Subscription_Payments.php' );
	require_once( 'inc/Subscription_Invoices.php' );

	SI_Subscription_Payments::init();
	Subscription_Invoices::init();
}

if ( ! apply_filters( 'is_bundle_addon', false ) ) {
	if ( SI_DEV ) { error_log( 'not bundled: sa_load_subscription_payments_updates' ); }
	// Load up the updater after si is completely loaded
	add_action( 'sprout_invoices_loaded', 'sa_load_subscription_payments_updates' );
	function sa_load_subscription_payments_updates() {
		if ( class_exists( 'SI_Updates' ) ) {
			require_once( 'inc/sa-updates/SA_Updates.php' );
		}
	}
}
