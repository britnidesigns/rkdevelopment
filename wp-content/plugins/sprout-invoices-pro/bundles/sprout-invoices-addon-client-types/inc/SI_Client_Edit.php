<?php

/**
 * Controller
 * Adds meta boxes to client admin.
 */
class SI_Client_Type_Edit extends SI_Client_Categories {

	public static function init() {
		add_filter( 'si_client_adv_form_fields', array( __CLASS__, 'add_type_option' ), 10, 2 );
		add_action( 'SI_Clients::save_meta_box_client_adv_information', array( __CLASS__, 'save_client_options' ) );
	}


	public static function add_type_option( $fields = array(), $client = 0 ) {

		$fields['client_type'] = array(
			'weight' => 0,
			'label' => __( 'Client Type' , 'sprout-invoices' ),
			'type' => 'select',
			'options' => self::get_client_types(),
			'default' => self::get_client_type( $client->get_id() ),
				'description' => __( 'Select the client type.' ),
			);

			return $fields;

	}

	/**
	 * Save client options on advanced meta box save action
	 * @param  integer $post_id
	 * @return
	 */
	public static function save_client_options( $post_id = 0 ) {
		$type = ( isset( $_POST['sa_metabox_client_type'] ) ) ? wp_unslash( sanitize_title( $_POST['sa_metabox_client_type'] ) ) : '' ;
		self::set_client_type( $post_id, $type );
	}
}
SI_Client_Type_Edit::init();
