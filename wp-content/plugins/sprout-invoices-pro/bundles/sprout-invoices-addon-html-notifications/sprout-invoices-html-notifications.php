<?php
/*
Plugin Name: Sprout Invoices Add-on - HTML Notifications
Plugin URI: https://sproutapps.co/marketplace/html-notifications/
Description: Use some beautiful HTML notifications for your e-mail communications.
Author: Sprout Apps
Version: 2.1.1
ID: 7970
Author URI: https://sproutapps.co
Auto Active: true
*/

/**
 * Plugin Info for updates
 */
define( 'SA_ADDON_HTML_NOTIFICATIONS_VERSION', '2.1.1' );
define( 'SA_ADDON_HTML_NOTIFICATIONS_DOWNLOAD_ID', 7970 );
define( 'SA_ADDON_HTML_NOTIFICATIONS_NAME', 'Sprout Invoices HTML Notifications' );
define( 'SA_ADDON_HTML_NOTIFICATIONS_FILE', __FILE__ );
define( 'SA_ADDON_HTML_NOTIFICATIONS_PATH', dirname( __FILE__ ) );
define( 'SA_ADDON_HTML_NOTIFICATIONS_URL', plugins_url( '', __FILE__ ) );

if ( ! function_exists( 'sa_load_html_notifications_addon' ) ) {

	// Load up after SI is loaded.
	add_action( 'sprout_invoices_loaded', 'sa_load_html_notifications_addon' );
	function sa_load_html_notifications_addon() {
		if ( class_exists( 'SI_HTML_Notifications' ) ) {
			return;
		}

		require_once( 'inc/HTML_Notifications.php' );

		SI_HTML_Notifications::init();
	}

	if ( ! apply_filters( 'is_bundle_addon', false ) ) {
		// Load up the updater after si is completely loaded
		add_action( 'sprout_invoices_loaded', 'sa_load_html_notifications_updates' );
		function sa_load_html_notifications_updates() {
			if ( class_exists( 'SI_Updates' ) ) {
				require_once( 'inc/sa-updates/SA_Updates.php' );
			}
		}
	}
}
