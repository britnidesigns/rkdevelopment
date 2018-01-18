<?php
/*
Plugin Name: Sprout Invoices Add-on - Digital Signatures
Plugin URI: https://sproutapps.co/marketplace/time-tracking/
Description: Adds the requirement for client's to digitally sign docs before payment or acceptance.
Author: Sprout Apps
Version: 1.1
Author URI: https://sproutapps.co
*/

/**
 * Plugin Info for updates
 */
define( 'SA_ADDON_SI_ESIGNATURE_VERSION', '1.1' );
define( 'SA_ADDON_SI_ESIGNATURE_DOWNLOAD_ID', 577436 );
define( 'SA_ADDON_SI_ESIGNATURE_NAME', 'Sprout Invoices Digital Signature' );
define( 'SA_ADDON_SI_ESIGNATURE_FILE', __FILE__ );
define( 'SA_ADDON_SI_ESIGNATURE_PATH', dirname( __FILE__ ) );
define( 'SA_ADDON_SI_ESIGNATURE_URL', plugins_url( '', __FILE__ ) );

if ( ! defined( 'SI_DEV' ) ) {
	define( 'SI_DEV', false );
}

if ( ! defined( 'SI_WPES_USE_COOKIES' ) ) {
	define( 'SI_WPES_USE_COOKIES', true );
}

// Load up after SI is loaded.
add_action( 'sprout_invoices_loaded', 'sa_load_si_esignature_addon' );
function sa_load_si_esignature_addon() {
	if ( class_exists( 'eSignature_Controller' ) ) {
		return;
	}

	require_once( 'inc/eSignature_Controller.php' );
	require_once( 'inc/eSignature_Settings.php' );
	if ( ! function_exists( 'GetSignatureImage' ) ) {
		require_once( 'resources/ss/license.php' );
	}

	eSignature_Controller::init();
		// init sub classes
		eSignature_Settings::init();
}

if ( ! apply_filters( 'is_bundle_addon', false ) ) {
	if ( SI_DEV ) { error_log( 'not bundled: sa_load_si_esignature_updates' ); }
	// Load up the updater after si is completely loaded
	add_action( 'sprout_invoices_loaded', 'sa_load_si_esignature_updates' );
	function sa_load_si_esignature_updates() {
		if ( class_exists( 'SI_Updates' ) ) {
			require_once( 'inc/sa-updates/SA_Updates.php' );
			SA_eSignature_Updates::init();
		}
	}
}
