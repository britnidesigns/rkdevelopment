<?php
/*
Plugin Name: Sprout Invoices Add-on - Sprout Billings
Plugin URI: https://sproutapps.co/marketplace/auto-billing-invoices-payment-profiles/
Description: A way for customers to pay their invoices automatically, while providing the ability for them to update payment methods within a dashboard.
Author: Sprout Apps
Version: 3.5.3
Author URI: https://sproutapps.co
*/

/**
 * Plugin Info for updates
 */
define( 'SA_ADDON_AUTO_BILLING_VERSION', '3.5.3' );
define( 'SA_ADDON_AUTO_BILLING_DOWNLOAD_ID', 44588 );
define( 'SA_ADDON_AUTO_BILLING_NAME', 'Sprout Invoices Auto Billing & Payment Profiles' );
define( 'SA_ADDON_AUTO_BILLING_FILE', __FILE__ );
define( 'SA_ADDON_AUTO_BILLING_PATH', dirname( __FILE__ ) );
define( 'SA_ADDON_AUTO_BILLING_URL', plugins_url( '', __FILE__ ) );

// Load up after SI is loaded.
add_action( 'sprout_invoices_loaded', 'sa_load_auto_billing_addon' );
function sa_load_auto_billing_addon() {
	if ( class_exists( 'SI_Sprout_Billings' ) ) {
		return;
	}
	// Controller
	require_once( 'controllers/Sprout_Billings.php' );
	require_once( 'controllers/Sprout_Billings_Admin.php' );
	require_once( 'controllers/Sprout_Billings_AJAX.php' );
	require_once( 'controllers/Sprout_Billings_Checkout.php' );
	require_once( 'controllers/Sprout_Billings_New_Invoices.php' );
	require_once( 'controllers/Sprout_Billings_Shortcodes.php' );
	require_once( 'controllers/Sprout_Billings_Notifications.php' );
	require_once( 'controllers/Sprout_Billings_Tasks.php' );

	require_once( 'controllers/Sprout_Billings_Client_Set_Payment.php' );

	require_once( 'controllers/Sprout_Billings_Profiles.php' );

	require_once( 'payment-processors/Auto_Payment_Processors.php' );

	require_once( 'template-tags/ab-functions.php' );

	SI_Sprout_Billings::init();
	SI_Sprout_Billings_Admin::init();
	SI_Sprout_Billings_AJAX::init();
	SI_Sprout_Billings_Checkout::init();
	SI_Sprout_Billings_New_Invoices::init();
	SI_Sprout_Billings_Shortcodes::init();
	SI_Sprout_Billings_Notifications::init();

	SI_Sprout_Billings_Client_Set_Payment::init();

	Sprout_Billings_Profiles::init();

	SI_Auto_Payment_Processors::init();

	SI_Sprout_Billings_Tasks::init();
}

add_action( 'si_payment_processors_loaded', 'sa_load_authnetcim_processor' );
function sa_load_authnetcim_processor() {
	if ( ! class_exists( 'SI_AuthorizeNet_CIM' ) ) {
		// Payment Processor
		require_once( 'payment-processors/authorize-net-cim/SA_AuthorizeNet_CIM.php' );
	}

	if ( ! class_exists( 'SA_Stripe_Profiles' ) ) {
		// Payment Processor
		require_once( 'payment-processors/stripe/SA_Stripe_Profiles.php' );
	}

	if ( ! class_exists( 'SA_NMI_Tokenized' ) ) {
		// Payment Processor
		require_once( 'payment-processors/nmi/SA_NMI_Tokenized.php' );
	}
}

// Load up the updater after si is completely loaded
add_action( 'sprout_invoices_loaded', 'sa_load_auto_billing_updates' );
function sa_load_auto_billing_updates() {
	if ( class_exists( 'SI_Updates' ) ) {
		require_once( 'inc/sa-updates/SA_Updates.php' );
	}
}
