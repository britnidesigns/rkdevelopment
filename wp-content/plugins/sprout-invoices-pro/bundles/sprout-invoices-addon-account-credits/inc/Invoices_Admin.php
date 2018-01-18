<?php

/**
 * Account_Credits Controller
 *
 * @package Sprout_Invoice
 * @subpackage Account_Credits
 */
class SI_Account_Credits_Invoices_Admin extends SI_Account_Credits {

	public static function init() {

		if ( is_admin() ) {
			add_action( 'si_payments_meta_box_pre', array( __CLASS__, 'import_credit' ) );
		}

	}

	public static function import_credit() {
		$invoice = SI_Invoice::get_instance( get_the_ID() );
		if ( ! is_a( $invoice, 'SI_Invoice' ) ) {
			return;
		}
		if ( 0.01 > $invoice->get_balance() ) {
			return;
		}
		$client_id = $invoice->get_client_id();
		$invoice_id = $invoice->get_invoice_id();

		$client_options = array();
		$args = array(
			'post_type' => SI_Client::POST_TYPE,
			'post_status' => 'publish',
			'posts_per_page' => -1,
			'fields' => 'ids',
		);
		$clients = get_posts( apply_filters( 'si_clients_select_get_posts_args', $args ) );
		foreach ( $clients as $client_id ) {
			$client_options[ $client_id ] = get_the_title( $client_id );
		}

		self::load_addon_view( 'admin/meta-boxes/invoices/credit-invoicing.php', array(
				'invoice' => $invoice,
				'invoice_id' => $invoice_id,
				'client_id' => $client_id,
				'client_options' => $client_options,
		), true );
	}
}
