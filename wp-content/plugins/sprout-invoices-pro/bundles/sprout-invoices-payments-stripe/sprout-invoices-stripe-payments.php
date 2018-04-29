<?php
/*
Plugin Name: Sprout Invoices Add-on - Stripe Payments
Plugin URI: https://sproutapps.co/marketplace/stripe-payments/
Description: Accept Stripe Payments with Sprout Invoices.
Author: Sprout Apps
Version: 4.0.6
Author URI: https://sproutapps.co
*/

/**
 * Plugin File
 */
define( 'SA_ADDON_STRIPE_VERSION', '4.0.6' );
define( 'SA_ADDON_STRIPE_DOWNLOAD_ID', 141 );
define( 'SA_ADDON_STRIPE_FILE', __FILE__ );
define( 'SA_ADDON_STRIPE_NAME', 'Sprout Invoices Stripe Payments' );

if ( ! defined( 'SA_ADDON_STRIPE_URL' ) ) {
	define( 'SA_ADDON_STRIPE_URL', plugins_url( '', __FILE__ ) );
}

// Load up the processor before updates
add_action( 'si_payment_processors_loaded', 'sa_load_stripe' );
function sa_load_stripe() {
	require_once( 'SA_Stripe.php' );
}

// Load up the updater after si is completely loaded
add_action( 'sprout_invoices_loaded', 'sa_load_stripe_updates' );
function sa_load_stripe_updates() {
	if ( class_exists( 'SI_Updates' ) ) {
		require_once( 'inc/sa-updates/SA_Updates.php' );
	}
}
