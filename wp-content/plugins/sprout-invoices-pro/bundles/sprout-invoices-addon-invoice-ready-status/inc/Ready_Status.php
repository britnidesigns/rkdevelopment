<?php

/**
 * @package SI_Ready_Status
 */
class SI_Ready_Status extends SI_Controller {

	public static function init() {
		self::register_post_status();

		add_filter( 'si_invoice_statuses', array( __CLASS__, 'add_ready_status' ) );

		// is current status
		add_filter( 'si_is_invoice_currently_custom_status', array( __CLASS__, 'is_invoice_current_status' ) );

		// add option to dropdown
		add_action( 'si_invoice_custom_status_current_label', array( __CLASS__, 'add_ready_status_to_drop_label' ) );
		add_action( 'si_invoice_custom_status_current_option', array( __CLASS__, 'add_ready_status_to_drop_option' ) );
	}

	public static function add_ready_status( $statuses = array() ) {
		$statuses['ready'] = __( 'Payment Due', 'sprout-invoices' );
		return $statuses;
	}

	public static function register_post_status() {
		register_post_status( 'ready', array(
				'label' => __( 'Payment Due', 'sprout-invoices' ),
				'public' => true,
				'exclude_from_search' => false,
				'show_in_admin_all_list' => true,
		  		'show_in_admin_status_list' => true,
		  		'label_count' => _n_noop( __( 'Payment Due', 'sprout-invoices' ) . ' <span class="count">(%s)</span>', __( 'Payments Due', 'sprout-invoices' ) . ' <span class="count">(%s)</span>' ),
		));
	}

	public static function is_invoice_current_status( $invoice_id ) {
		$invoice = SI_Invoice::get_instance( $invoice_id );
		if ( ! is_a( $invoice, 'SI_Invoice' ) ) {
			return;
		}
		$status = $invoice->get_status();
		if ( 'ready' != $status ) {
			return false;
		}
		return true;
	}

	public static function add_ready_status_to_drop_label( $doc_id ) {
		$view = self::load_addon_view_to_string( 'admin/drop-label', array(
			'doc_id' => $doc_id,
		), true );
		print $view;
	}

	public static function add_ready_status_to_drop_option( $doc_id ) {
		$view = self::load_addon_view_to_string( 'admin/drop-option', array(
			'doc_id' => $doc_id,
		), true );
		print $view;
	}


	public static function load_addon_view( $view, $args, $allow_theme_override = true ) {
		add_filter( 'si_views_path', array( __CLASS__, 'addons_view_path' ) );
		$view = self::load_view( $view, $args, $allow_theme_override );
		remove_filter( 'si_views_path', array( __CLASS__, 'addons_view_path' ) );
		return $view;
	}

	protected static function load_addon_view_to_string( $view, $args, $allow_theme_override = true ) {
		ob_start();
		self::load_addon_view( $view, $args, $allow_theme_override );
		return ob_get_clean();
	}

	public static function addons_view_path() {
		return SA_ADDON_READY_STATUS_PATH . '/views/';
	}
}
