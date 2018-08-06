<?php

/**
 * Time_Tracking Controller
 *
 * @package Sprout_Invoice
 * @subpackage Time_Tracking
 */
class SI_Invoices_Recurring_Tasks extends SI_Invoices_Recurring {

	public static function init() {

		if ( self::DEBUG ) {
			add_action( 'admin_init', array( __CLASS__, 'maybe_create_new_invoices' ) );
		} else {
			add_action( self::CRON_HOOK, array( __CLASS__, 'maybe_create_new_invoices' ) );
		}

	}


	/////////////////////
	// Scheduled Task //
	/////////////////////

	public static function maybe_create_new_invoices() {

		if ( doing_action( 'si_maybe_create_new_invoices' ) ) {
			return;
		}

		do_action( 'si_maybe_create_new_invoices', current_time( 'timestamp' ) );

		$args = array(
			'post_type' => SI_Invoice::POST_TYPE,
			'post_status' => array_keys( SI_Invoice::get_statuses() ),
			'posts_per_page' => -1,
			'fields' => 'ids',
			'meta_query' => array(
				'relation' => 'AND',
				array(
					'key' => self::$meta_keys['is_recurring'],
					'value' => 1,
					'compare' => '=',
					),
				array(
					'key' => self::$meta_keys['clone_time'],
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

			// double check in case the meta query is being dumb
			$cloned_from = (int) $invoice->get_post_meta( self::$meta_keys['cloned_from'] );
			if ( $cloned_from ) {
				continue;
			}

			// double check in case the meta query is being dumb
			$next_check = (int) $invoice->get_post_meta( self::$meta_keys['clone_time'] );
			if ( ! $next_check || ( $next_check > current_time( 'timestamp' ) ) ) {
				continue;
			}

			// determine if the duration has been met
			$duration = self::get_duration( $invoice_id );
			if ( 0 !== $duration ) {

				// count children
				$duration_clones = count( self::get_child_clones( $invoice_id ) );

				// if duration is met, disable recurring.
				if ( $duration_clones >= $duration ) {
					self::set_as_not_recurring( $invoice_id );
					continue;
				}
			}

			$scheduled_cloned_time = self::get_clone_time( $invoice_id );

			// If not scheduled than don't generate
			if ( ! $scheduled_cloned_time ) {
				do_action( 'si_error', 'Could not generate invoice from master recurring invoice. No scheduled time.', $invoice_id );
				continue;
			}

			// Just in case the WP Query returns future invoices, or it was created by a previous task that's taking a long time.
			if ( $scheduled_cloned_time > current_time( 'timestamp' ) ) {
				do_action( 'si_error', 'Could not generate invoice from master recurring invoice. Future attempt.', $invoice_id );
				continue;
			}

			$cloned_post_id = self::generate_invoice( $invoice_id );
		}

	}

	public static function generate_invoice( $invoice_id ) {

		$scheduled_cloned_time = self::get_clone_time( $invoice_id );

		$cloned_post_id = self::clone_post( $invoice_id, SI_Invoice::STATUS_PENDING, SI_Invoice::POST_TYPE );
		$cloned_invoice = SI_Invoice::get_instance( $cloned_post_id );

		// Issue date is today.
		$cloned_invoice->set_issue_date( $scheduled_cloned_time );

		// Due date is in the future
		$due_date = apply_filters( 'si_new_recurring_invoice_due_date_in_days', 14 );
		$cloned_invoice->set_due_date( $scheduled_cloned_time + (DAY_IN_SECONDS * $due_date) );

		// reset totals
		$cloned_invoice->reset_totals();

		// save as child
		self::set_parent( $cloned_post_id, $invoice_id );

		// adjust the clone time after the next invoice
		self::set_clone_time( $invoice_id );

		do_action( 'si_recurring_invoice_created', $invoice_id, $cloned_post_id );

		// Send notification, new in v11
		$client = $cloned_invoice->get_client();
		if ( ! is_a( $client, 'SI_Client' ) ) {
			return false;
		}
		$client_users = $client->get_associated_users();
		if ( ! empty( $client_users ) && apply_filters( 'si_send_invoice_notifciation_for_newly_created_recurring_invoices', true ) ) {
			do_action( 'send_recurring_invoice', $cloned_invoice, $client_users );
		}

		do_action( 'si_recurring_invoice_created_post_send', $invoice_id, $cloned_post_id );

		return $cloned_post_id;
	}
}
