<?php

/**
 * Auto Billing Controller
 *
 *
 * @package SI_Sprout_Billings
 */
class SI_Sprout_Billings extends SI_Controller {
	const CLIENT_META_KEY = 'sb_default_client_bill_date';
	const GLOBAL_META_KEY = 'sb_default_bill_date';
	const PAYMENT_PROFILE_ID = 'sc_allow_charging';
	const DONOT_AUTOBILL = 'sb_do_not_autobill';
	const FAILED_ATTEMPTS_META = 'sc_failed_attempts';
	const RECORD = 'auto_payments';

	public static function init() {
		// nothing.
	}

	//////////
	// Meta //
	//////////


	/**
	 * The payment profile_id is the payment method selected
	 * at checkout, or from a shortcode. It's the profile id
	 * from the Sprout_Billings_Profiles::get_client_payment_profiles
	 */

	/**
	 * Can the client be charged with a saved payment profile
	 * @param  int $client_id
	 * @return bool
	 */
	public static function can_autocharge_client( $client_id = 0 ) {
		$option = get_post_meta( $client_id, self::PAYMENT_PROFILE_ID, true );
		if ( '' === $option ) {
			$option = false;
		}
		return $option;
	}

	public static function get_client_autopay_profile_id( $client_id ) {
		return get_post_meta( $client_id, self::PAYMENT_PROFILE_ID, true );
	}

	public static function save_client_payment_profile( $client_id, $payment_profile_id ) {
		update_post_meta( $client_id, self::PAYMENT_PROFILE_ID, $payment_profile_id );
	}

	public static function clear_client_payment_profile( $client_id ) {
		update_post_meta( $client_id, self::PAYMENT_PROFILE_ID, '' );
	}

	/**
	 * Global autopay date
	 */

	public static function get_global_autopay_date() {
		$day_of_month = get_option( self::GLOBAL_META_KEY, 1 );
		return $day_of_month;
	}

	/**
	 * The client specified option
	 */

	public static function set_client_autopay_date( $client_id = 0, $day_of_month = 15 ) {
		if ( is_a( $client_id, 'SI_Client' ) ) {
			$client_id = $client_id->get_id();
		}
		update_post_meta( $client_id, self::CLIENT_META_KEY, $day_of_month );
	}

	public static function get_client_autopay_date( $client_id = 0 ) {
		if ( is_a( $client_id, 'SI_Client' ) ) {
			$client_id = $client->get_id();
		}
		$day_of_month = get_post_meta( $client_id, self::CLIENT_META_KEY, true );
		if ( '' === $day_of_month ) {
			$day_of_month = self::get_global_autopay_date();
		}
		return $day_of_month;
	}


	/////////////////////
	// Attempt Payment //
	/////////////////////


	/**
	 * Attempt an autopayment
	 * @param  integer $invoice_id
	 * @param  integer $client_id
	 * @return null
	 */
	public static function maybe_attempt_autopay( $invoice_id = 0, $client_id = 0 ) {
		$invoice = SI_Invoice::get_instance( $invoice_id );
		if ( $invoice->get_balance() < 0.01 ) {
			return;
		}
		$disabled = self::is_auto_bill_disabled();
		if ( $disabled ) {
			return;
		}
		if ( ! apply_filters( 'sb_maybe_attempt_autopay', true, $invoice_id ) ) { // allow for an override, i.e. project completion.
			return;
		}
		if ( ! $client_id ) {
			$client_id = $invoice->get_client_id();
		}
		self::charge_invoice_balance( $invoice_id, $client_id );
	}

