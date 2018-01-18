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
		$client_id = self::get_client_id( $invoice_id );
		if ( ! self::can_autocharge_client( $client_id ) ) {
			return;
		}

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
