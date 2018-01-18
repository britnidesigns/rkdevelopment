<?php

/**
 * ApproveMe_Controller Controller
 *
 * @package Sprout_Invoice
 * @subpackage ApproveMe_Controller
 */
class ApproveMe_Controller extends SI_Controller {
	const RECORD = 'si_approveme';
	const DOC_ID_META = '_si_esig_aggreement_doc';
	const DOC_SIGNED = '_si_esig_aggreement_signed_v4';
	const DOC_SIG_ID = '_si_esig_aggreement_signed_id_v4';
	const COOKIE = 'si_esig_id';

	public static function init() {
		add_action( 'esig_document_complate', array( __CLASS__, 'esig_signature_after' ), 10, 1 );
		add_action( 'esig_document_complete', array( __CLASS__, 'esig_signature_after' ), 10, 1 );

		add_action( 'pre_si_estimate_view', array( __CLASS__, 'store_doc_id' ) );
		add_action( 'pre_si_invoice_view', array( __CLASS__, 'store_doc_id' ) );

		add_action( 'si_doc_actions_pre', array( __CLASS__, 'signature_required_button' ) );

		add_action( 'si_signature_button', array( __CLASS__, 'signature_required_button' ) );

		add_action( 'si_head', array( __CLASS__, 'maybe_remove_payment_button' ) );

		add_action( 'si_head', array( __CLASS__, 'add_esig_integration_css' ) );

		add_action( 'si_cloned_post', array( __CLASS__, 'set_default_when_new_invoice_is_cloned' ), 10, 3 );

		add_action( 'sa_new_invoice', array( __CLASS__, 'set_default_when_new_invoice_is_created' ), 10, 2 );
	}

	public static function maybe_remove_payment_button() {
		$doc_id = get_the_id();
		$required = self::is_signature_required( $doc_id );
		if ( ! $required ) {
			return;
		}
		remove_action( 'si_invoice_payment_button', array( 'SI_Invoices_Template', 'show_payment_options' ), 100, 2 );
	}

	public static function signature_required_button() {
		$doc_id = get_the_id();

		$signed = self::is_doc_agreement_signed( $doc_id );
		if ( $signed ) {
			self::signed_button_view( $doc_id );
			return;
		}

		$required = self::is_signature_required( $doc_id );
		if ( ! $required ) {
			return;
		}

		$agreement_page_id = self::get_doc_agreement_id( $doc_id );
		self::signature_required_button_view( $agreement_page_id );
		return;
	}

	public static function signed_button_view( $doc_id = 0 ) {
		$esig_setting = new WP_E_Setting();
		$page_id = $esig_setting->get_generic( 'default_display_page' );
		$signed_id = self::get_doc_agreement_sig_id( $doc_id );

		if ( ! $signed_id ) {
			_e( 'Signature not found.', 'sprout-invoices' );
			return;
		}

		$preview_url = 'javascript:void(0)';
		if ( current_user_can( 'edit_post', $page_id ) ) {
			$preview_url = esc_url( add_query_arg( array( 'esigpreview' => 1, 'document_id' => $signed_id ), get_permalink( $page_id ) ) );
		}

		ob_start();
		?>
			<a href="<?php echo $preview_url ?>" id="sign_doc_agreement" class="button signature_preview_button" rel="nofollow"><?php _e( 'Signed', 'sprout-invoices' ) ?></a>
		<?php
		$button = ob_get_clean();
		print apply_filters( 'si_signature_required_button', $button, $doc_id, $preview_url );
	}

	public static function signature_required_button_view( $agreement_page_id = 0 ) {
		$url = get_permalink( $agreement_page_id );
		ob_start();
		?>
			<style type="text/css">
				.button.status_change {
				    display: none;
				}
				.button.status_change[data-status-change='decline'] {
				    display: inline-block;
				}
			</style>
			<a href="<?php echo $url ?>" id="sign_doc_agreement" class="button signature_button" rel="nofollow"><?php _e( 'Signature Required', 'sprout-invoices' ) ?></a>
		<?php
		$button = ob_get_clean();
		print apply_filters( 'si_signature_required_button', $button, $agreement_page_id, $url );
	}

