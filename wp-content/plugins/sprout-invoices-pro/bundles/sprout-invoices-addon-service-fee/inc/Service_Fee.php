<?php

/**
 * SI_Service_Fee Controller
 *
 * @package Sprout_Invoice
 * @subpackage SI_Service_Fee
 */
class SI_Service_Fee extends SI_Controller {
	const OPTION_PRE = 'si_service_fee_';

	public static function init() {

		// settings
		add_filter( 'si_payment_settings', array( __CLASS__, 'register_options' ) );

		// add fee on checkout
		add_action( 'si_checkout_action_'.SI_Checkouts::PAYMENT_PAGE, array( __CLASS__, 'maybe_add_processing_fee_from_checkout' ), -100, 1 );

		// failed checkout, remove the fee
		add_action( 'checkout_failed', array( __CLASS__, 'remove_processing_fee_from_checkout' ) );

		add_action( 'save_post', array( __CLASS__, 'maybe_add_service_fee_auto' ), 10, 2 );

		// Stripe compatible
		if ( class_exists( 'SA_Stripe' ) ) {
			add_filter( 'si_stripe_js_data_attributes', array( __CLASS__, 'adjust_stripe_total' ), 10, 2 );
			add_action( 'payment_complete', array( __CLASS__, 'add_fee_after_stripe_payment' ) );
		}

		add_action( 'si_default_theme_payment_options_desc', array( __CLASS__, 'add_service_fee_info_to_payment_options' ) );
		add_action( 'si_doc_line_items', array( __CLASS__, 'add_service_fee_info_to_payment_options' ), 100 );

	}

	public static function get_service_fee( $class = '' ) {
		if ( is_object( $class ) ) {
			$class = get_class( $class );
		}
		$option = get_option( self::OPTION_PRE . $class, 0 );
		return $option;
	}

	public static function get_service_fees() {

		$fees = array();
		$payment_gateways = SI_Payment_Processors::get_registered_processors();
		foreach ( $payment_gateways as $class => $label ) {
			$fees[ $class ] = array(
					'label' => $label,
					'fee' => self::get_service_fee( $class ),
				);
		}
		return $fees;
	}

	/**
	 * Hooked on init add the settings page and options.
	 *
	 */
	public static function register_options( $settings = array() ) {
		// Settings
		$settings['si_service_fee_settings'] = array(
				'title' => __( 'Service Fee Options', 'sprout-invoices' ),
				'weight' => 300,
				// 'tab' => SI_Payment_Processors::get_settings_page( false ),
				'settings' => array(),
			);

		$enabled_gateways = SI_Payment_Processors::enabled_processors();
		$payment_gateways = SI_Payment_Processors::get_registered_processors();

		foreach ( $payment_gateways as $class => $label ) {
			if ( ! in_array( $class, $enabled_gateways ) ) {
				continue;
			}
			$settings['si_service_fee_settings']['settings'][ self::OPTION_PRE . $class ] = array(
					'label' => sprintf( __( '%s Service Fee', 'sprout-invoices' ), $label ),
						'option' => array(
							'type' => 'text',
							'default' => self::get_service_fee( $class ),
							'attributes' => array( 'class' => 'small-text' ),
							'description' => __( 'Percentage based on subtotal (before tax & discounts).' ),
						),
					);
		}
		return $settings;
	}

	public static function maybe_add_processing_fee_from_checkout( SI_Checkouts $checkout ) {
		$invoice = $checkout->get_invoice();
		$payment_processor = get_class( $checkout->get_processor() );

		$service_fee = self::get_service_fee( $payment_processor );
		if ( ! (float) $service_fee ) {
			self::remove_processing_fee_from_checkout( $checkout );
			return;
		}
		$fee_total = $invoice->get_calculated_total( false ) * ( $service_fee / 100 ); //

		$processor_options = $checkout->get_processor()->checkout_options();
		$label = ( isset( $processor_options['label'] ) && '' !== $processor_options['label'] ) ? $processor_options['label'] : 'Payment' ;
		self::add_service_fee( $invoice, $fee_total, sprintf( __( '%s Service Fee', 'sprout-invoices' ), $label ) );

	}

	public static function maybe_add_service_fee_auto( $post_id, $post ) {
		if ( $post->post_status == 'auto-draft' ) {
			return;
		}
		if ( $post->post_type !== SI_Invoice::POST_TYPE ) {
			return;
		}

		$invoice = SI_Invoice::get_instance( $post_id );

		$enabled_gateways = SI_Payment_Processors::doc_enabled_processors( $post_id );

		// Don't allow for multiple service fees, or a fee to be added based on an existing fee that will be overriden.
		// Example, recurring invoice with a fee is duplicated, then the new fee is based on the total including the old fee.
		self::maybe_remove_processing_fee( $invoice );

		// Don't autoamtically add a fee if more than one option
		if ( 1 < count( $enabled_gateways ) ) {
			return;
		}

		$class = array_values( $enabled_gateways )[0];

		$service_fee = self::get_service_fee( $class );
		$fee_total = $invoice->get_calculated_total( false ) * ( $service_fee / 100 );

		self::add_service_fee( $invoice, $fee_total );
	}

