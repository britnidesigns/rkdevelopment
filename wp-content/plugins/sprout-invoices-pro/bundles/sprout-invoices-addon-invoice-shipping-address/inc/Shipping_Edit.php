<?php

/**
 * SI_Shipping_Addy_Edit Controller
 *
 * Edit the address
 *
 * @package SI_Controller
 * @subpackage SI_Shipping_Addy_Edit
 */
class SI_Shipping_Addy_Edit extends SI_Shipping_Addy {

	public static function init() {

		// hook on save
		add_action( 'pre_si_invoice_view', array( __CLASS__, 'maybe_save_edit_shipping' ) );
		add_action( 'pre_si_estimate_view', array( __CLASS__, 'maybe_save_edit_shipping' ) );

		// add pane
		add_action( 'si_invoice_outer_doc_wrap', array( __CLASS__, 'show_edit_pane' ) );
	}

	public static function show_edit_pane() {
		$client = si_get_invoice_client();
		if ( ! is_a( $client, 'SI_Client' ) ) {
			return;
		}
		self::load_addon_view( 'public/edit-shipping', array(
				'shipping_address' => self::shipping_fields( get_the_id() ),
		) );
	}

	public static function maybe_save_edit_shipping() {

		if ( ! isset( $_POST['edit_shipping'] ) ) {
			return;
		}
		if ( ! wp_verify_nonce( $_POST['edit_shipping'], self::NONCE ) ) {
			return;
		}

		$doc_id = get_the_id();

		$address = array(
			'street' => isset( $_POST['sa_billing_shipping_street'] ) ? $_POST['sa_billing_shipping_street'] : '',
			'city' => isset( $_POST['sa_billing_shipping_city'] ) ? $_POST['sa_billing_shipping_city'] : '',
			'zone' => isset( $_POST['sa_billing_shipping_zone'] ) ? $_POST['sa_billing_shipping_zone'] : '',
			'postal_code' => isset( $_POST['sa_billing_shipping_postal_code'] ) ? $_POST['sa_billing_shipping_postal_code'] : '',
			'country' => isset( $_POST['sa_billing_shipping_country'] ) ? $_POST['sa_billing_shipping_country'] : '',
		);

		if ( isset( $_POST['update_client'] ) ) {

			$doc = si_get_doc_object( $doc_id );
			$client_id = $doc->get_client_id();
			self::set_shipping_address( $address, $client_id );
		}

		self::set_shipping_address( $address, $doc_id );

	}
}
