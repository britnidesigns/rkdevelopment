<?php

/**
 * ApproveMe_Settings Controller
 *
 * @package Sprout_Invoice
 * @subpackage ApproveMe_Settings
 */
class ApproveMe_Settings extends ApproveMe_Controller {
	const DOC_ID = 'si_esig_aggreement_doc1';
	const DOC_ID_EST = 'si_esig_aggreement_doc_est';
	private static $agreement_doc;
	private static $agreement_doc_estimates;

	public static function init() {
		self::$agreement_doc = self::get_agreement_doc();
		self::$agreement_doc_estimates = self::get_agreement_doc_estimates();
		if ( is_admin() ) {

			// Register Settings
			add_filter( 'si_settings', array( __CLASS__, 'register_settings' ) );

			// meta boxes
			add_action( 'doc_information_meta_box_client_row_last', array( __CLASS__, 'add_agreement_option' ) );
			add_action( 'si_save_line_items_meta_box', array( __CLASS__, 'save_agreement_selection' ) );

		}
	}

	public static function get_agreement_doc() {
		$doc_id = get_option( self::DOC_ID, '' );
		return $doc_id;
	}

	public static function get_agreement_doc_estimates() {
		$doc_id = get_option( self::DOC_ID_EST, '' );
		return $doc_id;
	}

	public static function global_agreement_doc_enabled() {
		$doc_id = self::get_agreement_doc();
		return ( $doc_id > 0 );
	}

	public static function gloabl_agreement_doc_estimates_enabled() {
		$doc_id = self::get_agreement_doc_estimates();
		return ( $doc_id > 0 );
	}

	////////////
	// admin //
	////////////

	/**
	 * Hooked on init add the settings page and options.
	 *
	 */
	public static function register_settings( $settings = array() ) {

		$page_options = array();
		$page_options[0] = __( 'Select an Agreement Document (disabled)', 'sprout-invoices' );
		$page_options += self::esig_get_sad_pages();

		// Settings
		$settings['approveme'] = array(
				'title' => __( 'E-Signature Integration Settings', 'sprout-invoices' ),
				'weight' => 1010, // Add-on settings are 1000 plus
				'tab' => 'addons',
				'callback' => array( __CLASS__, 'display_integration_info_for_global_options' ),
				'settings' => array(
					self::DOC_ID => array(
						'label' => __( 'Invoices Agreement Doc', 'sprout-invoices' ),
						'option' => array(
							'type' => 'select',
							'options' => $page_options,
							'default' => self::get_agreement_doc(),
							'description' => __( 'Selecting an agreement (Stand Alone) document enables the use of a global contract for all invoices.', 'sprout-invoices' ),
							),
						),
					self::DOC_ID_EST => array(
						'label' => __( 'Estimates Agreement Doc', 'sprout-invoices' ),
						'option' => array(
							'type' => 'select',
							'options' => $page_options,
							'default' => self::get_agreement_doc_estimates(),
							'description' => __( 'Selecting an agreement (Stand Alone) document enables the use of a global contract for all estimates.', 'sprout-invoices' ),
							),
						),
					),
			);
		return $settings;
	}

	public static function display_integration_info_for_global_options() {
		printf( '<p>%s</p>', __( 'A global contract is pretty rad...it lets you set a "global contract" or "global agreement" for your Sprout Invoices documents. In short you can require all clients to sign a legal &amp; court recognized contract before completing their invoice payment or accepting an estimate. You can also attach an individual document to individual invoices/estimates when creating them.', 'sprout-invoices' ) );
	}

	/////////////////
	// Meta boxes //
	/////////////////

	public static function add_agreement_option( $doc ) {
		$agreements = array();
		$agreements[ PHP_INT_MAX ] = __( 'Select an Agreement Document (disabled)', 'sprout-invoices' );
		$agreements += self::esig_get_sad_pages();

		$default = self::get_doc_agreement_id( $doc->get_id() );
		self::load_addon_view( 'admin/select-doc-agreement.php', array(
				'agreements' => $agreements,
				'default' => $default,
		), false );
	}

	public static function save_agreement_selection( $post_id = 0 ) {
		$doc_agreement = ( isset( $_POST['doc_agreement'] ) ) ? $_POST['doc_agreement'] : PHP_INT_MAX ;
		self::save_doc_agreement_id( $post_id, $doc_agreement );
	}
}