	public static function add_esig_integration_css() {
		ob_start();
		?>
			<style type="text/css">
				.button.signature_button {
					background-color: #B43D3D;
					color: #FFFFFF;
				}
				.button.signature_preview_button {
					background-color: #78B43D;
					color: #FFFFFF;
				}
				.button.signature_preview_button:before,
				.button.signature_button:before {
					font-family: 'dashicons';
					vertical-align: middle;
    				margin-right: 4px;
    				content: "\f147";
				}
				.button.signature_button:before {
    				content: "\f534";
				}
			</style>
		<?php
		$css = ob_get_clean();
		print apply_filters( 'add_esig_integration_css', $css );
	}


	public static function esig_signature_after( $args = array() ) {
		$sad_doc_id = $args['sad_doc_id'];
		$signed_page_id = self::get_sad_page_id_from_document_id( $sad_doc_id );

		$doc_id = self::get_doc_id_being_signed();
		$agreement_page_id = self::get_doc_agreement_id( $doc_id );

		if ( (int) $agreement_page_id !== (int) $signed_page_id ) {
			return;
		}

		if ( SI_Estimate::POST_TYPE === get_post_type( $doc_id ) ) {
			$estimate = SI_Estimate::get_instance( $doc_id );
			$estimate->set_approved();
			do_action( 'doc_status_changed', $estimate, $args );
		}

		self::save_doc_agreement_signed_time( $doc_id );
		self::save_doc_agreement_sig_args( $doc_id, $args );

		$url = add_query_arg( array( 'signed' => time() ), get_permalink( $doc_id ) );
		wp_redirect( $url );
		exit();
	}

	public static function store_doc_id() {
		if ( ! SI_WPES_USE_COOKIES ) {
			self::store_transient();
		} elseif ( ! headers_sent() ) {
			setcookie( self::COOKIE, get_the_id(), time() + ( 60 * 60 ), COOKIEPATH, COOKIE_DOMAIN );
		}
	}

	public static function get_transient() {
		$cache_key = '_si_approveme_transient_'.get_the_id().self::get_user_ip();
		return get_transient( $cache_key );
	}

	public static function store_transient() {
		$transient_id = self::get_transient();
		if ( ! $transient_id ) {
			$cache_key = '_si_approveme_transient_'.get_the_id().self::get_user_ip();
			set_transient( $cache_key, get_the_id(), 60 * 15 );
		}
	}

	public static function get_doc_id_being_signed() {
		$doc_id = 0;
		if ( isset( $_COOKIE[ self::COOKIE ] ) ) {
			$doc_id = $_COOKIE[ self::COOKIE ];
		}
		return $doc_id;
	}


	//////////
	// Meta //
	//////////

	public static function get_doc_agreement_signed_time( $doc_id = 0 ) {
		$time = (int) get_post_meta( $doc_id, self::DOC_SIGNED, true );
		return $time;
	}

	public static function is_doc_agreement_signed( $doc_id = 0 ) {
		$possibly_signed_at = self::get_doc_agreement_signed_time( $doc_id );
		return ( $possibly_signed_at > 0 ) ? true : false;
	}

	public static function save_doc_agreement_signed_time( $doc_id = 0 ) {
		update_post_meta( $doc_id, self::DOC_SIGNED, time() );
	}

	public static function get_doc_agreement_sig_id( $doc_id = 0 ) {
		$signature_args = get_post_meta( $doc_id, self::DOC_SIG_ID, true );
		if ( ! isset( $signature_args['invitation'] ) ) {
			return 0;
		}
		$signature = $signature_args['invitation'];
		$signature_id = $signature->document_id;
		return $signature_id;
	}

	public static function save_doc_agreement_sig_args( $doc_id = 0, $args = 0 ) {
		update_post_meta( $doc_id, self::DOC_SIG_ID, $args );
	}

	public static function get_doc_agreement_id( $doc_id = 0 ) {
		$default = get_post_meta( $doc_id, self::DOC_ID_META, true );
		if ( '' === $default || ! $default ) {
			if ( SI_Estimate::POST_TYPE === get_post_type( $doc_id ) ) {
				$default = ApproveMe_Settings::get_agreement_doc_estimates();
			} else {
				$default = ApproveMe_Settings::get_agreement_doc();
			}
		}
		return $default;
	}

	public static function save_doc_agreement_id( $doc_id = 0, $agreement_page_id = 0 ) {
		update_post_meta( $doc_id, self::DOC_ID_META, $agreement_page_id );
	}

