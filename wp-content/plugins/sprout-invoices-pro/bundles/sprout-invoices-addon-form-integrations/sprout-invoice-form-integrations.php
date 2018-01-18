<?php
/*
Plugin Name: Sprout Invoices Add-on - Form Integrations
Plugin URI: https://sproutapps.co/marketplace/advanced-form-integration-gravity-ninja-forms/
Description: Use your premium plugin based form to accept estimate requests that integrate with Sprout Invoices.
Author: Sprout Apps
Version: 1.8
Auto Active: true
Author URI: https://sproutapps.co
*/

/**
 * Plugin Info for updates
 */
define( 'SA_ADDON_FI_VERSION', '1.8' );
define( 'SA_ADDON_FI_DOWNLOAD_ID', 280 );
define( 'SA_ADDON_FI_FILE', __FILE__ );
define( 'SA_ADDON_FI_NAME', 'Sprout Invoices Form Integrations' );
define( 'SA_ADDON_FI_URL', plugins_url( '', __FILE__ ) );


if ( ! function_exists( 'sa_load_form_int_addon' ) ) {
	// Load up after SI is loaded.
	add_action( 'sprout_invoices_loaded', 'sa_load_form_int_addon' );
	function sa_load_form_int_addon() {
		require_once( 'SI_Form_Integrations.php' );
		SI_Form_Integrations::init();
	}

	// Load up the updater after si is completely loaded
	add_action( 'sprout_invoices_loaded', 'sa_load_form_int_updates' );
	function sa_load_form_int_updates() {
		if ( class_exists( 'SI_Updates' ) ) {
			require_once( 'inc/sa-updates/SA_Updates.php' );
		}
	}
}
