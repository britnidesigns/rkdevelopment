<?php

/**
 * Time_Tracking Controller
 *
 * @package Sprout_Invoice
 * @subpackage Time_Tracking
 */
class SI_Invoices_Recurring_Addons extends SI_Invoices_Recurring {

	public static function init() {

		if ( is_admin() ) {

			// client dashboard
			add_action( 'si_client_dashboard_invoice_info_row', array( __CLASS__, 'add_recurring_invoice_info' ) );

			// Estimate Acceptance Add-on Compat
			add_action( 'si_estimate_acceptance_fields', array( __CLASS__, 'add_estimate_acceptance_meta_fields' ) );
			add_action( 'save_estimate_acceptance_meta', array( __CLASS__, 'save_estimate_acceptance_meta' ) );

		}

	}


	/////////////////////////
	// Estimate Acceptance //
	/////////////////////////

	public static function add_estimate_acceptance_meta_fields( $fields = array() ) {
		$estimate = SI_Estimate::get_instance( get_the_id() );
		if ( ! is_a( $estimate, 'SI_Estimate' ) ) {
			return $fields;
		}
		$recurring_fields = SI_Invoices_Recurring_Settings::recurring_options( $estimate );
		$recurring_fields['recurring_heading'] = array(
			'weight' => 99,
			'label' => __( 'Recurring Invoice Settings', 'sprout-invoices' ),
			'type' => 'heading',
		);
		$fields = array_merge( $recurring_fields, $fields );
		uasort( $fields, array( __CLASS__, 'sort_by_weight' ) );
		return $fields;
	}

	public static function save_estimate_acceptance_meta( $post_id = 0 ) {
		self::set_as_not_recurring( $post_id );

		$start_time = ( isset( $_POST['sa_estimate_acceptance_start_time'] ) ) ? strtotime( $_POST['sa_estimate_acceptance_start_time'] ) : current_time( 'timestamp' );
		self::set_start_time( $post_id, $start_time );

		$frequency = ( isset( $_POST['sa_estimate_acceptance_frequency'] ) ) ? $_POST['sa_estimate_acceptance_frequency'] : '' ;
		self::set_frequency( $post_id, $frequency );

		$frequency_days = ( isset( $_POST['sa_estimate_acceptance_custom_freq'] ) ) ? $_POST['sa_estimate_acceptance_custom_freq'] : '' ;
		self::set_frequency_custom( $post_id, $frequency_days );

		if ( isset( $_POST['sa_estimate_acceptance_is_recurring'] ) && $_POST['sa_estimate_acceptance_is_recurring'] ) {
			self::set_recurring( $post_id );
		}
	}

	public static function add_recurring_invoice_info( $invoice ) {
		$invoice_id = $invoice->get_id();
		$start_date = self::get_start_time( $invoice_id );
		$cloned_id = self::get_cloned( $invoice_id );
		if ( ! self::is_recurring( $invoice_id ) && ! $cloned_id ) {
			return;
		}
		if ( ! $cloned_id ) {
			printf( __( '<small>The billing agreement started on <em>%s</em>.</small>', 'sprout-invoices' ), date_i18n( get_option( 'date_format' ), $start_date ) );
		} else {
			$start_date = self::get_start_time( $cloned_id );
			printf( __( '<small>Invoice generated from a billing agreement started on <em>%s</em>.</small>', 'sprout-invoices' ), date_i18n( get_option( 'date_format' ), $start_date ) );
		}
		echo '<style type="text/css">.estimate_info_row_wrap { display: none; }</style>';
	}
}
