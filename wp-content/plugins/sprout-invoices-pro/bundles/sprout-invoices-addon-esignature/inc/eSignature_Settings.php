<?php

/**
 * eSignature_Settings Controller
 *
 * @package Sprout_Invoice
 * @subpackage eSignature_Settings
 */
class eSignature_Settings extends eSignature_Controller {

	public static function init() {
		// meta boxes
		add_action( 'doc_information_meta_box_client_row_last', array( __CLASS__, 'meta_add_signature_protection' ) );
		add_action( 'si_save_line_items_meta_box', array( __CLASS__, 'save_signature_selection' ) );
	}


	public static function meta_add_signature_protection( $doc ) {
		self::load_addon_view( 'admin/force-signature', array(
				'force_signature' => self::is_signature_required( $doc->get_id() ),
				'fields' => self::meta_box_fields( $doc ),
		), false );
	}

	public static function save_signature_selection( $post_id = 0 ) {
		$doc_forced_signature = ( isset( $_POST['sa_force_signature_doc_forced_signature'] ) ) ? 'true' : 'false' ;
		self::save_doc_signature_required_option( $post_id, $doc_forced_signature );
	}

	public static function meta_box_fields( $doc ) {
		$fields = array();
		$fields['doc_forced_signature'] = array(
			'weight' => 20,
			'label' => __( 'Force a client signature.', 'sprout-invoices' ),
			'type' => 'checkbox',
			'default' => self::is_signature_required( $doc->get_id() ),
			'value' => '1',
		);
		$fields = apply_filters( 'si_force_signature_meta_fields', $fields );
		uasort( $fields, array( __CLASS__, 'sort_by_weight' ) );
		return $fields;
	}
}
