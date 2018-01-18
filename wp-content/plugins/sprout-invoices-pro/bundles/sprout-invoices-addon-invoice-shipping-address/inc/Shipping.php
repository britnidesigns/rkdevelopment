<?php

/**
 * @package SI_Shipping_Addy
 */
class SI_Shipping_Addy extends SI_Controller {
	const SHIP_META = '_ship_addy';
	const NONCE = '_ship_addy';

	public static function init() {
		add_action( 'sa_new_client', array( __CLASS__, 'maybe_save_shipping_with_client_creation' ), 10, 2 );

		//add_filter( 'si_afi_maybe_create_client', array( __CLASS__, 'maybe_add_shipping_args' ), 10, 2 );
	}

	public static function maybe_save_shipping_with_client_creation( SI_Client $client, $args = array() ) {
		if ( ! isset( $args['shipping_address'] ) && $args['address'] ) {
			$shipping_address = $args['address'];
		} elseif ( ! empty( $args['shipping_address'] ) ) {
			$shipping_address = $args['shipping_address'];
		}
		self::set_shipping_address( $client->get_id(), $shipping_address );
	}

	public static function maybe_add_shipping_args() {
		// data isn't passed
	}

	public static function shipping_fields( $doc_id = 0 ) {

		$doc_shipping_fields = self::get_shipping_address( $doc_id );

		$addy_fields = self::get_standard_address_fields( false );
		foreach ( $addy_fields as $key => $field ) {
			if ( false !== strpos( $key, 'name' ) ) {
				continue;
			}
			$shipping_fields[ 'shipping_' . $key ] = $field;
			$shipping_fields[ 'shipping_' . $key ]['weight'] = $field['weight'] + 500;
			$shipping_fields[ 'shipping_' . $key ]['label'] = sprintf( '%s %s', __( 'Shipping', 'sprout-invoices' ), $field['label'] );
			if ( isset( $doc_shipping_fields[ $key ] ) &&  '' !== $doc_shipping_fields[ $key ] ) {
				$shipping_fields[ 'shipping_' . $key ]['default'] = $doc_shipping_fields[ $key ];
			}
		}
		return $shipping_fields;
	}

	public static function get_shipping_address( $doc_id = 0 ) {
		if ( SI_Client::POST_TYPE === get_post_type( $doc_id ) ) {

			$address = get_post_meta( $doc_id, self::SHIP_META, true );

		} else {

			$address = get_post_meta( $doc_id, self::SHIP_META, true );

			if ( ! is_array( $address ) ) {
				$doc = si_get_doc_object( $doc_id );
				$client_id = $doc->get_client_id();
				if ( $client_id ) {
					// default to client
					$address = get_post_meta( $client_id, self::SHIP_META, true );
				}
			}
		}

		if ( ! is_array( $address ) ) {
			$address = array();
		}

		return $address;
	}

	public static function set_shipping_address( $address = array(), $doc_id = 0 ) {
		return update_post_meta( $doc_id, self::SHIP_META, $address );
	}


	public static function load_addon_view( $view, $args, $allow_theme_override = true ) {
		add_filter( 'si_views_path', array( __CLASS__, 'addons_view_path' ) );
		$view = self::load_view( $view, $args, $allow_theme_override );
		remove_filter( 'si_views_path', array( __CLASS__, 'addons_view_path' ) );
		return $view;
	}

	protected static function load_addon_view_to_string( $view, $args, $allow_theme_override = true ) {
		ob_start();
		self::load_addon_view( $view, $args, $allow_theme_override );
		return ob_get_clean();
	}

	public static function addons_view_path() {
		return SA_ADDON_SHIPPING_ADDRESS_PATH . '/views/';
	}
}
