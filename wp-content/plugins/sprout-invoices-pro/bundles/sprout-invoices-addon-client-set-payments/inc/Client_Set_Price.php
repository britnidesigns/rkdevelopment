<?php

/**
 * Client_Set_Price Controller
 *
 * @package Sprout_Invoice
 * @subpackage Client_Set_Price
 */
class Client_Set_Price extends SI_Controller {
	const TEMP_META_KEY = 'si_temp_deposit_';
	const AJAX_ACTION = 'si_set_temp_deposit';

	public static function init() {
		// og theme, and slate
		add_action( 'si_doc_actions', array( __CLASS__, 'client_set_price_view' ) );

		// default theme
		add_action( 'si_default_theme_payment_options_desc', array( __CLASS__, 'client_set_price_view' ), 100 );

		// AJAX
		add_action( 'wp_ajax_' . self::AJAX_ACTION, array( get_class(), 'maybe_set_temp_deposit' ) );
		add_action( 'wp_ajax_nopriv_' . self::AJAX_ACTION, array( get_class(), 'maybe_set_temp_deposit' ) );

		// Change deposit amount
		add_filter( 'si_get_invoice_deposit', array( __CLASS__, 'maybe_change_deposit' ), 10, 2 );

		// Don't show upgrade message for despit section
		add_filter( 'show_upgrade_messaging', array( __CLASS__, 'add_deposit_for_free' ), 10, 2 );
	}

	public static function maybe_change_deposit( $deposit, SI_Invoice $invoice ) {
		$temp = (float) self::get_temp_deposit( $invoice->get_id() );

		if ( $deposit < 0.01 ) {
			$allow_client_price = apply_filters( 'si_allow_payment_to_be_set_by_client', false, $invoice );
			if ( ! $allow_client_price || ! $temp ) {
				return $deposit;
			}
		}

		if ( $temp > $deposit ) { // If greater than set the temp as the new deposit
			$deposit = round( $temp, 2 );
			$balance = $invoice->get_balance();
			if ( $deposit > $balance ) { // check if deposit is more than what's due.
				$deposit = floatval( $balance );
			}
		}

		return round( (float) $deposit, 2 );
	}

	public static function add_deposit_for_free( $bool, $location = '' ) {
		if ( $location === 'deposit-line-items' ) {
			return false;
		}
		return $bool;
	}

	//////////
	// View //
	//////////

	public static function client_set_price_view() {
		if ( ! SI_Invoice::is_invoice_query() ) {
			return;
		}

		$invoice = SI_Invoice::get_instance( get_the_ID() );
		$balance = $invoice->get_balance();
		$temp_deposit = self::get_temp_deposit( $invoice->get_id() );

		// don't allow the filter not return the true value
		remove_filter( 'si_get_invoice_deposit', array( __CLASS__, 'maybe_change_deposit' ) );
		$admin_set_deposit = $invoice->get_deposit();

		// If deposit isn't set than fallback to balance
		if ( $admin_set_deposit < 0.01 ) {
			$temp_deposit = $balance;
			$allow_client_price = apply_filters( 'si_allow_payment_to_be_set_by_client', false, $invoice );
			if ( ! $allow_client_price ) {
				return;
			}
		}

		$payment_amount = ( $temp_deposit ) ? $temp_deposit : $invoice->get_deposit();

		$theme = SI_Templating_API::get_invoice_theme_option();

		self::load_addon_view( 'theme/' . $theme . '/invoice/price-option', array(
			'min_payment' => $admin_set_deposit,
			'max_payment' => $balance,
			'temp_deposit' => $temp_deposit,
			'payment_amount' => $payment_amount,
		) );
	}

	//////////
	// Meta //
	//////////

	public static function get_transient_key( $invoice_id ) {
		return self::TEMP_META_KEY . $invoice_id;
	}

	public static function get_temp_deposit( $invoice_id = 0 ) {
		$deposit = get_site_transient( self::get_transient_key( $invoice_id ) );
		return (float) $deposit;
	}

	public static function set_temp_deposit( $invoice_id = 0, $deposit = 0.00 ) {
		$deposit = round( (float) $deposit, 2 );
		set_site_transient( self::get_transient_key( $invoice_id ), $deposit, 60 * 60 ); // expire in an hour
		return (float) $deposit;
	}

	//////////
	// AJAX //
	//////////

	public static function maybe_set_temp_deposit() {
		$nonce = $_REQUEST['nonce'];
		if ( ! wp_verify_nonce( $nonce, self::NONCE ) ) {
			wp_send_json_error( array( 'message' => __( 'Not going to fall for it!', 'sprout-invoices' ) ) );
		}

		if ( ! isset( $_REQUEST['invoice_id'] ) || ! isset( $_REQUEST['payment_amount'] ) ) {
			wp_send_json_error( array( 'message' => __( 'Missing critical info!', 'sprout-invoices' ) ) );
		}

		// attempt the charge
		$invoice_id = $_REQUEST['invoice_id'];
		$deposit = $_REQUEST['payment_amount'];

		self::set_temp_deposit( $invoice_id, $deposit );

		wp_send_json_success( array( 'message' => sprintf( __( 'Payment amount updated: %s', 'sprout-invoices' ), sa_get_formatted_money( $deposit ) ) ) );
	}

	//////////////
	// Utility //
	//////////////

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
		return SA_ADDON_CLIENT_PRICE_PATH . '/views/';
	}
}
