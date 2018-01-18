<?php

/**
 * Account_Credits Controller
 *
 * @package Sprout_Invoice
 * @subpackage Account_Credits
 */
class SI_Account_Credits extends SI_Controller {
	const SUBMISSION_NONCE = 'si_credit_submission';
	const IMPORT_QUERY_VAR = 'import-unbilled-credit';


	public static function init() {

		if ( is_admin() ) {

			// Enqueue
			add_action( 'admin_enqueue_scripts', array( __CLASS__, 'register_resources' ) );
			add_action( 'admin_enqueue_scripts', array( __CLASS__, 'admin_enqueue' ), 20 );

		}

		// Apply credit but before anything else, in case an payment is attempted
		add_action( 'sa_new_invoice', array( __CLASS__, 'maybe_apply_credit_to_invoice_balance' ), 0, 1 );
		add_action( 'si_recurring_invoice_created', array( __CLASS__, 'maybe_apply_credit_to_new_recurring_invoice' ), 0 );

	}

	//////////////
	// Enqueue //
	//////////////

	public static function register_resources() {
		// admin js
		wp_register_script( 'si_account_credits', SA_ADDON_ACCOUNT_CREDITS_URL . '/resources/admin/js/account_credit.js', array( 'jquery' ), self::SI_VERSION );
	}

	public static function admin_enqueue() {
		wp_enqueue_script( 'si_account_credits' );
	}


	///////////
	// Form //
	///////////

	public static function credit_entry_fields() {
		$clients = array();
		$credit_types = array();
		$fields = array();

		if ( isset( $_GET['post'] ) && SI_Client::POST_TYPE === get_post_type( get_the_id() )  ) {

			$fields['client_id'] = array(
					'weight' => 1,
					'label' => __( 'Client', 'sprout-invoices' ),
					'type' => 'hidden',
					'value' => get_the_id(),
				);

		} else {
			$args = array(
				'post_type' => SI_Client::POST_TYPE,
				'post_status' => 'publish',
				'posts_per_page' => -1,
				'fields' => 'ids',
			);
			$clients = get_posts( apply_filters( 'si_clients_select_get_posts_args', $args ) );
			$client_options = array();
			foreach ( $clients as $client_id ) {
				$client_options[ $client_id ] = get_the_title( $client_id );
			}
			$fields['client_id'] = array(
					'weight' => 1,
					'label' => __( 'Client', 'sprout-invoices' ),
					'type' => 'select',
					'options' => $client_options,
					//'attributes' => array( 'class' => 'select2' ),
				);
		}

		$description = sprintf( __( 'Select a credit type, <a href="%s">create a new type</a> or <a class="thickbox" href="%s" title="Edit Types">manage existing credit types</a>.', 'sprout-invoices' ), 'javascript:void(0)" id="show_credit_type_creation_modal"', admin_url( 'admin-ajax.php?action=sa_manage_credit&width=750&height=450' ) );

		$credit_types_options = SI_Credit::get_credit_types();
		$fields['credit_type_id'] = array(
				'weight' => 10,
				'label' => __( 'Type', 'sprout-invoices' ),
				'type' => 'select',
				'description' => $description,
				'options' => $credit_types_options,
			);

		$fields['credit'] = array(
			'weight' => 20,
			'label' => __( 'Credit', 'sprout-invoices' ),
			'type' => 'number',
			'description' => __( 'Set to 1:1 to your set currency.', 'sprout-invoices' ),
		);

		$fields['note'] = array(
			'weight' => 30,
			'label' => __( 'Note', 'sprout-invoices' ),
			'type' => 'textarea',
			'default' => '',
		);

		$fields['date'] = array(
			'weight' => 100,
			'label' => __( 'Date', 'sprout-invoices' ),
			'type' => 'date',
			'default' => date( 'Y-m-d', current_time( 'timestamp' ) ),
			'placeholder' => '',
		);

		$fields['nonce'] = array(
			'type' => 'hidden',
			'value' => wp_create_nonce( self::SUBMISSION_NONCE ),
			'weight' => 10000,
		);

		$fields = apply_filters( 'si_credit_entry_form_fields', $fields );
		uasort( $fields, array( __CLASS__, 'sort_by_weight' ) );
		return $fields;
	}

