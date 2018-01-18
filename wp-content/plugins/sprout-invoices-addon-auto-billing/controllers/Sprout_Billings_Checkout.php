<?php

/**
 * Auto Billing Checkout Controller
 *
 *
 * @package SI_Sprout_Billings_Checkout
 */
class SI_Sprout_Billings_Checkout extends SI_Sprout_Billings {

	public static function init() {
		// autobill options
		add_action( 'si_credit_card_payment_controls', array( __CLASS__, 'ask_to_auto_bill_on_checkout' ) );

		// save options
		add_action( 'payment_authorized', array( __CLASS__, 'process_payment_maybe_save_option' ), 20, 1 );

		// Checkout template updates
		add_action( 'si_head', array( __CLASS__, 'add_style_and_js' ) );
		add_filter( 'sa_credit_fields', array( __CLASS__, 'add_card_selection' ), 100, 2 );
		add_action( 'si_credit_card_payment_fields', array( __CLASS__, 'add_checking_info' ) );

		// Processing checkout
		add_action( 'si_checkout_action_'.SI_Checkouts::PAYMENT_PAGE, array( __CLASS__, 'process_payment_page_for_ab' ), 20, 1 );
		add_filter( 'si_validate_credit_card_cc', array( __CLASS__, 'maybe_not_check_credit_cards' ), 100, 2 );

		add_action( 'pre_si_invoice_view', array( __CLASS__, 'add_message_if_auto_pay' ) );
	}

	///////////////////////
	// Auto Bill Options //
	///////////////////////

	public static function ask_to_auto_bill_on_checkout() {
		$default = false;
		$invoice_id = get_the_ID();

		$client_id = self::get_client_id( $invoice_id );

		if ( ! $client_id || SI_Sprout_Billings::can_autocharge_client( $client_id ) ) {
			$default = apply_filters( 'sb_autopay_optin', true, $client_id );
		}

		$selection = self::load_addon_view_to_string( 'checkout/autopay-selection', array(
			'autopay_date_options' => self::day_of_month_selection(),
			'default_autopay_date' => self::get_client_autopay_date( $client_id ),
			'default_autopay' => $default,
			'company_name' => si_get_company_name(),
		), true );
		print $selection;
	}

	public static function process_payment_maybe_save_option( SI_Payment $payment ) {
		$client = $payment->get_client();
		$payment_data = $payment->get_data();
		if ( is_a( $client, 'SI_Client' ) ) {
			$client_id = $client->get_id();
		} else {
			$invoice_id = $payment_data['invoice_id'];
			$client_id = self::get_client_id( $invoice_id );
		}
		if ( isset( $_POST['sa_billing_allow_to_autobill'] ) ) {
			$payment_profile_id = $payment_data['payment_profile_id'];
			self::save_client_payment_profile( $client_id, $payment_profile_id );
			self::set_message( __( 'You\'ve successfully setup auto pay for all future invoices!', 'sprout-invoices' ), self::MESSAGE_STATUS_INFO );

		}

		if ( isset( $_POST['sa_billing_allow_to_autobill'] ) && isset( $_POST['sa_billing_autopay_billdate'] ) && is_numeric( $_POST['sa_billing_autopay_billdate'] ) ) {
			$day = $_POST['sa_billing_autopay_billdate'];
			self::set_client_autopay_date( $client_id, $day );

			if ( 0 === (int) $day ) {
				self::set_message( __( 'Auto pay has been setup for all future invoices. Thank you!', 'sprout-invoices' ), self::MESSAGE_STATUS_INFO );
			} elseif ( 32 === (int) $day ) {
				self::set_message( __( 'Auto pay has been setup for the last day of each month. Thank you!', 'sprout-invoices' ), self::MESSAGE_STATUS_INFO );
			} else {
				self::set_message( sprintf( __( 'Auto pay has been setup for the %s of each month. Thank you!', 'sprout-invoices' ), sa_day_ordinal_formatter( $day ) ), self::MESSAGE_STATUS_INFO );
			}
		}
	}

	/////////////////////////////////
	// Profile Options at Checkout //
	/////////////////////////////////

	public static function add_style_and_js() {
		if ( 'estimate' === si_get_doc_context() ) {
			return;
		}
		echo '<link rel="stylesheet" id="si_payments_checkout" href="' . SA_ADDON_AUTO_BILLING_URL . '/resources/front-end/css/si-payments-checkout.css" type="text/css" media="all">';
		echo '<script type="text/javascript" id="si_payments_checkout" src="' . SA_ADDON_AUTO_BILLING_URL . '/resources/front-end/js/si-payments-checkout.js"></script>';
	}

	public static function add_card_selection( $fields, $checkout ) {
		$invoice = $checkout->get_invoice();
		$invoice_id = $invoice->get_id();
		$client_id = self::get_client_id( $invoice_id );
		// If multiple payments isn't selected add the credit-card option
		$fields['payment_method'] = array();
		$fields['payment_method']['type'] = 'bypass';
		$fields['payment_method']['weight'] = 0;
		$fields['payment_method']['label'] = __( 'Payment Method' , 'sprout-invoices' );
		$fields['payment_method']['required'] = true;

		// Add CC options to the checkout fields
		$profile_id = Sprout_Billings_Profiles::get_customer_profile_id( $invoice_id );
		$cards = Sprout_Billings_Profiles::get_client_payment_profiles( $client_id, true, apply_filters( 'si_billings_cached_payment_profiles_at_checkout', false ) );

		// Payment selection view
		$selection = self::load_addon_view_to_string( 'checkout/card-selection', array(
			'cards' => $cards,
			'profile_id' => $profile_id,
			'invoice_id' => $invoice_id,
		), true );
		$fields['payment_method']['output'] = $selection;

		$fields['store_payment_profile'] = array(
			'type' => 'checkbox',
			'weight' => 100,
			'label' => __( 'Save Credit Card' , 'sprout-invoices' ),
			'default' => true,
		);
		return apply_filters( 'si_auto_billing_card_selection', $fields );
	}


