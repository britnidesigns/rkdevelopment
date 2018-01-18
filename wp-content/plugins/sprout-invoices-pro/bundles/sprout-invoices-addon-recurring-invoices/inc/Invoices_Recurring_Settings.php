<?php

/**
 * Time_Tracking Controller
 *
 * @package Sprout_Invoice
 * @subpackage Time_Tracking
 */
class SI_Invoices_Recurring_Settings extends SI_Invoices_Recurring {

	public static function init() {

		if ( is_admin() ) {
			// Meta boxes
			add_action( 'admin_init', array( __CLASS__, 'register_meta_boxes' ) );

			// Enqueue
			add_action( 'admin_enqueue_scripts', array( __CLASS__, 'register_resources' ) );
			add_action( 'admin_enqueue_scripts', array( __CLASS__, 'admin_enqueue' ), 20 );

		}

	}

	//////////////
	// Enqueue //
	//////////////

	public static function register_resources() {
		// admin js
		wp_register_script( 'si_recurring_invoices', SA_ADDON_RECURRING_INVOICES_URL . '/resources/admin/js/recurring_invoices.js', array( 'jquery' ), self::SI_VERSION );
	}

	public static function admin_enqueue() {
		wp_enqueue_script( 'si_recurring_invoices' );
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
			'si_recurring_invoices' => array(
				'title' => __( 'Recurring Invoice Creation', 'sprout-invoices' ),
				'show_callback' => array( __CLASS__, 'show_recurring_invoices_meta_box' ),
				'save_callback' => array( __CLASS__, 'save_meta_box_recurring_invoices' ),
				'context' => 'normal',
				'priority' => 'low',
				'weight' => 0,
				'save_priority' => 0,
			),
		);
		do_action( 'sprout_meta_box', $args, SI_Invoice::POST_TYPE );
	}

	/**
	 * Show time tracking metabox.
	 * @param  WP_Post $post
	 * @param  array $metabox
	 * @return
	 */
	public static function show_recurring_invoices_meta_box( $post, $metabox ) {
		$invoice = SI_Invoice::get_instance( $post->ID );

		$cloned_id = self::get_cloned( $post->ID );
		if ( $cloned_id ) {

			// Compat with subscription payments
			$cloned_invoice = SI_Invoice::get_instance( $cloned_id );
			$cloned_recurring_payment = SI_Payment_Processors::get_recurring_payment( $cloned_invoice );
			if ( $cloned_recurring_payment ) {
				printf( __( 'This invoice was created from another invoice: <a href="%s">%s</a>', 'sprout-invoices' ), get_edit_post_link( $cloned_id ), get_the_title( $cloned_id ) );
				return;
			}

			printf( __( 'This invoice was created from a recurring invoice: <a href="%s">%s</a>', 'sprout-invoices' ), get_edit_post_link( $cloned_id ), get_the_title( $cloned_id ) );
			return;
		}

		self::load_addon_view( 'admin/meta-boxes/invoices/recurring', array(
				'fields' => self::recurring_options( $invoice ),
				'next_time' => date_i18n( 'Y-m-d', self::get_clone_time( $post->ID ) ),
				'invoice_id' => $invoice->get_id(),
				'duration' => self::get_duration( $post->ID ),
				'children' => self::get_child_clones( $post->ID ),
		), false );

	}

	public static function save_meta_box_recurring_invoices( $post_id, $post, $callback_args, $invoice_id = null ) {

		self::set_as_not_recurring( $post_id );
		if ( isset( $_POST['sa_recurring_invoice_is_recurring'] ) && $_POST['sa_recurring_invoice_is_recurring'] ) {
			self::set_recurring( $post_id );
		}

		$frequency = ( isset( $_POST['sa_recurring_invoice_frequency'] ) ) ? $_POST['sa_recurring_invoice_frequency'] : '' ;
		self::set_frequency( $post_id, $frequency );

		$frequency_days = ( isset( $_POST['sa_recurring_invoice_custom_freq'] ) ) ? $_POST['sa_recurring_invoice_custom_freq'] : '' ;
		self::set_frequency_custom( $post_id, $frequency_days );

		$duration = ( isset( $_POST['sa_recurring_invoice_duration'] ) ) ? $_POST['sa_recurring_invoice_duration'] : 0 ;
		self::set_duration( $post_id, $duration );

		$start_time = ( isset( $_POST['sa_recurring_invoice_start_time'] ) ) ? strtotime( $_POST['sa_recurring_invoice_start_time'] ) : current_time( 'timestamp' );
		self::set_start_time( $post_id, $start_time );
	}

	public static function recurring_options( $doc ) {
		$fields = array();

		$is_recurring = self::is_recurring( $doc->get_id() );

		$start_meta = self::get_start_time( $doc->get_id() );
		$start = ( $start_meta ) ? $start_meta : strtotime( 'Next Month', current_time( 'timestamp' ) );

		$frequency_meta = self::get_frequency( $doc->get_id() );
		$frequency = ( $frequency_meta ) ? $frequency_meta : 'monthly' ;

		$custom_freq_meta = self::get_frequency_custom( $doc->get_id() );
		$custom_freq = ( $custom_freq_meta ) ? $custom_freq_meta : 15 ;

		$duration_meta = self::get_duration( $doc->get_id() );
		$duration = ( $duration_meta ) ? $duration_meta : 0 ;

		$is_recurring_desc = __( 'Check if this invoice should be recurring and be cloned on the frequency below.', 'sprout-invoices' );
		$clone_time = self::get_clone_time( $doc->get_id() );
		if ( $clone_time ) {
			$is_recurring_desc = sprintf( 'The next invoice will be generated on <code>%s</code>.', date_i18n( get_option( 'date_format' ), $clone_time ) );
		}

		$children = self::get_child_clones( $doc->get_id() );
		if ( $duration && ! empty( $children ) && $duration <= count( $children ) ) {
			$is_recurring_desc = __( 'All recurring invoices have been generated.', 'sprout-invoices' );
		}

		$fields['is_recurring'] = array(
			'weight' => 100,
			'label' => __( 'Recurring Invoice', 'sprout-invoices' ),
			'type' => 'checkbox',
			'default' => $is_recurring,
			'value' => '1',
			'description' => $is_recurring_desc,
		);

		$fields['duration'] = array(
			'weight' => 102,
			'label' => __( 'Duration', 'sprout-invoices' ),
			'type' => 'number',
			'default' => $duration,
			'description' => __( 'Total invoices to generate. 0 is unlimited.', 'sprout-invoices' ),
		);

		$fields['start_time'] = array(
			'weight' => 105,
			'label' => __( 'Start Date', 'sprout-invoices' ),
			'type' => 'date',
			'default' => date_i18n( 'Y-m-d', $start ),
			'placeholder' => ' ',
			'description' => __( 'This is the date the first child invoice will be generated. It should be in the future, otherwise an invoice will generated immediatly after you save this invoice.', 'sprout-invoices' ),
		);

		if ( count( $children ) ) {
			$fields['start_time']['description'] = __( 'IMPORTANT NOTE: Changing the start date will change the generation date for the next invoice, and the generation history will be factored.', 'sprout-invoices' );
		}

		$fields['frequency'] = array(
			'weight' => 110,
			'label' => __( 'Frequency', 'sprout-invoices' ),
			'type' => 'select',
			'options' => array(
					'weekly' => __( 'Weekly', 'sprout-invoices' ),
					'monthly' => __( 'Monthly', 'sprout-invoices' ),
					'quarterly' => __( 'Quarterly', 'sprout-invoices' ),
					'yearly' => __( 'Yearly', 'sprout-invoices' ),
					'custom' => __( 'Custom', 'sprout-invoices' ),
				),
			'default' => $frequency,
		);

		$day_option_input = '<input type="number" name="sa_recurring_invoice_custom_freq" id="sa_recurring_invoice_custom_freq" class="small-input" placeholder="10" max="364" size="4" value="'.$custom_freq.'">';

		$fields['custom_freq'] = array(
			'weight' => 110.1,
			'label' => '',
			'type' => 'bypass',
			'output' => sprintf( __( 'Created Every %s Days', 'sprout-invoices' ), $day_option_input ),
		);

		$fields = apply_filters( 'si_recurring_invoice_submission_fields', $fields );
		uasort( $fields, array( __CLASS__, 'sort_by_weight' ) );
		return $fields;
	}
}
