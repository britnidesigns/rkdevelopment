<?php

/**
 * Woo_VAT Controller
 *
 * @package Woo_VAT
 */
class Woo_Tools extends SI_Controller  {

	public static function init() {
		// save vat option at checkout, when using SI as payment
		add_action( 'si_woocommerce_payment_end', array( __CLASS__, 'save_vat_number' ), 10, 2 );

		// show vat on invoice
		add_action( 'si_document_vcards', array( __CLASS__, 'add_vat_number_to_doc' ) );
	}

	public static function get_vat( SI_Client $client ) {
		return $client->get_post_meta( '_vat' );
	}

	public static function set_vat( SI_Client $client, $vat = '' ) {
		return $client->save_post_meta( array( '_vat' => $vat ) );
	}

	//////////////
	// Save vat //
	//////////////

	public static function save_vat_number( $order_id, $invoice_id ) {
		$invoice = SI_Invoice::get_instance( $invoice_id );
		if ( ! is_a( $invoice, 'SI_Invoice' ) ) {
			return;
		}
		$client = $invoice->get_client();
		if ( ! is_a( $client, 'SI_Client' ) ) {
			return;
		}
		$order = wc_get_order( $order_id );
		$vat = get_user_meta( $order->get_user_id(), 'vat_number', true );
		self::set_vat( $client, $vat );
	}


	/////////////////
	// Invoice VAT //
	/////////////////

	public static function add_vat_number_to_doc() {
		$vat_number = si_wc_get_client_vat();
		if ( '' !== $vat_number ) {
			printf( '<dl class="client_vat"><dt><span class="dt_heading">%1$s</span></dt><dd>%2$s</dd></dl>', __( 'VAT', 'sprout-invoices' ), $vat_number );
		}
	}
}
