<?php

/**
 * Auto Billing Checkout Controller
 *
 *
 * @package SI_Sprout_Billings_Checkout
 */
class SI_Auto_Payment_Processors extends SI_Sprout_Billings {

	public static function init() {
		// nothing as of yet.
	}

	public static function create_new_payment_profile( $profile_id = 0, $client_id = 0, $payment_info = array() ) {

		$payment_profile_id = __( 'Payment Gateway not supported.' , 'sprout-invoices' );
		$active_payment_processor = SI_Payment_Processors::get_active_credit_card_processor();
		if ( method_exists( $active_payment_processor, 'create_payment_profile' ) ) {
			$payment_profile_id = $active_payment_processor->create_payment_profile( $profile_id, $client_id, $payment_info );
		}

		return $payment_profile_id;

	}

	public static function save_payment_profile( $profile_id = 0, $invoice_id = 0 ) {
		$response = __( 'Saved' , 'sprout-invoices' );
		$active_payment_processor = SI_Payment_Processors::get_active_credit_card_processor();
		if ( method_exists( $active_payment_processor, 'save_payment_profile' ) ) {
			$response = $active_payment_processor->save_payment_profile( $profile_id, $invoice_id );
		}
		return $response;

	}

	public static function remove_payment_profile( $profile_id = 0, $invoice_id = 0 ) {
		$response = __( 'Removed' , 'sprout-invoices' );
		$active_payment_processor = SI_Payment_Processors::get_active_credit_card_processor();
		if ( method_exists( $active_payment_processor, 'remove_payment_profile' ) ) {
			$response = $active_payment_processor->remove_payment_profile( $profile_id, $invoice_id );
		}
		return $response;
	}

	public static function get_payment_profiles( $profile_id = 0 ) {
		if ( ! $profile_id ) {
			return array();
		}

		$cards = array();
		$active_payment_processor = SI_Payment_Processors::get_active_credit_card_processor();
		if ( method_exists( $active_payment_processor, 'get_payment_profiles' ) ) {
			$cards = $active_payment_processor->get_payment_profiles( $profile_id );
		}
		return $cards;

	}

	public static function validate_profile_id( $profile_id = 0 ) {
		$active_payment_processor = SI_Payment_Processors::get_active_credit_card_processor();
		if ( method_exists( $active_payment_processor, 'validate_profile_id' ) ) {
			$profile_id = $active_payment_processor->validate_profile_id( $profile_id );
		}

		return $profile_id;
	}

	public static function bank_supported() {
		$supported = false;
		$active_payment_processor = SI_Payment_Processors::get_active_credit_card_processor();
		if ( method_exists( $active_payment_processor, 'bank_supported' ) ) {
			$supported = $active_payment_processor->bank_supported();
		}

		return $supported;
	}

	public static function attempt_payment( $invoice_id = 0, $payment_profile_id = 0 ) {

		$payment_id = __( 'Payment Gateway not supported.' , 'sprout-invoices' );

		$active_payment_processor = SI_Payment_Processors::get_active_credit_card_processor();
		if ( method_exists( $active_payment_processor, 'manual_payment_attempt' ) ) {
			$payment_id = $active_payment_processor->manual_payment_attempt( $invoice_id, $payment_profile_id );
		}
		return $payment_id;
	}
}
