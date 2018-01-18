<?php

/**
 * SI_Shipping_Addy_Display Controller
 *
 * Edit the address
 *
 * @package SI_Controller
 * @subpackage SI_Shipping_Addy_Display
 */
class SI_Shipping_Addy_Display extends SI_Shipping_Addy {

	public static function init() {
		add_action( 'si_document_vcards', array( __CLASS__, 'show_shipping_v_card' ) );
	}

	public static function show_shipping_v_card() {
		$client = si_get_invoice_client();
		if ( ! is_a( $client, 'SI_Client' ) ) {
			return;
		}
		self::load_addon_view( 'public/show-shipping', array(
				'shipping_address' => self::get_shipping_address( get_the_id() ),
		) );
	}
}
