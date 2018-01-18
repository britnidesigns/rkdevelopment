<?php

/**
 * @package SI_Shipping_Addy
 */
class SI_Shipping_Options extends SI_Shipping_Addy {

	public static function init() {
		// add fields
		add_filter( 'si_client_form_fields', array( __CLASS__, 'add_shipping' ), 10, 2 );

		// save
		add_filter( 'SI_Clients::save_meta_box_client_information', array( __CLASS__, 'save_shipping' ) );
	}

	public static function add_shipping( $fields = array(), $client = 0 ) {
		if ( ! is_a( $client, 'SI_Client' ) ) {
			return $fields;
		}
		$shipping_fields = self::shipping_fields( $client->get_id() );
		$fields = array_merge( $fields, $shipping_fields );
		return $fields;
	}

	public static function save_shipping( $client_id = 0 ) {
		if ( SI_Client::POST_TYPE !== get_post_type( $client_id ) ) {
			return;
		}
		$address = array(
			'street' => isset( $_POST['sa_metabox_shipping_street'] ) ? $_POST['sa_metabox_shipping_street'] : '',
			'city' => isset( $_POST['sa_metabox_shipping_city'] ) ? $_POST['sa_metabox_shipping_city'] : '',
			'zone' => isset( $_POST['sa_metabox_shipping_zone'] ) ? $_POST['sa_metabox_shipping_zone'] : '',
			'postal_code' => isset( $_POST['sa_metabox_shipping_postal_code'] ) ? $_POST['sa_metabox_shipping_postal_code'] : '',
			'country' => isset( $_POST['sa_metabox_shipping_country'] ) ? $_POST['sa_metabox_shipping_country'] : '',
		);

		self::set_shipping_address( $address, $client_id );
	}
}
