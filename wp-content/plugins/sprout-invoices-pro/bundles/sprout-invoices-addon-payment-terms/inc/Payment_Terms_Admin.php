<?php

/**
 * SI_Payment_Terms_Fees Controller
 *
 * Creates the admin interferace.
 *
 * @package SI_Payment_Terms
 * @subpackage SI_Payment_Terms_Fees
 */
class SI_Payment_Terms_Admin extends SI_Payment_Terms {
	const META = 'si_payment_terms'; // assoc. array
	const AJAX_ACTION = 'si_payment_term_callback';

	public static function init() {

		if ( is_admin() ) {
			// Meta boxes
			add_action( 'admin_init', array( __CLASS__, 'register_meta_boxes' ) );

			// Enqueue
			add_action( 'admin_enqueue_scripts', array( __CLASS__, 'register_resources' ) );
			add_action( 'admin_enqueue_scripts', array( __CLASS__, 'admin_enqueue' ), 20 );
		}

		// add a meta box
		add_action( 'wp_ajax_' . self::AJAX_ACTION, array( get_class(), 'ajax_callback' ) );
	}

	//////////////
	// Enqueue //
	//////////////

	public static function register_resources() {
		// admin js
		wp_register_script( 'si_payment_term', SA_ADDON_PAYMENT_TERMS_URL . '/resources/admin/js/payment_term.js', array( 'jquery' ), self::SI_VERSION );
	}

	public static function admin_enqueue() {
		wp_localize_script( 'si_payment_term', 'payment_term_saved',
			array(
				'fee_saved' => __( 'Fee saved!', 'sprout-invoices' ),
				)
		);
		wp_enqueue_script( 'si_payment_term' );
	}

	/////////////////
	// Meta boxes //
	/////////////////

	/**
	 * Regsiter meta boxes for estimate editing.
	 *
	 * @return
	 */
	public static function register_meta_boxes() {
		// estimate specific
		$args = array(
			'si_payment_terms' => array(
				'title' => __( 'Payment Terms', 'sprout-invoices' ),
				'show_callback' => array( __CLASS__, 'show_meta_box_payment_term' ),
				'save_callback' => array( __CLASS__, 'save_meta_box_payment_term' ),
				'context' => 'normal',
				'priority' => 'high',
				'save_priority' => 0,
			),
		);
		do_action( 'sprout_meta_box', $args, SI_Invoice::POST_TYPE );
	}

	/**
	 * Meta box view
	 * Abstracted to be called via AJAX
	 * @param int $invoice_id
	 *
	 */
	public static function show_meta_box_payment_term( $post, $metabox ) {
		if ( ! current_user_can( 'edit_sprout_invoices' ) ) {
			_e( 'User cannot create new posts!', 'sprout-invoices' );
			return;
		}

		$doc_id = $post->ID;

		$sorted_payment_terms = self::get_sorted_payment_terms( $doc_id );

		// table
		$view = self::load_addon_view_to_string( 'admin/payment-terms/metabox', array(
			'doc_id' => $doc_id,
			'sorted_payment_terms' => $sorted_payment_terms,
		), true );

		// option to save
		$fields = self::fee_creation_fields();
		$view .= self::load_addon_view_to_string( 'admin/payment-terms/term-option', array(
			'doc_id' => $doc_id,
			'fields' => $fields,
		), true );

		print $view;
	}

	public static function save_meta_box_payment_term() {
		// Term mngt is done via AJAX
	}

	//////////
	// AJAX //
	//////////

	public static function ajax_callback() {

		$nonce = $_REQUEST['nonce'];

		if ( ! wp_verify_nonce( $nonce, self::NONCE ) ) {
			wp_send_json_error( array( 'message' => __( 'Not going to fall for it!', 'sprout-invoices' ) ) );
		}

		if ( isset( $_REQUEST['maybe'] ) ) {

			switch ( $_REQUEST['maybe'] ) {
				case 'add_term':
					self::maybe_add_payment_term();
					break;
				case 'delete_term':
					self::maybe_delete_payment_term();
					break;

				default:
					wp_send_json_error( array( 'message' => __( 'No action provided!', 'sprout-invoices' ) ) );
					break;
			}
		}

		switch ( $_REQUEST['view'] ) {
			case 'table':
				self::payment_terms_table_view();
				break;

			case 'option':
				self::payment_option_view();
				break;

			default:
				wp_send_json_error( array( 'message' => __( 'No view provided!', 'sprout-invoices' ) ) );
				break;
		}
	}

