<?php

/**
 * Auto Billing Checkout Controller
 *
 *
 * @package Sprout_Billings_Client_Set_Payment
 */
class SI_Sprout_Billings_Client_Set_Payment extends SI_Sprout_Billings {

	public static function init() {
		// remove filter but wait until after si is loaded.
		add_action( 'sprout_invoices_loaded', array( __CLASS__, 'remove_client_set_price' ), 110 );

		add_action( 'si_credit_card_form_controls', array( __CLASS__, 'client_set_price_view' ) );

		// the actual action of the price change is handled by the payment processor.
	}

	public static function remove_client_set_price() {
		remove_action( 'si_doc_actions', array( 'Client_Set_Price', 'client_set_price_view' ) );
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
		$deposit = $invoice->get_deposit();
		if ( 0.01 > (float) $deposit ) {
			$deposit = 1.00;
		}

		self::load_addon_view( 'checkout/price-option', array(
			'deposit' => $deposit,
			'balance' => $balance,
		) );
	}
}
