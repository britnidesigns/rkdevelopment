<?php
/*
Plugin Name: Sprout Invoices Add-on - PDF Service
Plugin URI: https://sproutapps.co/sprout-invoices/sprout-pdfs/
Description: Generates PDFs via a Sprout Apps API to create better looking PDFs.<br/><b>Note:</b> The PDF Service will work only for current Business and Corporate license holders.
Author: Sprout Apps
Version: 0.1 Alpha
Author URI: https://sproutapps.co
*/

/**
 * Plugin Info for updates
 */
define( 'SA_ADDON_SPROUT_PDFS_VERSION', '0.1' );
define( 'SA_ADDON_SPROUT_PDFS_DOWNLOAD_ID', 577436 );
define( 'SA_ADDON_SPROUT_PDFS_NAME', 'Sprout Invoices PDF Service (beta)' );
define( 'SA_ADDON_SPROUT_PDFS_FILE', __FILE__ );
define( 'SA_ADDON_SPROUT_PDFS_PATH', dirname( __FILE__ ) );
define( 'SA_ADDON_SPROUT_PDFS_URL', plugins_url( '', __FILE__ ) );


if ( ! defined( 'SI_DEV' ) ) {
	define( 'SI_DEV', false );
}

if ( ! function_exists( 'sa_load_si_pdf_service_addon' ) ) {
		// Load up after SI is loaded.
	add_action( 'sprout_invoices_loaded', 'sa_load_si_pdf_service_addon' );
	function sa_load_si_pdf_service_addon() {
		if ( class_exists( 'Sprout_PDFs_Controller' ) ) {
			return;
		}

		require_once( 'vendor/pdfcrowd.php' );
		require_once( 'inc/PDF_Service_Controller.php' );
		require_once( 'inc/PDF_Service_API.php' );
		require_once( 'inc/PDF_Service_Settings.php' );
		require_once( 'inc/PDF_Service_Attachments.php' );
		require_once( 'inc/PDF_Service_Views.php' );

		if ( SI_Updates::license_status() != false && SI_Updates::license_status() == 'valid' ) {
			SI_Sprout_PDFs_Controller::init();
				// init sub classes
				SI_Sprout_PDFs_API::init();
				SI_Sprout_PDFs_Settings::init();
				SI_Sprout_PDFs_Attachments::init();
				SI_Sprout_PDFs_Views::init();
		}
	}

	if ( ! apply_filters( 'is_bundle_addon', false ) ) {
		if ( SI_DEV ) { error_log( 'not bundled: sa_load_si_pdf_service_updates' ); }
		// Load up the updater after si is completely loaded
		add_action( 'sprout_invoices_loaded', 'sa_load_si_pdf_service_updates' );
		function sa_load_si_pdf_service_updates() {
			if ( class_exists( 'SI_Updates' ) ) {
				require_once( 'inc/sa-updates/SA_Updates.php' );
				SA_Sprout_PDFs_Updates::init();
			}
		}
	}
}
