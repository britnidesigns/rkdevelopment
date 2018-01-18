<?php
/*
Plugin Name: Sprout Invoices Add-on - Login
Plugin URI: https://sproutapps.co/marketplace/login/
Description: Force clients to login (or enter password) before viewing an invoice or estimate of theirs.
Author: Sprout Apps
Version: 1.1.1
Author URI: https://sproutapps.co
*/

/**
 * Plugin Info for updates
 */
define( 'SA_ADDON_LOGIN_VERSION', '1.1.1' );
define( 'SA_ADDON_LOGIN_DOWNLOAD_ID', 16242 );
define( 'SA_ADDON_LOGIN_NAME', 'Sprout Invoices Login' );
define( 'SA_ADDON_LOGIN_FILE', __FILE__ );
define( 'SA_ADDON_LOGIN_PATH', dirname( __FILE__ ) );
define( 'SA_ADDON_LOGIN_URL', plugins_url( '', __FILE__ ) );

if ( ! function_exists( 'sa_load_login_addon' ) ) {

	// Load up after SI is loaded.
	add_action( 'sprout_invoices_loaded', 'sa_load_login_addon' );
	function sa_load_login_addon() {
		if ( class_exists( 'SI_Login' ) ) {
			return;
		}

		require_once( 'inc/Login.php' );

		SI_Login::init();
	}

	if ( ! apply_filters( 'is_bundle_addon', false ) ) {
		// Load up the updater after si is completely loaded
		add_action( 'sprout_invoices_loaded', 'sa_load_login_updates' );
		function sa_load_login_updates() {
			if ( class_exists( 'SI_Updates' ) ) {
				require_once( 'inc/sa-updates/SA_Updates.php' );
			}
		}
	}
}