	//////////////////////
	// Standard Methods //
	//////////////////////


	public static function is_signature_required( $doc_id = 0 ) {
		$agreement_page_id = self::get_doc_agreement_id( $doc_id );
		if ( ! $agreement_page_id ) {
			return false;
		}
		if ( PHP_INT_MAX == (int) $agreement_page_id ) {
			return false;
		}
		$signed = self::is_doc_agreement_signed( $doc_id );
		return ( ! $signed ) ? true : false;

	}

	public static function agreement_required( $sad_doc_id = 0 ) {
		if ( ! $sad_doc_id ) {
			return false;
		}

		$page_id = self::get_sad_page_id_from_document_id( $sad_doc_id );
		return ( $page_id ) ? true : false;
	}

	///////////
	// Model //
	///////////

	public static function esig_get_sad_pages() {
		if ( ! function_exists( 'WP_E_Sig' ) ) {
			return array();
		}

		global $wpdb;
		$dbtable = self::db_table();
		$stand_alone_pages = $wpdb->get_results( "SELECT page_id, document_id FROM {$dbtable}", OBJECT_K );

		$pages = array();
		foreach ( $stand_alone_pages as $sad_page ) {
			// Why don't "trashed" posts have a different status? Duuummmb.
			if ( 'publish' === get_post_status( $sad_page->page_id ) ) {
				$pages[ $sad_page->page_id ] = get_the_title( $sad_page->page_id );
			}
		}
		return $pages;
	}


	/**
	 *  Get sad page id by document id
	 */
	public static function get_sad_page_id_from_document_id( $document_id ) {
		global $wpdb;
		$dbtable = self::db_table();
		$page_id = $wpdb->get_var(
			$wpdb->prepare(
				'SELECT page_id FROM ' . $dbtable . ' WHERE document_id=%s', $document_id
			)
		);
		return (int) $page_id;
	}

	/**
	 *  Get sad document id by page id
	 *
	 */
	public function get_sad_document_id( $page_id ) {
		global $wpdb;
		$dbtable = self::db_table();
		$doc_id = $wpdb->get_var(
			$wpdb->prepare(
				'SELECT document_id FROM ' . $dbtable . ' WHERE page_id=%s', $page_id
			)
		);
		return (int) $doc_id;
	}

	/**
	*  Check sad page is active and document is valid.
	*/
	public function is_agreement_page_valid( $page_id ) {
		$is_valid = false;
		if ( ! function_exists( 'WP_E_Sig' ) ) {
			return $is_valid;
		}

		$esig = WP_E_Digital_Signature::instance();
		$esig_controller = $esig->shortcode;

		$document_id = (int) $get_sad_document_id( $page_id );
		if ( 0 === $esig_controller->document->document_exists( $document_id ) ) {
			$is_valid = false;
		}
		$document_status = $esig_controller->document->getStatus( $document_id );

		if ( 'trash' !== $document_status ) {
			$is_valid = true;
		}

		return $is_valid;
	}

	public static function db_table() {
		global $wpdb;
		$dbtable = $wpdb->prefix . 'esign_documents_stand_alone_docs';
		return $dbtable;
	}

	//////////////
	// Utility //
	//////////////


	public static function load_addon_view( $view, $args, $allow_theme_override = true ) {
		add_filter( 'si_views_path', array( __CLASS__, 'addons_view_path' ) );
		$view = self::load_view( $view, $args, $allow_theme_override );
		remove_filter( 'si_views_path', array( __CLASS__, 'addons_view_path' ) );
		return $view;
	}

	public static function addons_view_path() {
		return SA_ADDON_APPROVEME_PATH . '/views/';
	}


	public static function set_default_when_new_invoice_is_cloned( $new_post_id = 0, $cloned_post_id = 0, $new_post_type = '' ) {
		if ( SI_Invoice::POST_TYPE === $new_post_type ) {
			$doc_agreement = self::get_doc_agreement_id( $new_post_id );
			self::save_doc_agreement_id( $new_post_id, $doc_agreement );
		}
	}


	public static function set_default_when_new_invoice_is_created( $invoice = '', $args = array() ) {
		$new_post_id = $invoice->get_id();
		$doc_agreement = self::get_doc_agreement_id( $new_post_id );
		self::save_doc_agreement_id( $new_post_id, $doc_agreement );
	}
}
