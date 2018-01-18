<?php
/*
Plugin Name: Sprout Invoices Add-on - NMI Payments (Custom)
Plugin URI: https://sproutapps.co/marketplace/nmi-payments/
Description: This is cusotm version that requires some theme modifications.
Author: Sprout Apps
Version: 2
Author URI: https://sproutapps.co
*/

/**
 * Plugin File
 */
define( 'SA_ADDON_NMI_VERSION', '2' );
define( 'SA_ADDON_NMI_DOWNLOAD_ID', 6120 );
define( 'SA_ADDON_NMI_FILE', __FILE__ );
define( 'SA_ADDON_NMI_PATH', dirname( __FILE__ ) );
define( 'SA_ADDON_NMI_NAME', 'Sprout Invoices Stripe Payments' );
define( 'SA_ADDON_NMI_URL', plugins_url( '', __FILE__ ) );


// Load up the processor before updates
add_action( 'si_payment_processors_loaded', 'sa_load_nmi' );
function sa_load_nmi() {
	require_once( 'SA_NMI.php' );
}

// Load up the updater after si is completely loaded
add_action( 'sprout_invoices_loaded', 'sa_load_nmi_updates' );
function sa_load_nmi_updates() {
	if ( class_exists( 'SI_Updates' ) ) {
		require_once( 'inc/sa-updates/SA_Updates.php' );
	}
}