	public static function credit_creation_fields( $id = 0 ) {

		$fields['name'] = array(
			'weight' => 0,
			'label' => __( 'Name', 'sprout-invoices' ),
			'type' => 'text',
		);

		$fields['nonce'] = array(
			'type' => 'hidden',
			'value' => wp_create_nonce( self::SUBMISSION_NONCE ),
			'weight' => 10000,
		);

		$fields = apply_filters( 'si_credit_creation_form_fields', $fields );
		uasort( $fields, array( __CLASS__, 'sort_by_weight' ) );
		return $fields;
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

	public static function load_addon_view_to_string( $view, $args, $allow_theme_override = true ) {
		ob_start();
		self::load_addon_view( $view, $args, $allow_theme_override );
		return ob_get_clean();
	}

	public static function addons_view_path() {
		return SA_ADDON_ACCOUNT_CREDITS_PATH . '/views/';
	}

	public static function delete_credit_entry( $credit_id = 0 ) {
		// records are normally deleted if their parent is deleted
		remove_action( 'deleted_post', array( 'SI_Internal_Records', 'attempt_associated_record_deletion' ) );
		// by removing this action the credit entries will be newly associated
		// with the default credit_type instead via
		add_action( 'deleted_post', array( __CLASS__, 'attempt_reassign_entries_to_default' ) );

		wp_delete_post( $credit_id, true );
	}

	public static function attempt_reassign_entries_to_default( $post_id = 0 ) {
		// prevent looping and checking if a record has a record associated with it.
		if ( get_post_type( $post_id ) !== SI_Record::POST_TYPE ) {
			global $wpdb;
			$parent_update = array( 'post_parent' => SI_Credit::default_credit() );
			$parent_where = array( 'post_parent' => $post_id, 'post_type' => SI_Record::POST_TYPE );
			$wpdb->update( $wpdb->posts, $parent_update, $parent_where );
		}
	}

	////////////////
	// Automation //
	////////////////

	public static function maybe_apply_credit_to_new_recurring_invoice( $invoice_id ) {
		$invoice = SI_Invoice::get_instance( $invoice_id );
		self::maybe_apply_credit_to_invoice_balance( $invoice );
	}

	public static function maybe_apply_credit_to_invoice_balance( SI_Invoice $invoice ) {
		$invoice_id = $invoice->get_id();
		$client_id = $invoice->get_client_id();
		if ( ! $client_id ) {
			return;
		}
		$total_credits = SI_Account_Credits_Clients::get_credit_balance( $client_id );

		if ( $total_credits < 0.01 ) {
			return;
		}

		$balance = $invoice->get_balance();
		$amount = ( $total_credits <= $balance ) ? $total_credits : $balance ;

		// Create negative balance credit
		$credit_data = array(
			'client_id' => (int) $client_id,
			'type_id' => (int) SI_Credit::get_payment_credit_type(),
			'credit_val' => (float) -$amount,
			'note' => sprintf(
				__( 'Credit Payment Applied to Invoice: %s', 'sprout-invoices' ),
				sprintf( '<a href="%s">%s</a>', get_edit_post_link( $invoice_id ), get_the_title( $invoice_id )
				)
			),
			'date' => (int) current_time( 'timestamp' ),
			'user_id' => get_current_user_id(),
		);
		$new_credit_id = SI_Account_Credits_Clients::create_associated_credit( $client_id, $credit_data );

		// Make a payment
		$payment_id = Credits_Payment::create_admin_payment( $invoice_id, $amount, $new_credit_id, date( get_option( 'date_format' ), time() ), sprintf( __( 'Credit Applied from Account Balance. Payment Credit ID #%s', 'sprout-invoices' ), $new_credit_id ) );

		if ( ! $payment_id ) {
			return;
		}

		do_action( 'si_credit_payment', $new_credit_id, $payment_id );

		$data = array(
			'payment_credit_id' => $new_credit_id,
			'payment_id' => $payment_id,
			);

		$credit = SI_Credit::get_credit_entry( $new_credit_id );
		$data = array_merge( $credit->get_data(), $data );
		$credit->set_data( $data );
	}
}
