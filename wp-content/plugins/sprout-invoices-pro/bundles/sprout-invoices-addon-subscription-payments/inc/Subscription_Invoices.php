<?php

/**
 * Subscription_Invoices Controller
 *
 * @package Sprout_Invoice
 * @subpackage Subscription_Payments
 */
class Subscription_Invoices extends SI_Subscription_Payments {

	public static function init() {
		add_action( 'si_paypal_recurring_payment_profile_created', array( __CLASS__, 'set_payment_reciept_schedule' ) );
		add_action( 'si_stripe_recurring_payment_profile_created', array( __CLASS__, 'set_payment_reciept_schedule' ) );
		add_action( self::CRON_HOOK, array( __CLASS__, 'create_invoice_reciepts' ) );

	}

	public static function set_payment_reciept_schedule( $payment_id ) {
		$payment = SI_Payment::get_instance( $payment_id );
		if ( ! $payment->is_active() ) {
			return;
		}

		$invoice_id = $payment->get_invoice_id();

		self::schedule_next_reciept( $invoice_id, current_time( 'timestamp' ) );
	}


	/////////////////////
	// Scheduled Task //
	/////////////////////

	public static function create_invoice_reciepts() {
		$args = array(
			'post_type' => SI_Invoice::POST_TYPE,
			'post_status' => array_keys( SI_Invoice::get_statuses() ),
			'posts_per_page' => -1,
			'fields' => 'ids',
			'meta_query' => array(
				'relation' => 'AND',
				array(
					'key' => '_si_has_subscription_payments',
					'value' => 1,
					'compare' => '=',
					),
				array(
					'key' => self::$meta_keys['next_time'],
					'value' => array(
							strtotime( 'Last year' ),
							current_time( 'timestamp' ),
							),
					'compare' => 'BETWEEN',
					),
				array(
					'key' => self::$meta_keys['cloned_from'],
					'compare' => 'NOT EXISTS',
					)
				),
		);
		$invoice_ids = get_posts( $args );

		foreach ( $invoice_ids as $invoice_id ) {
			$invoice = SI_Invoice::get_instance( $invoice_id );
			$recurring_payment = SI_Payment_Processors::get_recurring_payment( $invoice );
			if ( ! $recurring_payment ) {
				continue;
			}
			// check if sub is active in order to create a paid invoice.
			// TODO may not create the last invoice if term is up and this generation is after.
			if ( $recurring_payment->is_active( true ) ) {
				self::create_paid_invoice_and_payment( $invoice );
			} else {
				// cancel
				$recurring_payment->cancel();
			}
		}
	}

	////////////////////
	// Create Reciept //
	////////////////////

	public static function create_paid_invoice_and_payment( SI_Invoice $invoice ) {
		$invoice_id = $invoice->get_id();

		$reciept_id = self::clone_post( $invoice_id, SI_Invoice::STATUS_PAID, SI_Invoice::POST_TYPE );
		$reciept = SI_Invoice::get_instance( $reciept_id );

		// payment amount is the balance of the cloned invoice.
		$payment_amount = $reciept->get_calculated_total();

		// Create a payment
		SI_Admin_Payment::create_admin_payment( $reciept_id, $payment_amount, '', 'Now', __( 'This payment was automatically added to settle a subscription payment.', 'sprout-invoices' ) );

		// Issue date is today.
		$reciept->set_issue_date( time() );

		// adjust the clone time for the next receipt
		self::schedule_next_reciept( $invoice_id, current_time( 'timestamp' ) );

		self::set_parent( $reciept_id, $invoice_id );
		do_action( 'si_subscription_invoice_reciept_created', $invoice_id, $reciept_id );
	}


	public static function schedule_next_reciept( $invoice_id = 0, $time = 0 ) {
		$invoice = SI_Invoice::get_instance( $invoice_id );
		if ( ! is_a( $invoice, 'SI_Invoice' ) ) {
			return 0;
		}

		$is_sub = self::has_subscription_payment( $invoice_id );
		if ( ! $is_sub ) {
			$the_next_time = 0;
		}

		$start_time = 0;
		if ( ! $start_time ) {
			$start_time = current_time( 'timestamp' );
		}

		$frequency = self::get_term( $invoice_id );

		switch ( $frequency ) {
			case 'day':
				$the_next_time = strtotime( '+1 Day', $start_time );
				break;
			case 'week':
				$the_next_time = strtotime( '+1 Week', $start_time );
				break;
			case 'month':
				$the_next_time = strtotime( '+1 Month', $start_time );
				break;
			case 'year':
				$the_next_time = strtotime( '+1 Year', $start_time );
				break;

			default:
				$the_next_time = 0;
				break;
		}

		$invoice->save_post_meta( array(
			self::$meta_keys['next_time'] => $the_next_time,
		) );
		return $the_next_time;
	}
}
