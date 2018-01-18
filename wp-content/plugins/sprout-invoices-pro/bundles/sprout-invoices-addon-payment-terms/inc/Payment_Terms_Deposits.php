<?php

/**
 * SI_Payment_Terms_Fees Controller
 *
 * Change deposit to the payment terms due, plus the next payment due.
 *
 * @package SI_Payment_Terms
 * @subpackage SI_Payment_Terms_Deposits
 */
class SI_Payment_Terms_Deposits extends SI_Payment_Terms {

	public static function init() {

		// Set deposit to at least the terms set
		if ( apply_filters( 'si_payment_terms_change_deposit', true ) ) {

			add_filter( 'pre_si_invoice_view', array( __CLASS__, 'maybe_change_deposit_on_view' ), 10, 2 );
			add_filter( 'si_payment_term_created', array( __CLASS__, 'maybe_change_deposit' ), 10, 2 );
			add_filter( 'si_payment_term_deleted', array( __CLASS__, 'maybe_change_deposit' ), 10, 2 );
		}
	}

	public static function maybe_change_deposit_on_view() {
		$invoice_id = get_the_id();
		if ( SI_Invoice::POST_TYPE !== get_post_type( $invoice_id ) ) {
			return;
		}
		self::maybe_change_deposit( 0, $invoice_id );
	}

	/**
	 * Change deposit to the payment terms due, plus the next payment due.
	 * @param  int $pt
	 * @param  int $invoice_id
	 * @return null         Change the deposit
	 */
	public static function maybe_change_deposit( $pt, $invoice_id ) {

		$invoice = SI_Invoice::get_instance( $invoice_id );
		if ( ! is_a( $invoice, 'SI_Invoice' ) ) {
			return;
		}

		$payment_terms = self::get_sorted_payment_terms( $invoice_id );

		if ( empty( $payment_terms ) ) {
			return;
		}

		$amount_due = (float) 0.00;
		$stop_sign = false; // only want to tally one future term.
		$old_term_found = false; // only want to tally one future term.
		foreach ( $payment_terms as $data ) {

			// if the late fee is complete than there's nothing to send.
			if ( 'true' === $data['complete'] ) {
				continue;
			}

			// Take one future term
			if ( current_time( 'timestamp' ) < (int) $data['time'] ) {
				if ( $stop_sign ) {
					continue;
				}
				// set to true, so next time around another future term isn't tallied
				$stop_sign = true;
			} else {
				$old_term_found = true;
			}

			// add up what's owed
			$amount_due += floatval( $data['balance'] );
		}

		if ( $old_term_found && ! $stop_sign ) {
			return;
		}

		$invoice->set_deposit( round( (float) $amount_due, 2 ) );
	}
}
