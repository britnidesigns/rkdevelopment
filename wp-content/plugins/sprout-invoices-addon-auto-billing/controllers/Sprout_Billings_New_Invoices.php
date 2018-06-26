<?php

/**
 * Auto Billing New Invoices Controller
 *
 *
 * @package SI_Sprout_Billings_New_Invoices
 */
class SI_Sprout_Billings_New_Invoices extends SI_Sprout_Billings {

	public static function init() {

		add_action( 'sa_new_invoice', array( __CLASS__, 'maybe_auto_pay_new_invoice' ) );
		add_action( 'si_recurring_invoice_created', array( __CLASS__, 'maybe_charge_new_recurring_invoice' ) );

	}

	public static function maybe_charge_new_recurring_invoice( $invoice_id ) {
		$invoice = SI_Invoice::get_instance( $invoice_id );
		self::maybe_auto_pay_new_invoice( $invoice );
	}

	public static function maybe_auto_pay_new_invoice( $invoice ) {
		$invoice_id = $invoice->get_id();

		$disabled = self::is_auto_bill_disabled( $invoice_id );
		if ( $disabled ) {
			return;
		}

		$client_id = self::get_client_id( $invoice_id );
		if ( ! self::can_autocharge_client( $client_id ) ) {
			return;
		}

		$auto_pay_date = (int) self::get_client_autopay_date( $client_id );

		if ( 0 === $auto_pay_date ) {
			self::charge_and_record( $invoice_id, $client_id );
			// move along to the next client
			return;
		}

		$todays_date = date( 'j', current_time( 'timestamp' ) );

		if ( (int) $todays_date === (int) $auto_pay_date ) {
			self::charge_and_record( $invoice_id, $client_id );
			// don't stop...check for last day of the month
		}

		$last_day_of_month = date( 't', current_time( 'timestamp' ) );

		// if today is the last day of the month process
		// all invoices with bill dates set to after the
		// last day of the month.
		if ( (int) $last_day_of_month === (int) $todays_date && (int) $auto_pay_date > (int) $last_day_of_month ) {
			self::charge_and_record( $invoice_id, $client_id );
		}

		return;

	}

	public static function charge_and_record( $invoice_id, $client_id ) {
		$response = self::charge_invoice_balance( $invoice_id, $client_id );
		$record_message = ( ! is_numeric( $response ) ) ?  __( 'Auto Payment Failed on Invoice: %s.' , 'sprout-invoices' ) : __( 'Auto Payment Succeeded on Invoice: %s.' , 'sprout-invoices' );
		do_action( 'si_new_record',
			$response,
			self::RECORD,
			$client_id,
			sprintf( $record_message, (int) $invoice_id ),
			0,
			false
		);
	}
}
