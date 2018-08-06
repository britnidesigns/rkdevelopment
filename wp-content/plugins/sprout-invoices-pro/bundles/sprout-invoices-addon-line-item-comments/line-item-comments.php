<?php
/*
Plugin Name: Sprout Invoices Add-on - Line Item Comments
Plugin URI: https://sproutapps.co/marketplace/estimateinvoice-line-item-commenting/
Description: Allows for invoices and estimates to be commented on.
Author: Sprout Apps
Version: 2.0
ID: 14750
Author URI: https://sproutapps.co
Auto Active: true
*/

/**
 * Plugin Info for updates
 */
define( 'SA_ADDON_DOC_COMMENTS_VERSION', '2.0' );
define( 'SA_ADDON_DOC_COMMENTS_DOWNLOAD_ID', 14750 );
define( 'SA_ADDON_DOC_COMMENTS_NAME', 'Sprout Invoices Line Item Comments' );
define( 'SA_ADDON_DOC_COMMENTS_FILE', __FILE__ );
define( 'SA_ADDON_DOC_COMMENTS_PATH', dirname( __FILE__ ) );
define( 'SA_ADDON_DOC_COMMENTS_URL', plugins_url( '', __FILE__ ) );

if ( ! defined( 'SI_DEV' ) ) {
	define( 'SI_DEV', false );
}

// Load up after SI is loaded.
add_action( 'sprout_invoices_loaded', 'sa_load_doc_comments_addon' );
function sa_load_doc_comments_addon() {
	if ( class_exists( 'SI_Doc_Comments' ) ) {
		return;
	}

	require_once( 'inc/Doc_Comments.php' );
	SI_Doc_Comments::init();

	require_once( 'inc/Doc_Comment_Notifications.php' );
	SI_Doc_Comments_Notifications::init();
}

if ( ! apply_filters( 'is_bundle_addon', false ) ) {
	if ( SI_DEV ) { error_log( 'not bundled: sa_load_doc_comments_updates' ); }
	// Load up the updater after si is completely loaded
	add_action( 'sprout_invoices_loaded', 'sa_load_doc_comments_updates' );
	function sa_load_doc_comments_updates() {
		if ( class_exists( 'SI_Updates' ) ) {
			require_once( 'inc/sa-updates/SA_Updates.php' );
		}
	}
}
