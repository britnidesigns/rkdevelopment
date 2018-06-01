<?php

/**
 * Auto Billing Admin Controller
 *
 *
 * @package SI_Sprout_Billings_Admin
 */
class SI_Sprout_Billings_Admin extends SI_Sprout_Billings {

	public static function init() {

		if ( is_admin() ) {
			// payment settings
			add_action( 'admin_init', array( __CLASS__, 'register_options' ), -10 );

			// Add meta box button to auto bill
			add_action( 'admin_init', array( __CLASS__, 'register_client_meta_boxes' ) );

			// meta box for invoice
			add_action( 'admin_init', array( __CLASS__, 'register_invoice_meta_boxes' ) );

			// Admin columns
			add_filter( 'manage_edit-'.SI_Invoice::POST_TYPE.'_columns', array( __CLASS__, 'register_columns' ) );
			add_filter( 'manage_'.SI_Invoice::POST_TYPE.'_posts_custom_column', array( __CLASS__, 'column_display' ), 10, 2 );
		}
	}

	//////////////////////
	// Payment Settings //
	//////////////////////

	public static function register_options() {
		$settings = array(
			'si_billing_default_bill_date' => array(
				'title' => __( 'AutoPay Options' , 'sprout-invoices' ),
				'tab' => SI_Payment_Processors::get_settings_page( false ),
				'settings' => array(
					self::GLOBAL_META_KEY => array(
						'label' => __( 'AutoPay Billing Date/Time' , 'sprout-invoices' ),
						'option' => array(
							'type' => 'select',
							'options' => self::day_of_month_selection(),
							'default' => self::get_global_autopay_date(),
							'description' => sprintf( __( 'Time of AutoPay is set to <code>%s</code>, current time is set to <code>%s</code>. This AutoPay date can be defined per client.', 'sprout-invoices' ), self::time_of_day_for_autopay(), date( 'r', current_time( 'timestamp' ) ) ),
						),
					),
				),
			),
		);
		do_action( 'sprout_settings', $settings, SI_Payment_Processors::SETTINGS_PAGE );
	}

	/////////////////
	// Meta boxes //
	/////////////////

	public static function register_client_meta_boxes() {
		$args = array(
			'si_client_auto_billing' => array(
				'title' => __( 'Auto Payments' , 'sprout-invoices' ),
				'show_callback' => array( __CLASS__, 'show_autobilling_meta_box' ),
				'save_callback' => array( __CLASS__, 'maybe_save_client_option' ),
				'context' => 'normal',
				'priority' => 'high',
				'save_priority' => 0,
				'weight' => 10,
			),
		);
		do_action( 'sprout_meta_box', $args, SI_Client::POST_TYPE );
	}

	public static function register_invoice_meta_boxes() {
		$args = array(
			'si_client_auto_billing' => array(
				'title' => __( 'Auto Payments' , 'sprout-invoices' ),
				'show_callback' => array( __CLASS__, 'show_billing_meta_box' ),
				'save_callback' => array( __CLASS__, 'maybe_save_invoice_option' ),
				'context' => 'normal',
				'priority' => 'high',
				'save_priority' => 0,
				'weight' => 0,
			),
		);
		do_action( 'sprout_meta_box', $args, SI_Invoice::POST_TYPE );
	}

	///////////////////////
	// Client Meta Boxes //
	///////////////////////


	public static function show_autobilling_meta_box( $post, $metabox ) {
		$client_id = $post->ID;
		$client = SI_Client::get_instance( $post->ID );

		$profile_id = Sprout_Billings_Profiles::get_client_payment_profile_id( $client_id );
		$payment_profiles = Sprout_Billings_Profiles::get_client_payment_profiles( $client_id );
		$payment_profile_id = self::get_client_autopay_profile_id( $client_id );
		$fields = array();
		$fields['payment_profile_id'] = array(
			'type' => 'input',
			'weight' => 5,
			'label' => sprintf( __( 'Payment Profile ID' , 'sprout-invoices' ) ),
			'description' => __( 'Matches unique id at payment processor.' , 'sprout-invoices' ),
			'default' => $profile_id,
		);
		$fields['autopay_option'] = array(
			'type' => 'select',
			'options' => self::day_of_month_selection(),
			'weight' => 15,
			'label' => sprintf( __( 'AutoPay Date' , 'sprout-invoices' ) ),
			'description' => __( 'A payment will be attempted this date for all outstanding invoices.', 'sprout-invoices' ),
			'default' => self::get_client_autopay_date( $post->ID ),
		);
		$fields = apply_filters( 'siba_client_meta_fields', $fields, $post );
		self::load_addon_view( 'admin/meta-boxes/clients/auto-billing-meta-box', array(
				'client_id' => $client_id,
				'fields' => $fields,
				'payment_profiles' => $payment_profiles,
				'default_payment_profile_id' => $payment_profile_id,
		) );

	}

	public static function maybe_save_client_option( $post_id, $post, $callback_args ) {
		$client_id = $post_id;
		self::clear_client_payment_profile( $client_id );
		if ( isset( $_POST['sa_credit_payment_method'] ) && $_POST['sa_credit_payment_method'] ) {
			$payment_profile_id = $_POST['sa_credit_payment_method'];
			self::save_client_payment_profile( $client_id, $payment_profile_id );
		}
		if ( isset( $_POST['sa_metabox_autopay_option'] ) && is_numeric( $_POST['sa_metabox_autopay_option'] ) ) {
			$day = (int) $_POST['sa_metabox_autopay_option'];
			self::set_client_autopay_date( $client_id, $day );
		}
		Sprout_Billings_Profiles::delete_profile( $client_id );
		if ( isset( $_POST['sa_metabox_payment_profile_id'] ) ) {
			$profile_id = $_POST['sa_metabox_payment_profile_id'];
			Sprout_Billings_Profiles::update_client_payment_profile_id( $client_id, $profile_id );

		}
		do_action( 'sb_save_client_meta_box', $client_id, $day, $profile_id );
	}

