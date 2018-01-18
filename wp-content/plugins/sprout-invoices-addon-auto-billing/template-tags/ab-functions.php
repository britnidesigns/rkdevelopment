<?php

if ( ! function_exists( 'si_ab_get_clients_outstanding_balance' ) ) :
	/**
	 * Get the client's outstanding balance.
	 * @param  integer $client_id
	 * @return float
	 */
	function si_ab_get_clients_outstanding_balance( $client_id = 0 ) {
		if ( ! $client_id ) {
			global $post;
			$id = $post->ID;
			if ( SI_Invoice::POST_TYPE === get_post_type( $id ) ) {
				$client_id = SI_Sprout_Billings::get_client_id( $id );
			}
		}
		if ( ! $client_id ) {
			return false;
		}
		$client = SI_Client::get_instance( $client_id );
		$invoices = $client->get_invoices();
		$balance = 0.00;
		foreach ( $invoices as $invoice_id ) {
			if ( ! in_array( get_post_status( $invoice_id ), array( SI_Invoice::STATUS_PENDING, SI_Invoice::STATUS_PARTIAL ) ) ) {
				continue;
			}
			$invoice = SI_Invoice::get_instance( $invoice_id );
			$balance += $invoice->get_balance();
		}
		return apply_filters( 'si_ab_get_clients_outstanding_balance', $balance, $client_id );
	}
endif;

if ( ! function_exists( 'si_ab_client_has_outstanding_balance' ) ) :
	/**
	 * Does the client have an outstanding balance
	 * @param  integer $client_id
	 * @return bool
	 */
	function si_ab_client_has_outstanding_balance( $client_id = 0 ) {
		$balance = si_ab_get_clients_outstanding_balance( $client_id );
		$answer = ( $balance > 0.00 );
		return apply_filters( 'si_ab_client_has_outstanding_balance', $answer, $client_id );
	}
endif;


if ( ! function_exists( 'si_ab_can_auto_bill_client' ) ) :
	/**
	 * Can the client be auto-billed when an invoice is created.
	 * Agreed to the terms
	 * @param  integer $client_id
	 * @return bool
	 */
	function si_ab_can_auto_bill_client( $client_id = 0 ) {
		if ( ! $client_id ) {
			global $post;
			$id = $post->ID;
			if ( SI_Invoice::POST_TYPE === get_post_type( $id ) ) {
				$client_id = SI_Sprout_Billings::get_client_id( $id );
			}
		}
		if ( ! $client_id ) {
			return false;
		}
		$answer = SI_Sprout_Billings::can_autocharge_client( $client_id );
		return apply_filters( 'si_ab_can_auto_bill_client', $answer, $client_id );
	}
endif;



if ( ! function_exists( 'si_ab_can_autocharge_client' ) ) :
	/**
	 * Can the client be charged with a saved payment profile.
	 * @param  integer $client_id
	 * @return bool
	 */
	function si_ab_can_autocharge_client( $client_id = 0 ) {
		if ( ! $client_id ) {
			global $post;
			$id = $post->ID;
			if ( SI_Invoice::POST_TYPE === get_post_type( $id ) ) {
				$client_id = SI_Sprout_Billings::get_client_id( $id );
			}
		}
		if ( ! $client_id ) {
			return false;
		}
		$answer = SI_Sprout_Billings::can_autocharge_client( $client_id );
		return apply_filters( 'si_ab_can_autocharge_client', $answer, $client_id );
	}
endif;