	public static function maybe_add_payment_term() {
		if ( isset( $_REQUEST['doc_id'] ) ) {
			$doc_id = $_REQUEST['doc_id'];
			$doc = si_get_doc_object( $doc_id );
		}
		if ( ! $doc_id ) {
			wp_send_json_error( array( 'message' => __( 'No doc id provided!', 'sprout-invoices' ) ) );
		}

		$fee = 0;
		if ( isset( $_REQUEST['fee'] ) ) {
			$fee = floatval( $_REQUEST['fee'] );
		}
		if ( ! is_numeric( $fee ) ) {
			wp_send_json_error( array( 'message' => __( 'No fee provided!', 'sprout-invoices' ) ) );
		}

		$doc = si_get_doc_object( $doc_id );
		if ( ! is_a( $doc, 'SI_Invoice' ) && ! is_a( $doc, 'SI_Estimate' ) ) {
			wp_send_json_error( array( 'message' => __( 'Doc not found!', 'sprout-invoices' ) ) );
		}

		$args = array();
		$args['doc_id'] = (int) $doc_id;
		$args['fee'] = (float) $fee;
		$args['balance'] = (float) $doc->get_balance();

		if ( isset( $_REQUEST['balance'] ) ) {
			$args['balance'] = floatval( $_REQUEST['balance'] );
		}

		if ( isset( $_REQUEST['title'] ) ) {
			$args['title'] = esc_textarea( $_REQUEST['title'] );
		}

		$args['percentage'] = false;
		if ( isset( $_REQUEST['percentage'] ) ) {
			$args['percentage'] = $_REQUEST['percentage'];
		}

		if ( isset( $_REQUEST['time'] ) ) {
			$args['time'] = (int) strtotime( $_REQUEST['time'] );
			$args['post_date'] = (int) strtotime( $_REQUEST['time'] );

			// Set the due date if set before this new payment term due date
			if ( $args['time'] > $doc->get_due_date() ) {
				$doc->set_due_date( $args['time'] );
			}
		}

		$args['complete'] = false;
		if ( isset( $_REQUEST['complete'] ) ) {
			$args['complete'] = $_REQUEST['complete'];
		}

		if ( 'true' == $args['complete'] && $args['time'] >= current_time( 'timestamp' ) ) {
			wp_send_json_error( array( 'message' => __( 'Term date must be in the past if complete.', 'sprout-invoices' ) ) );
		}

		if ( 'false' == $args['complete'] && $args['time'] <= current_time( 'timestamp' ) ) {
			//error_log
			//TODO
			//wp_send_json_error( array( 'message' => __( 'Term date must be in the future if not complete.', 'sprout-invoices' ) ) );
		}

		$defaults = array(
			'doc_id' => (int) $doc->get_id(),
			'balance' => (float) si_get_number_format( (float) $doc->get_balance() ),
			'fee' => (float) si_get_number_format( (float) 0 ),
			'title' => '',
			'time' => (int) current_time( 'timestamp' ),
			'post_date' => (int) current_time( 'timestamp' ),
			'percentage' => false,
			'user_id' => get_current_user_id(),
		);
		$data = wp_parse_args( $args, $defaults );
		$new_payment_term = SI_Payment_Term::new_payment_term( $data );
		do_action( 'si_payment_term_created', $new_payment_term, $doc_id );

		wp_send_json_success( array( 'message' => __( 'Payment Term Created Successfully!', 'sprout-invoices' ), 'data' => $data ) );
	}

	public static function maybe_delete_payment_term() {
		if ( isset( $_REQUEST['id'] ) ) {
			$payment_term_id = $_REQUEST['id'];
		}
		if ( isset( $_REQUEST['doc_id'] ) ) {
			$doc_id = $_REQUEST['doc_id'];
		}
		if ( ! $payment_term_id ) {
			wp_send_json_error( array( 'message' => __( 'Doc not found!', 'sprout-invoices' ) ) );
		}

		$acted = SI_Payment_Term::delete_payment_term( $payment_term_id, $doc_id );

		if ( ! $acted ) {
			wp_send_json_error( array( 'message' => __( 'Payment term not found!', 'sprout-invoices' ) ) );
		}

		do_action( 'si_payment_term_deleted', $payment_term_id, $doc_id );

		wp_send_json_success( array( 'message' => __( 'Payment Term Deleted!', 'sprout-invoices' ) ) );
	}