	////////////////////////
	// Invoice Meta Boxes //
	////////////////////////

	public static function show_billing_meta_box( $post, $metabox ) {
		$invoice_id = $post->ID;
		$invoice = SI_Invoice::get_instance( $invoice_id );
		if ( ! is_a( $invoice, 'SI_Invoice' ) ) {
			return;
		}

		if ( 'auto-draft' === $invoice->get_status() ) {
			printf( '<b>%s</b>', __( 'This invoice needs to be saved before these options are available.' , 'sprout-invoices' ) );
			return;
		}

		$balance = $invoice->get_balance();
		if ( $balance < 0.01 ) {
			self::meta_box_payments_view( $invoice );
			return;
		}

		$fields = self::meta_box_option_fields( $invoice );
		self::load_addon_view( 'admin/meta-boxes/invoices/auto-billing-option.php', array(
				'fields' => $fields,
				'invoice_id' => $invoice_id,
		), false );

	}

	public static function meta_box_payments_view( $invoice ) {
		self::load_addon_view( 'admin/meta-boxes/invoices/auto-billing-information.php', array(
				'invoice_id' => $invoice->get_id(),
				'client_id' => $invoice->get_client_id(),
				'payments' => $invoice->get_payments(),
		), false );
	}

	public static function meta_box_option_fields( $invoice ) {
		$invoice_id = $invoice->get_id();
		$client_id = $invoice->get_client_id();
		$balance = ( si_has_invoice_deposit( $invoice->get_id() ) ) ? $invoice->get_deposit() : $invoice->get_balance();
		$can_charge = SI_Sprout_Billings::can_autocharge_client( $client_id );

		$fields = array();

		$fields['do_not_autobill_divide'] = array(
				'type' => 'bypass',
				'weight' => 20,
				'output' => '<hr/>',
			);

		$fields['do_not_autobill'] = array(
			'weight' => 21,
			'label' => __( 'Do Not Autobill', 'sprout-invoices' ),
			'description' => __( 'Seting this option would disable the autobilling functionality for this invoice.', 'sprout-invoices' ),
			'type' => 'checkbox',
			'default' => self::is_auto_bill_disabled( $invoice_id ),
			'value' => 'false',
		);

		if ( $balance > 0.00 && $client_id && $can_charge ) {
			$fields['attempt_charge'] = array(
				'label' => sprintf( __( 'Capture Payment' , 'sprout-invoices' ) ),
				'type' => 'bypass',
				'weight' => 10,
				'output' => sprintf( '<button class="payment_capture button button-small" data-client_id="%3$s" data-invoice_id="%2$s">%1$s</button>', sprintf( __( 'Attempt %s Payment' , 'sprout-invoices' ), sa_get_formatted_money( $balance ) ), $invoice_id, $client_id, $balance ),
			);
		}

		// filter and sort
		$fields = apply_filters( 'si_sprout_billing_meta_box_fields', $fields, $invoice_id, $client_id, $balance );
		uasort( $fields, array( __CLASS__, 'sort_by_weight' ) );
		return $fields;
	}

	public static function maybe_save_invoice_option( $post_id, $post, $callback_args ) {
		$doc_id = $post_id;
		delete_post_meta( $doc_id, self::DONOT_AUTOBILL );

		if ( isset( $_POST['sa_metabox_do_not_autobill'] ) && 'false' === $_POST['sa_metabox_do_not_autobill'] ) {
			self::set_invoice_tonot_autobill( $doc_id );
		}
		do_action( 'sb_save_invoice_meta_box', $doc_id );
	}


	//////////////////////
	// Standard Methods //
	//////////////////////

	public static function get_invoice_autobill_option( $doc_id = 0 ) {
		$option = get_post_meta( $doc_id, self::DONOT_AUTOBILL, true );
		return ( 'false' === $option ) ? false : true;
	}

	public static function set_invoice_tonot_autobill( $doc_id = 0 ) {
		$willautobill = update_post_meta( $doc_id, self::DONOT_AUTOBILL, 'false' );
		return true;
	}

	///////////////////
	// Admin columns //
	///////////////////

	public static function register_columns( $columns ) {
		$columns['payment_collections'] = '<span class="dashicons dashicons-money"></span>';
		return $columns;
	}

	public static function column_display( $column_name, $id ) {
		$invoice = SI_Invoice::get_instance( $id );

		if ( ! is_a( $invoice, 'SI_Invoice' ) ) {
			return;
		}

		$balance = ( si_has_invoice_deposit( $invoice->get_id() ) ) ? $invoice->get_deposit() : $invoice->get_balance();

		switch ( $column_name ) {
			case 'payment_collections':
				if ( $invoice->get_balance() < 0.01 ) {
					_e( 'Paid' , 'sprout-invoices' );
				} else {
					$client_id = $invoice->get_client_id();
					if ( $client_id ) {
						if ( self::can_autocharge_client( $client_id ) ) {
							printf( '<button class="payment_capture button button-small" data-client_id="%3$s" data-invoice_id="%2$s">%1$s</button>', sprintf( __( 'Attempt %s Payment' , 'sprout-invoices' ), sa_get_formatted_money( $balance ) ), $id, $client_id );
						} else {
							_e( 'Not setup, or not yet accepted.' , 'sprout-invoices' );
						}
					}
				}
			break;

			default:
			break;
		}

	}
}
