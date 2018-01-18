<?php
/*
Plugin Name: Sprout Invoices Add-on - Attachments and Downloads
Plugin URI: https://sproutapps.co/marketplace/attachments-downloads/
Description: Allows for an admin to add an attachment for download after an invoice is paid (with a filter to show attachments at all times).
Author: Sprout Apps
Version: 1.2
Author URI: https://sproutapps.co
*/

/**
 * Plugin Info for updates
 */
define( 'SA_ADDON_ATTACHMENT_DOWNLOADS_VERSION', '1.2' );
define( 'SA_ADDON_ATTACHMENT_DOWNLOADS_DOWNLOAD_ID', 62854 );
define( 'SA_ADDON_ATTACHMENT_DOWNLOADS_NAME', 'Sprout Invoices Attachments and Downloads' );
define( 'SA_ADDON_ATTACHMENT_DOWNLOADS_FILE', __FILE__ );
define( 'SA_ADDON_ATTACHMENT_DOWNLOADS_PATH', dirname( __FILE__ ) );
define( 'SA_ADDON_ATTACHMENT_DOWNLOADS_URL', plugins_url( '', __FILE__ ) );
if ( ! defined( 'SI_DEV' ) ) {
	define( 'SI_DEV', false );
}

if ( ! function_exists( 'sa_load_attachment_downloads_addon' ) ) {
	// Load up after SI is loaded.
	add_action( 'sprout_invoices_loaded', 'sa_load_attachment_downloads_addon' );
	function sa_load_attachment_downloads_addon() {
		if ( class_exists( 'Attachment_Downloads' ) ) {
			return;
		}

		require_once( 'inc/Attachment_Downloads.php' );
		SI_Attachment_Downloads::init();
	}

	if ( ! apply_filters( 'is_bundle_addon', false ) ) {
		if ( SI_DEV ) { error_log( 'not bundled: sa_load_attachment_downloads_updates' ); }
		// Load up the updater after si is completely loaded
		add_action( 'sprout_invoices_loaded', 'sa_load_attachment_downloads_updates' );
		function sa_load_attachment_downloads_updates() {
			if ( class_exists( 'SI_Updates' ) ) {
				require_once( 'inc/sa-updates/SA_Updates.php' );
				SA_Attachment_Downloads_Updates::init();
			}
		}
	}
}
