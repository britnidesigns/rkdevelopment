<?php

/**
 * SI_Payment_Terms_Fees Controller
 *
 * Handles auto payments
 *
 * @package SI_Payment_Terms
 * @subpackage SI_Payment_Terms_Billings
 */
class SI_Payment_Terms_Billings extends SI_Payment_Terms {
	const LAST_CHECK_OPTION = 'si_payment_terms_last_check_v2';

	public static function init() {
		add_action( self::CRON_HOOK, array( __CLASS__, 'check_for_due_terms' ) );

		add_action( 'si_payment_terms_metabox_start', array( __CLASS__, 'add_message_below_term_options' ) );
	}

	public static function add_message_below_term_options( $invoice_id = 0 ) {

		$invoice = SI_Invoice::get_instance( $invoice_id );
		if ( ! is_a( $invoice, 'SI_Invoice' ) ) {
			return;
		}

		$message = __( 'Sprout Billings will attempt to automatically capture any payment terms that are past due.', 'sprout-invoices' );

		// if not set to autocharge
		$client_id = $invoice->get_client_id();
		if ( ! SI_Sprout_Billings::can_autocharge_client( $client_id ) ) {
			$message = __( 'This client does not have an auto billing agreement.', 'sprout-invoices' );
		}

		printf( '<p class="si_notice">%s</p>', $message );
	}

	public static function check_for_due_terms() {

		if ( ! class_exists( 'SI_Sprout_Billings' ) ) {
			return;
		}

		$last_check = get_option( self::LAST_CHECK_OPTION, strtotime( 'Last Month' ) ) - ( DAY_IN_SECONDS );
		$midnight_yesterday = (int) strtotime( 'today', current_time( 'timestamp' ) ) - 1;

		// Get terms that are due today and within the next three days.
		$term_ids = SI_Payment_Term::get_terms_by_date( $last_check, $midnight_yesterday );
		foreach ( $term_ids as $term_id ) {
			self::maybe_auto_bill_terms( $term_id );
		}

		update_option( self::LAST_CHECK_OPTION, current_time( 'timestamp' ) );
	}

	public static function maybe_auto_bill_terms( $term_id ) {

		$payment_term = SI_Record::get_instance( $term_id );
		$invoice_id = $payment_term->get_associate_id();

		$invoice = SI_Invoice::get_instance( $invoice_id );
		if ( ! is_a( $invoice, 'SI_Invoice' ) ) {
			return;
		}

		$client = $invoice->get_client();
		if ( ! is_a( $client, 'SI_Client' ) ) {
			return;
		}

		$client_id = $client->get_id();

		if ( ! SI_Sprout_Billings::can_autocharge_client( $client_id ) ) {
			return;
		}

		// get all payment terms
		$payment_terms = self::get_sorted_payment_terms( $invoice_id );

		if ( empty( $payment_terms ) ) {
			return;
		}

		$amount_due = (float) 0.00;
		foreach ( $payment_terms as $data ) {

			// if the late fee is complete than there's nothing to send.
			if ( 'true' === $data['complete'] ) {
				continue;
			}

			// Don't tally payments that are not due.
			if ( current_time( 'timestamp' ) < (int) $data['time'] ) {
				continue;
			}

			// add up what's owed
			$amount_due += floatval( $data['balance'] );
		}

		// set deposit so auto payments use it.
		$invoice->set_deposit( round( (float) $amount_due, 2 ) );

		// attempt charge on amount due.
		SI_Sprout_Billings::maybe_attempt_autopay( $invoice_id, $client_id );

	}
}