	public static function add_checking_info() {
		if ( ! SI_Auto_Payment_Processors::bank_supported() ) {
			return;
		}
		$fields = self::checking_account_fields();
		sa_form_fields( $fields, 'bank' );
	}

	public static function checking_account_fields() {
		$bank_fields = array();

		$bank_fields['section_heading'] = array(
			'type' => 'bypass',
			'weight' => 1,
			'output' => sprintf( '<span class="sa-form-field-radio clearfix"><label for="sa_credit_payment_method_bank"><input type="radio" name="sa_credit_payment_method" id="sa_credit_payment_method_bank" value="new_bank"><b>%s</b></label></span>', __( 'New Checking Account' , 'sprout-invoices' ) ),
		);
		$bank_fields['bank_routing'] = array(
			'type' => 'text',
			'weight' => 5,
			'label' => __( 'Routing Number' , 'sprout-invoices' ),
			'attributes' => array(
				//'autocomplete' => 'off',
			),
			'required' => true,
		);
		$bank_fields['bank_account'] = array(
			'type' => 'text',
			'weight' => 10,
			'label' => __( 'Checking Account' , 'sprout-invoices' ),
			'attributes' => array(
				//'autocomplete' => 'off',
			),
			'required' => true,
		);
		$bank_fields['store_payment_profile'] = array(
			'type' => 'checkbox',
			'weight' => 100,
			'label' => __( 'Save Bank Info' , 'sprout-invoices' ),
			'default' => true,
		);
		return apply_filters( 'si_auto_billing_checking_account_fields', $bank_fields );
	}

	//////////////////////
	// Process Checkout //
	//////////////////////

	public static function process_payment_page_for_ab( SI_Checkouts $checkout ) {
		if ( isset( $_POST['sa_credit_payment_method'] ) && '' !== $_POST['sa_credit_payment_method'] ) {
			$checkout->cache['ab_payment_profile'] = $_POST['sa_credit_payment_method'];
		}

		if ( isset( $_POST['sa_credit_store_payment_profile'] ) ) {
			$checkout->cache['sa_credit_store_payment_profile'] = true;
		}

		// Banking options
		if ( isset( $_POST['sa_bank_bank_routing'] ) && '' !== $_POST['sa_bank_bank_routing']  ) {
			$checkout->cache['bank_routing'] = $_POST['sa_bank_bank_routing'];
		}

		if ( isset( $_POST['sa_bank_bank_account'] ) && '' !== $_POST['sa_bank_bank_account'] ) {
			$checkout->cache['bank_account'] = $_POST['sa_bank_bank_account'];
		}

		if ( isset( $_POST['sa_bank_store_payment_profile'] ) ) {
			$checkout->cache['sa_credit_store_payment_profile'] = true;
		}

		if ( isset( $_POST['sa_billing_autopay_billdate'] ) ) {
			$checkout->cache['sb_autopay_billdate'] = $_POST['sa_billing_autopay_billdate'];
		}

		if ( isset( $_POST['si_payment_amount_change'] ) ) {
			if ( $_POST['si_payment_amount_change'] ) {
				$checkout->cache['payment_amount'] = $_POST['si_payment_amount_option'];
			}
		}
	}

	public static function maybe_not_check_credit_cards( $valid, SI_Checkouts $checkout ) {
		// previous stored profile
		if ( isset( $_POST['sa_credit_payment_method'] ) && '' !== $_POST['sa_credit_payment_method'] ) {
			self::clear_messages();
			return true;
		}

		// bank
		if ( isset( $_POST['sa_bank_bank_account'] ) && '' !== $_POST['sa_bank_bank_account'] ) {
			self::clear_messages();
			return true;
		}
		return $valid;
	}

	// message

	public static function add_message_if_auto_pay() {
		$invoice_id = get_the_id();
		if ( SI_Invoice::POST_TYPE !== get_post_type( $invoice_id ) ) {
			return;
		}

		$client_id = self::get_client_id( $invoice_id, 0 );
		$profile_id = Sprout_Billings_Profiles::get_customer_profile_id( $invoice_id );
		$cards = Sprout_Billings_Profiles::get_client_payment_profiles( $client_id, true, apply_filters( 'si_billings_cached_payment_profiles_at_checkout', false ) );

		if ( isset( $cards[ $profile_id ] ) ) {

			$card = $cards[ $profile_id ];
			$day = self::get_client_autopay_date( $client_id );

			self::set_message( sprintf( __( 'You have already set up %s for autopay on the %s of the month.<br/>There is nothing you need to do.', 'sprout-invoices' ), $card, sa_day_ordinal_formatter( $day ) ), self::MESSAGE_STATUS_INFO );
		}
	}
}
