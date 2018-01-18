<?php

/**
 * Auto Billing Cron Controller
 *
 *
 * @package SI_Sprout_Billings_Cron
 */
class SI_Sprout_Billings_Tasks extends SI_Sprout_Billings {

	public static function init() {

		if ( self::DEBUG ) {
			add_action( 'init', array( __CLASS__, 'maybe_process_autopayments' ) );
		} else {
			add_action( self::CRON_HOOK, array( __CLASS__, 'maybe_process_autopayments' ) );
		}
	}

	//////////////////////
	// Autopay Invoices //
	//////////////////////

	public static function maybe_process_autopayments() {

		// Query all clients with profile ids
		// PAYMENT_PROFILE_ID

		$args = array(
			'post_type' => SI_Client::POST_TYPE,
			'posts_per_page' => -1,
			'fields' => 'ids',
			'meta_query' => array(
				array(
					'key' => 'sc_allow_charging',
					'compare' => 'EXISTS',
					),
				),
		);

		$autopay_clients = get_posts( $args );

		if ( empty( $autopay_clients ) ) {
			// no clients with autopay.
			return;
		}

		foreach ( $autopay_clients as $client_id ) {
			if ( ! self::can_autocharge_client( $client_id ) ) {
				continue;
			}
			$auto_pay_date = (int) self::get_client_autopay_date( $client_id );

			if ( 0 === $auto_pay_date ) {
				self::maybe_attempt_autopay_clients_overdue_invoices( $client_id );
				// move along to the next client
				continue;
			}

			$todays_date = date( 'j', current_time( 'timestamp' ) );

			if ( (int) $todays_date === (int) $auto_pay_date ) {
				self::maybe_attempt_autopay_on_all_client_invoices( $client_id );
				// don't stop...check for last day of the month
			}

			$last_day_of_month = date( 't', current_time( 'timestamp' ) );

			// if today is the last day of the month process
			// all invoices with bill dates set to after the
			// last day of the month.
			if ( (int) $last_day_of_month === (int) $todays_date && (int) $auto_pay_date > (int) $last_day_of_month ) {
				self::maybe_attempt_autopay_on_all_client_invoices( $client_id );
			}
			continue;
		}

	}

	public static function maybe_attempt_autopay_on_all_client_invoices( $client_id = 0 ) {

		$args = array(
			'post_type' => SI_Invoice::POST_TYPE,
			'post_status' => array( SI_Invoice::STATUS_PENDING, SI_Invoice::STATUS_PARTIAL ),
			'posts_per_page' => -1,
			'fields' => 'ids',
			'meta_query' => array(
				array(
					'key' => '_client_id',
					'value' => $client_id,
					),
				),
		);

		$outstanding_invoices = get_posts( $args );

		if ( empty( $outstanding_invoices ) ) {
			// no overdue invoices.
			return;
		}

		foreach ( $outstanding_invoices as $invoice_id ) {
			self::maybe_attempt_autopay( $invoice_id );
		}
	}

	public static function maybe_attempt_autopay_clients_overdue_invoices( $client_id = 0 ) {

		$args = array(
			'post_type' => SI_Invoice::POST_TYPE,
			'post_status' => array( SI_Invoice::STATUS_PENDING, SI_Invoice::STATUS_PARTIAL ),
			'posts_per_page' => -1,
			'fields' => 'ids',
			'meta_query' => array(
				array(
					'key' => '_due_date',
					'value' => array(
						0,
						current_time( 'timestamp' ),
						), // yesterday
					'compare' => 'BETWEEN',
					),
				array(
					'key' => '_client_id',
					'value' => $client_id,
					),
				),
		);

		$recently_overdue = get_posts( $args );

		if ( empty( $recently_overdue ) ) {
			// no overdue invoices.
			return;
		}

		foreach ( $recently_overdue as $invoice_id ) {
			self::maybe_attempt_autopay( $invoice_id );
		}
	}
}
