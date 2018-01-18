<?php
/*
Plugin Name: Sprout Invoices Add-on - Invoice Submissions
Plugin URI: https://sproutapps.co/marketplace/dynamic-invoice-submissions-from-gravityforms-and-ninja-forms/
Description: Use your premium plugin based form to accept invoice submissions.
Author: Sprout Apps
Version: 1.5
Author URI: https://sproutapps.co
*/

/**
 * Plugin Info for updates
 */
define( 'SA_ADDON_INVOICE_SUBMISSIONS_VERSION', '1.5' );
define( 'SA_ADDON_INVOICE_SUBMISSIONS_DOWNLOAD_ID', 18425 );
define( 'SA_ADDON_INVOICE_SUBMISSIONS_FILE', __FILE__ );
define( 'SA_ADDON_INVOICE_SUBMISSIONS_NAME', 'Sprout Invoices Invoice Submissions' );
define( 'SA_ADDON_INVOICE_SUBMISSIONS_URL', plugins_url( '', __FILE__ ) );

if ( ! function_exists( 'sa_load_invoice_submissions_addon' ) ) {

	// Load up after SI is loaded.
	add_action( 'sprout_invoices_loaded', 'sa_load_invoice_submissions_addon' );
	function sa_load_invoice_submissions_addon() {
		require_once( 'inc/Submission.php' );

		if ( class_exists( 'RGFormsModel' ) ) {
			require_once( 'inc/integrations/Gravity_Forms.php' );
		}
		if ( function_exists( 'ninja_forms_get_all_forms' ) ) {
			require_once( 'inc/integrations/Ninja_Forms.php' );
		}
		if ( function_exists( 'frm_forms_autoloader' ) ) {
			require_once( 'inc/integrations/Formidable.php' );
		}
	}

	// Load up the updater after si is completely loaded
	add_action( 'sprout_invoices_loaded', 'sa_load_invoice_submissions_updates' );
	function sa_load_invoice_submissions_updates() {
		if ( class_exists( 'SI_Updates' ) ) {
			require_once( 'inc/sa-updates/SA_Updates.php' );
		}
	}
}