	/**
	 * Meta box view
	 * Abstracted to be called via AJAX
	 * @param int $invoice_id
	 *
	 */
	public static function payment_terms_table_view() {
		if ( ! current_user_can( 'edit_sprout_invoices' ) ) {
			wp_send_json_error( array( 'message' => 'User cannot create new posts!' ) );
		}

		$doc_id = 0;
		if ( isset( $_REQUEST['doc_id'] ) ) {
			$doc_id = $_REQUEST['doc_id'];
		}

		if ( ! $doc_id ) {
			wp_send_json_error( array( 'message' => 'No doc id' ) );
		}

		$sorted_payment_terms = self::get_sorted_payment_terms( $doc_id );

		$view = self::load_addon_view_to_string( 'admin/payment-terms/metabox', array(
			'doc_id' => $doc_id,
			'sorted_payment_terms' => $sorted_payment_terms,
		), true );

		wp_send_json_success( array( 'view' => $view ) );
	}

	public static function payment_option_view( $invoice = 0 ) {
		if ( ! current_user_can( 'edit_sprout_invoices' ) ) {
			wp_send_json_error( array( 'message' => 'User cannot create new posts!' ) );
		}

		if ( isset( $_REQUEST['invoice_id'] ) ) {
			$invoice_id = $_REQUEST['invoice_id'];
		}

		if ( ! $invoice_id ) {
			wp_send_json_error( array( 'message' => 'No invoice id' ) );
		}

		$fields = self::fees_creation_fields();
		$view = self::load_addon_view_to_string( 'admin/payment-terms/term-option', array(
			'invoice_id' => $invoice_id,
			'fields' => $fields,
		), true );

		wp_send_json_success( array( 'view' => $view ) );
	}


	public static function fee_creation_fields() {
		$clients = array();
		$fees_types = array();
		$fields = array();

		$fields['invoice_id'] = array(
			'weight' => 1,
			'type' => 'hidden',
			'value' => get_the_id(),
		);

		$fields['balance'] = array(
			'weight' => 5,
			'label' => __( 'Payment Due', 'sprout-invoices' ),
			'type' => 'number',
			'description' => __( 'The total payments due so no fee is added.', 'sprout-invoices' ),
		);

		$fields['time'] = array(
			'weight' => 10,
			'label' => __( 'Due by Date', 'sprout-invoices' ),
			'type' => 'date',
			'default' => date( 'Y-m-d', current_time( 'timestamp' ) ),
			'placeholder' => '',
		);

		$fields['complete'] = array(
			'weight' => 15,
			'label' => __( 'Complete', 'sprout-invoices' ),
			'type' => 'checkbox',
			//'description' => __( 'Must have a date in the past.', 'sprout-invoices' ),
			'default' => false,
		);

		$fields['fee'] = array(
			'weight' => 49,
			'label' => __( 'Fee', 'sprout-invoices' ),
			'type' => 'number',
			'description' => __( 'The fee imposed to the balance if the time passes.', 'sprout-invoices' ),
		);

		$fields['percentage'] = array(
			'weight' => 50,
			'label' => __( 'Is Percentage', 'sprout-invoices' ),
			'type' => 'checkbox',
			'default' => false,
			'description' => __( 'Percentage will be calculated off invoice sub total, not invoice balance or total due.', 'sprout-invoices' ),
		);

		$fields['title'] = array(
			'weight' => 70,
			'label' => __( 'Description', 'sprout-invoices' ),
			'type' => 'text',
			'description' => __( 'Description for the client.', 'sprout-invoices' ),
		);

		$fields['nonce'] = array(
			'type' => 'hidden',
			'value' => wp_create_nonce( self::NONCE ),
			'weight' => 10000,
		);

		$fields = apply_filters( 'si_fees_entry_form_fields', $fields );
		uasort( $fields, array( __CLASS__, 'sort_by_weight' ) );
		return $fields;
	}
}