	/**
	 * Attempt to charge the client's payment profile for an outstanding invoice.
	 * @param  int $invoice_id
	 * @param  int $client_id
	 * @return string/int             error message or payment id.
	 */
	public static function charge_invoice_balance( $invoice_id = 0, $client_id = 0 ) {

		if ( ! apply_filters( 'sb_charge_invoice_balance', true, $invoice_id ) ) {
			return;
		}

		$invoice = SI_Invoice::get_instance( $invoice_id );

		if ( ! $client_id ) {
			$client_id = $invoice->get_client_id();
		}

		if ( ! $client_id ) {
			return;
		}

		$invoice->reset_totals( true );
		if ( $invoice->get_balance() < 0.01 ) {
			return;
		}

		do_action( 'sb_charge_invoice_balance', $invoice_id, $client_id );

		$payment_profile_id = self::get_client_autopay_profile_id( $client_id );
		if ( ! $payment_profile_id ) {

			// do_action( 'sb_attempt_charge_failed', $payment_profile_id, 0, $invoice_id, $client_id );

			return __( 'Client not setup for automatic payments.' , 'sprout-invoices' );

		}
		$payment_id = SI_Auto_Payment_Processors::attempt_payment( $invoice_id, $payment_profile_id );
		if ( ! is_numeric( $payment_id ) ) {

			$failed_attempts = get_post_meta( $invoice_id, self::FAILED_ATTEMPTS_META, true );

			// Check the amount of attempts already made
			if ( count( $failed_attempts ) >= apply_filters( 'sb_failed_attempts_count', 1 ) ) { // if already attempted once

				do_action( 'si_new_record',
					$payment_id,
					self::RECORD,
					$client_id,
					sprintf( __( 'Final Failed Payment Attempt: %s.' , 'sprout-invoices' ), (int) $invoice_id ),
					0,
					false
				);

				// disable the profile id
				Sprout_Billings_Profiles::remove_payment_profile( $payment_profile_id, $invoice_id );

			} else { // if not failed enough make a record

				$failed_attempts[] = current_time( 'timestamp' );
				udpate_post_meta( $invoice_id, self::FAILED_ATTEMPTS_META, $failed_attempts );

			}

			do_action( 'sb_attempt_charge_failed', $payment_profile_id, $payment_id, $invoice_id, $client_id, $failed_attempts );

			return $payment_id; // this will be an error.
		}
		do_action( 'sb_attempt_charge', $payment_profile_id, $payment_id, $invoice_id, $client_id );
		return $payment_id;
	}


	//////////////
	// Utility //
	//////////////


	public static function is_auto_bill_disabled( $doc_id = 0 ) {
		$option = SI_Sprout_Billings_Admin::get_invoice_autobill_option( $doc_id );
		return ( ! $option ) ? true : false ;
	}

	/**
	 * 24 hours
	 * @return string 24 hour format, means no am/pm
	 */
	public static function time_of_day_for_autopay() {
		// adjust for admin's local time
		return apply_filters( 'sisb_autopay_time_of_day', '8:00' );
	}

	public static function day_of_month_selection() {
		$selection_array = array();
		foreach ( range( 1, 31 ) as $number ) {
			$selection_array[ $number ] = sa_day_ordinal_formatter( $number );
		}
		return apply_filters( 'sisb_autobilling_date_time_options',
			$selection_array + array( '32' => __( 'Last Day of Month', 'sprout-invoices' ), '0' => __( 'Invoice Due Date', 'sprout-invoices' ) )
		);
	}

	public static function get_client_id( $invoice_id = 0, $logged_in = 1 ) {
		$client_id = 0;

		// a bit hacky so that an invoice id doesn't have to be passed if the client id is given instead.
		if ( get_post_type( $invoice_id ) === SI_Client::POST_TYPE ) {
			return $invoice_id;
		}
		if ( ! $invoice_id && is_single() && SI_Invoice::POST_TYPE === get_post_type( get_the_ID() ) ) {
			$invoice_id = get_the_ID();
		}
		if ( $invoice_id ) {
			$invoice = SI_Invoice::get_instance( $invoice_id );
			if ( is_a( $invoice, 'SI_Invoice' ) ) {
				$client_id = $invoice->get_client_id();
			}
		}
		if ( ! $client_id && $logged_in ) {
			$user_id = get_current_user_id();
			$client_ids = SI_Client::get_clients_by_user( $user_id );
			if ( ! empty( $client_ids ) ) {
				$client_id = array_pop( $client_ids );
			}
		}
		return $client_id;
	}

	public static function load_addon_view( $view, $args, $allow_theme_override = true ) {
		add_filter( 'si_views_path', array( __CLASS__, 'addons_view_path' ) );
		$view = self::load_view( $view, $args, $allow_theme_override );
		remove_filter( 'si_views_path', array( __CLASS__, 'addons_view_path' ) );
		return $view;
	}

	public static function load_addon_view_to_string( $view, $args, $allow_theme_override = true ) {
		ob_start();
		self::load_addon_view( $view, $args, $allow_theme_override );
		return ob_get_clean();
	}

	public static function addons_view_path() {
		return SA_ADDON_AUTO_BILLING_PATH . '/views/';
	}
}