	public static function add_service_fee( SI_Invoice $invoice, $fee_total = 0.00, $label = '' ) {
		if ( $fee_total < 0.00 ) {
			return;
		}

		if ( apply_filters( 'si_bypass_add_service_fee', false, $invoice ) ) {
			return;
		}

		// don't add a fee for an invoice that has been paid already
		if ( $invoice->get_status() == SI_Invoice::STATUS_PAID ) {
			return;
		}

		$fees = $invoice->get_fees();

		// remove the previous fee, i.e reset with new fee
		unset( $fees['payment_service_fee'] );

		$fees['payment_service_fee'] = array(
			'label' => ( '' === $label ) ? __( 'Payment Service Fee', 'sprout-invoices' ) : $label,
			'always_show' => true,
			'delete_option' => true,
			'total' => (float) $fee_total,
			'weight' => 26,
		);

		$invoice->save_post_meta( array(
			'_fees' => $fees,
		) );
		$invoice->reset_totals();
	}

	public static function remove_processing_fee_from_checkout( $checkout ) {
		if ( ! is_a( $checkout, 'SI_Checkouts' ) ) {
			$checkout = SI_Checkouts::get_instance();
		}
		$invoice = $checkout->get_invoice();

		self::maybe_remove_processing_fee( $invoice );
	}

	public static function maybe_remove_processing_fee( SI_Invoice $invoice ) {
		// don't remove a fee for an invoice that has been paid already
		if ( $invoice->get_status() == SI_Invoice::STATUS_PAID ) {
			return;
		}

		$fees = $invoice->remove_fee( 'payment_service_fee' );

	}

	public static function adjust_stripe_total( $data_attributes = array() ) {
		$invoice_id = get_the_id();
		$invoice = SI_Invoice::get_instance( $invoice_id );

		$fee_total = self::get_stripe_fee_total( $invoice );

		$subtotal = ( si_has_invoice_deposit( $invoice->get_id() ) ) ? $invoice->get_deposit() : $invoice->get_balance();

		$payment_amount = $subtotal + $fee_total;

		$payment_in_cents = ( round( $payment_amount, 2 ) * 100 );
		$data_attributes['amount'] = $payment_in_cents;

		return $data_attributes;
	}

	public static function get_stripe_fee_total( $invoice ) {
		$service_fee = self::get_service_fee( 'SA_Stripe' );
		$amount = ( si_has_invoice_deposit( $invoice->get_id() ) ) ? $invoice->get_deposit() : $invoice->get_balance();
		$fee_total = floatval( $amount * ( $service_fee / 100 ) );
		return $fee_total;
	}

	public static function add_fee_after_stripe_payment( SI_Payment $payment ) {

		if ( $payment->get_payment_method() !== SA_Stripe::PAYMENT_METHOD ) {
			return;
		}

		$invoice_id = $payment->get_invoice_id();
		$invoice = SI_Invoice::get_instance( $invoice_id );

		$fees = $invoice->get_fees();
		if ( isset( $fees['payment_service_fee'] ) ) {
			return;
		}

		$fee_total = self::get_stripe_fee_total( $invoice );
		self::add_service_fee( $invoice, $fee_total, __( 'Credit Card Service Fee', 'sprout-invoices' ) );

	}


	///////////
	// theme //
	///////////

	public static function add_service_fee_info_to_payment_options() {
		$doc_id = get_the_id();

		$enabled_gateways = SI_Payment_Processors::doc_enabled_processors( $doc_id );

		if ( count( $enabled_gateways ) < 2 ) { // a fee is auto added if there's only one option
			return;
		}

		$payment_gateways = SI_Payment_Processors::get_registered_processors();

		$fee_labels = array();
		foreach ( $payment_gateways as $class => $label ) {
			if ( ! in_array( $class, $enabled_gateways ) ) {
				continue;
			}
			$service_fee = self::get_service_fee( $class );
			if ( 0.001 > $service_fee ) {
				continue;
			}
			if ( SI_Payment_Processors::is_cc_processor( $class ) ) {
				$fee_labels[] = sprintf( __( '%1$s%% with a <b>Credit Card</b> Payment', 'sprout-invoices' ), $service_fee );
			} else {
				$fee_labels[] = sprintf( __( '%1$s%% with a <b>%2$s</b> Payment', 'sprout-invoices' ), $service_fee, str_replace( array( '(onsite submission)', 'Payments Standard' ), array( '', '' ), $label ) );
			}
		}
		if ( empty( $fee_labels ) ) {
			return;
		}

		_e( '<p class="service_fee_message">These payment options will include a service fee:', 'sprout-invoices' ); // paragraph closed below
		printf( '<br/>%s</p>', implode( ', ', $fee_labels ) );

	}
}
