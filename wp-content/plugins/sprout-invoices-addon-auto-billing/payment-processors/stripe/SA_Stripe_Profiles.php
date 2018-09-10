<?php

/**
 * Stripe is cool so this shouldn't be too hard...
 *
 * @package SI
 * @subpackage Payment SI_Credit_Card_Processors
 */
class SA_Stripe_Profiles extends SI_Credit_Card_Processors {
	const MODE_TEST = 'test';
	const MODE_LIVE = 'live';
	const MODAL_JS_OPTION = 'si_use_stripe_js_modal';
	const DISABLE_JS_OPTION = 'si_use_stripe_js';
	const API_SECRET_KEY_OPTION = 'si_stripe_secret_key';
	const API_SECRET_KEY_TEST_OPTION = 'si_stripe_secret_key_test';
	const API_PUB_KEY_OPTION = 'si_stripe_pub_key';
	const API_PUB_KEY_TEST_OPTION = 'si_stripe_pub_key_test';

	// plaid
	const PLAID_API_CLIENT_ID = 'si_stripe_plaid_client_id';
	const PLAID_API_PUB_KEY = 'si_stripe_plaid_pub_key';
	const PLAID_API_SECRET_KEY = 'si_stripe_plaid_sec_key';
	const AJAX_ACTION_PLAID_TOKEN = 'sb_plaid_store_account_id';

	const STRIPE_CUSTOMER_KEY_USER_META = 'si_stripe_customer_id_v1'; // backwards/stripe compat.
	const PLAID_ACCOUNT_TOKEN = 'si_plaid_account_id_v1'; // backwards/stripe compat.

	const TOKEN_INPUT_NAME = 'stripe_charge_token';

	const CONVENIENCE_FEE_PERCENTAGE = 'si_stripe_service_fee';

	const API_MODE_OPTION = 'si_stripe_mode';
	const CURRENCY_CODE_OPTION = 'si_paypal_currency';
	const PAYMENT_METHOD = 'Credit (Stripe Profiles)';
	const PAYMENT_SLUG = 'stripe_profiles';

	protected static $instance;
	protected static $api_mode = self::MODE_TEST;
	private static $disable_stripe_js;
	private static $api_secret_key_test;
	private static $api_pub_key_test;
	private static $api_secret_key;
	private static $api_pub_key;
	private static $plaid_api_client_id;
	private static $plaid_api_secret_key;
	private static $plaid_api_pub_key;
	private static $currency_code = 'usd';

	public static function get_instance() {
		if ( ! ( isset( self::$instance ) && is_a( self::$instance, __CLASS__ ) ) ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function is_test() {
		return self::MODE_TEST === self::$api_mode;
	}

	public function get_payment_method() {
		return self::PAYMENT_METHOD;
	}

	public function get_slug() {
		return self::PAYMENT_SLUG;
	}

	public function bank_supported() {
		if ( '' === self::$plaid_api_pub_key ) {
			return false;
		}
		return true;
	}

	public static function is_active() {
		$enabled = SI_Payment_Processors::enabled_processors();
		return in_array( __CLASS__, $enabled );
	}

	public static function get_convenience_fee() {
		if ( method_exists( 'SI_Service_Fee', 'get_service_fee' )	) {
			$service_fee = SI_Service_Fee::get_service_fee( 'SA_Stripe_Profiles' );
			return $service_fee;
		}
		return get_option( self::CONVENIENCE_FEE_PERCENTAGE, false );
	}

	public static function register() {
		self::add_payment_processor( __CLASS__, __( 'Stripe Profiles' , 'sprout-invoices' ) );

		if ( ! self::is_active() ) {
			return;
		}

		add_action( 'si_credit_card_payment_fields', array( __CLASS__, 'modify_credit_form' ) );

		add_action( 'init', array( get_class(), 'modify_payment_controls' ), 1000 );

		add_filter( 'si_auto_billing_checking_account_fields', array( __CLASS__, 'modify_bank_fields' ) );

		add_action( 'wp_ajax_' . self::AJAX_ACTION_PLAID_TOKEN, array( get_class(), 'callback_for_plaid_token' ) );
		add_action( 'wp_ajax_nopriv_' . self::AJAX_ACTION_PLAID_TOKEN, array( get_class(), 'callback_for_plaid_token' ) );

	}

	public static function public_name() {
		if ( self::bank_supported() ) {
			return __( 'Credit Card or Bank Transfer' , 'sprout-invoices' );
		}
		return __( 'Credit Card' , 'sprout-invoices' );
	}

	public static function checkout_options() {
		$option = array(
			'icons' => array(
				SI_URL . '/resources/front-end/img/visa.png',
				SI_URL . '/resources/front-end/img/mastercard.png',
				SI_URL . '/resources/front-end/img/amex.png',
				SI_URL . '/resources/front-end/img/discover.png',
				),
			'label' => __( 'Credit Card' , 'sprout-invoices' ),
			'accepted_cards' => array(
				'visa',
				'mastercard',
				'amex',
				'diners',
				// 'discover',
				'jcb',
				// 'maestro'
				),
			);
		return $option;
	}

	protected function __construct() {
		parent::__construct();
		self::$api_mode = get_option( self::API_MODE_OPTION, self::MODE_TEST );
		self::$currency_code = get_option( self::CURRENCY_CODE_OPTION, 'usd' );

		self::$api_secret_key = get_option( self::API_SECRET_KEY_OPTION, '' );
		self::$api_pub_key = get_option( self::API_PUB_KEY_OPTION, '' );
		self::$api_secret_key_test = get_option( self::API_SECRET_KEY_TEST_OPTION, '' );
		self::$api_pub_key_test = get_option( self::API_PUB_KEY_TEST_OPTION, '' );

		self::$plaid_api_client_id = get_option( self::PLAID_API_CLIENT_ID, '' );
		self::$plaid_api_pub_key = get_option( self::PLAID_API_PUB_KEY, '' );
		self::$plaid_api_secret_key = get_option( self::PLAID_API_SECRET_KEY, '' );

		if ( ! self::is_active() ) {
			return;
		}

		// Remove review pages
		add_filter( 'si_checkout_pages', array( $this, 'remove_review_checkout_page' ) );

	}

	public static function modify_payment_controls() {
		add_action( 'si_head', array( __CLASS__, 'si_add_css_js' ) );
		add_action( 'si_footer', array( __CLASS__, 'add_plaid_js' ) );

	}

	/**
	 * The review page is unnecessary
	 *
	 * @param array   $pages
	 * @return array
	 */
	public function remove_review_checkout_page( $pages ) {
		unset( $pages[ SI_Checkouts::REVIEW_PAGE ] );
		return $pages;
	}

	/**
	 * Remove routing and bank account fields since it wont be used.
	 * @param  array  $bank_fields
	 * @return $bank_fields
	 */
	public static function modify_bank_fields( $bank_fields = array() ) {
		unset( $bank_fields['bank_routing'] );
		unset( $bank_fields['bank_account'] );
		$bank_fields['bank_account'] = array(
			'type' => 'bypass',
			'weight' => 90,
			'output' => sprintf( '<button class="button" id="plaid_auth">%s</button>', __( 'Authorize Bank Account' , 'sprout-invoices' ) ),
		);
		uasort( $bank_fields, array( __CLASS__, 'sort_by_weight' ) );
		return $bank_fields;
	}

	/**
	 * Hooked on init add the settings page and options.
	 *
	 */
	public static function register_settings( $settings = array() ) {
		// Settings
		$settings['payments'] = array(
			'si_stripe_settings' => array(
				'title' => __( 'Stripe Settings' , 'sprout-invoices' ),
				'weight' => 200,
				'settings' => array(
					self::API_MODE_OPTION => array(
						'label' => __( 'Mode' , 'sprout-invoices' ),
						'option' => array(
							'type' => 'radios',
							'options' => array(
								self::MODE_LIVE => __( 'Live' , 'sprout-invoices' ),
								self::MODE_TEST => __( 'Test' , 'sprout-invoices' ),
								),
							'default' => self::$api_mode,
							),
						),
					self::API_SECRET_KEY_OPTION => array(
						'label' => __( 'Live Secret Key' , 'sprout-invoices' ),
						'option' => array(
							'type' => 'text',
							'default' => self::$api_secret_key,
							),
						),
					self::API_PUB_KEY_OPTION => array(
						'label' => __( 'Live Publishable Key' , 'sprout-invoices' ),
						'option' => array(
							'type' => 'text',
							'default' => self::$api_pub_key,
							),
						),
					self::API_SECRET_KEY_TEST_OPTION => array(
						'label' => __( 'Test Secret Key' , 'sprout-invoices' ),
						'option' => array(
							'type' => 'text',
							'default' => self::$api_secret_key_test,
							),
						),
					self::API_PUB_KEY_TEST_OPTION => array(
						'label' => __( 'Test Publishable Key' , 'sprout-invoices' ),
						'option' => array(
							'type' => 'text',
							'default' => self::$api_pub_key_test,
							),
						),
					self::PLAID_API_CLIENT_ID => array(
						'label' => __( 'Plaid client_id' , 'sprout-invoices' ),
						'option' => array(
							'type' => 'text',
							'default' => self::$plaid_api_client_id,
							'description' => __( 'Leave this option blank if you do not want to enable ACH payments via Stripe.' , 'sprout-invoices' ),
							),
						),
					self::PLAID_API_PUB_KEY => array(
						'label' => __( 'Plaid public_key' , 'sprout-invoices' ),
						'option' => array(
							'type' => 'text',
							'default' => self::$plaid_api_pub_key,
							'description' => __( 'Leave this option blank if you do not want to enable ACH payments via Stripe.' , 'sprout-invoices' ),
							),
						),
					self::PLAID_API_SECRET_KEY => array(
						'label' => __( 'Plaid secret' , 'sprout-invoices' ),
						'option' => array(
							'type' => 'text',
							'default' => self::$plaid_api_secret_key,
							'description' => __( 'Leave this option blank if you do not want to enable ACH payments via Stripe.' , 'sprout-invoices' ),
							),
						),
					self::CURRENCY_CODE_OPTION => array(
						'label' => __( 'Currency Code' , 'sprout-invoices' ),
						'option' => array(
							'type' => 'text',
							'default' => self::$currency_code,
							'attributes' => array( 'class' => 'small-text' ),
							),
						),
				),
			),
		);
		return $settings;
	}

	/**
	 * Add the stripe token input to be passed
	 * @return
	 */
	public static function modify_credit_form() {
		printf( '<input type="hidden" name="%s" value="">', self::TOKEN_INPUT_NAME );
		echo '<div id="stripe_errors"></div><!-- #stripe_errors -->';
	}

	/**
	 * Process a payment
	 *
	 * @param SI_Checkouts $checkout
	 * @param SI_Invoice $invoice
	 * @return SI_Payment|bool FALSE if the payment failed, otherwise a Payment object
	 */
	public function process_payment( SI_Checkouts $checkout, SI_Invoice $invoice ) {

		$payment_source_id = $this->process_payment_for_source_id( $checkout, $invoice );
		$customer_id = self::get_payment_profile_by_invoice( $invoice );
		if ( ! $payment_source_id ) {
			self::set_error_messages( 'ERROR: Please verify your payment options.' );
			return false;
		}

		$charge_reciept = self::charge_stripe( $invoice, $payment_source_id );

		do_action( 'si_log', __CLASS__ . '::' . __FUNCTION__ . ' - Stripe Response', $charge_reciept );

		if ( ! $charge_reciept ) {
			return false;
		}

		$payment_id = SI_Payment::new_payment( array(
			'payment_method' => self::PAYMENT_METHOD,
			'invoice' => $invoice->get_id(),
			'amount' => self::convert_cents_to_money( $charge_reciept['amount'] ),
			'data' => array(
			'invoice_id' => $invoice->get_id(),
			'transaction_id' => $charge_reciept['id'],
			'profile_id' => $customer_id,
			'payment_profile_id' => $payment_source_id,
			'live' => ( self::$api_mode == self::MODE_LIVE ),
			'api_response' => $charge_reciept,
			),
		), SI_Payment::STATUS_AUTHORIZED );
		if ( ! $payment_id ) {
			return false;
		}

		// Go through the routine and do the authorized actions and then complete.
		$payment = SI_Payment::get_instance( $payment_id );
		do_action( 'payment_authorized', $payment );
		$payment->set_status( SI_Payment::STATUS_COMPLETE );
		do_action( 'payment_complete', $payment );

		// Return the payment
		return $payment;
	}


	public static function charge_stripe( SI_Invoice $invoice, $payment_source_id = '' ) {

		$customer_id = self::get_payment_profile_by_invoice( $invoice );
		$payment_amount = ( si_has_invoice_deposit( $invoice->get_id() ) ) ? $invoice->get_deposit() : $invoice->get_balance();
		if ( isset( $_POST['si_payment_amount_change'] ) && is_numeric( $_POST['si_payment_amount_option'] ) ) {
			if ( $_POST['si_payment_amount_option'] < $payment_amount ) {
					$payment_amount = $_POST['si_payment_amount_option'];
			}
		}

		$_service_fee = self::get_convenience_fee();
		if ( is_numeric( $_service_fee ) && 0.00 < $_service_fee ) {
			$service_fee = $payment_amount * ( $_service_fee / 100 );
			$payment_amount = si_get_number_format( $payment_amount + $service_fee );
		}

		self::setup_stripe();
		try {

			// Charge the card!
			$charge = \Stripe\Charge::create(
				array(
					'amount' => self::convert_money_to_cents( sprintf( '%0.2f', $payment_amount ) ),
					'currency' => self::get_currency_code( $invoice->get_id() ),
					'source' => $payment_source_id,
					'customer' => $customer_id,
					'description' => get_the_title( $invoice->get_id() ),
				)
			);

		} catch (Exception $e) {
			do_action( 'si_error', __CLASS__ . '::' . __FUNCTION__ . ' error: ', $e->getMessage() );
			return false;
		}

		$receipt = array(
			'id' => $charge->id,
			'status' => $charge->status,
			'amount' => $charge->amount,
			'customer' => $charge->customer,
			'card' => $charge->source->id,
			'service_fee' => $service_fee,
			'service_fee_perc' => $_service_fee,
		);

		return $receipt;
	}

	//////////////////////
	// Checkout Utility //
	//////////////////////

	/**
	 * Process the checkout and deterine the payment source
	 * @param  SI_Checkouts $checkout
	 * @param  SI_Invoice   $invoice
	 * @return source_id
	 */
	public function process_payment_for_source_id( SI_Checkouts $checkout, SI_Invoice $invoice ) {

		$customer_id = self::get_payment_profile_by_invoice( $invoice );
		$payment_source_id = 0;
		if ( isset( $_POST['sa_credit_payment_method'] ) ) {
			$payment_source_id = $_POST['sa_credit_payment_method'];
		} // If the payment profile id wasn't passed check the checkout cache for the cim profile id
		elseif ( isset( $checkout->cache['ab_payment_profile'] ) ) {
			$payment_source_id = $checkout->cache['ab_payment_profile'];
		}

		if ( 'new_credit' === $payment_source_id ) {
			$payment_source_id = false;
		}

		if ( 'new_bank' === $payment_source_id ) {
			$payment_source_id = false;
		}

		// could be a new token since front-end js can create it.
		$token_id = '';
		if ( isset( $_POST[ self::TOKEN_INPUT_NAME ] ) && $_POST[ self::TOKEN_INPUT_NAME ] !== '' ) {
			$token_id = $_POST[ self::TOKEN_INPUT_NAME ];
		}

		if ( ! $payment_source_id ) {
			// token was passed so store the payment source, since it's new but created via frontend js
			if ( '' !== $token_id ) {
				$payment_source_id = $this->add_payment_source_from_token( $customer_id, $token_id, $invoice );
			} // store the profile based on what's passed.
			else {
				$payment_source_id = $this->add_payment_source_from_checkout( $customer_id, $checkout, $invoice );
			}
			do_action( 'si_log', __CLASS__ . '::' . __FUNCTION__ . ' - adding payment profile: ', $payment_source_id );

		}
		return $payment_source_id;
	}

	private function add_payment_source_from_token( $customer_id, $token_id, SI_Invoice $invoice ) {

		$customer = self::get_customer_object( $customer_id );
		if ( ! $customer ) {
			do_action( 'si_error', __CLASS__ . '::' . __FUNCTION__ . ' response: ', $customer );
			return;
		}

		self::setup_stripe();
		try {

			$card = $customer->sources->create( array( 'source' => $token_id ) );

		} catch (Exception $e) {
			self::set_error_messages( $e->getMessage() );
			return false;
		}

		$source_id = $card->id;

		if ( ! isset( $_POST['sa_credit_store_payment_profile'] ) && ! isset( $checkout->cache['sa_credit_store_payment_profile'] ) ) {
			Sprout_Billings_Profiles::hide_payment_profile( $source_id, $invoice->get_id() );
		}

		return $source_id;
	}

	private function add_payment_source_from_checkout( $customer_id, SI_Checkouts $checkout, SI_Invoice $invoice ) {

		$customer = self::get_customer_object( $customer_id );
		if ( ! $customer ) {
			do_action( 'si_error', __CLASS__ . '::' . __FUNCTION__ . ' response: ', $customer );
			return;
		}

		$card_data = array(
			'object'          => 'card',
			'number'          => $this->cc_cache['cc_number'],
			'name'            => $checkout->cache['billing']['first_name'] . ' ' . $checkout->cache['billing']['last_name'],
			'exp_month'       => $this->cc_cache['cc_expiration_month'],
			'exp_year'        => substr( $this->cc_cache['cc_expiration_year'], -2 ),
			'cvc'             => $this->cc_cache['cc_cvv'],
			'address_line1'   => $checkout->cache['billing']['street'],
			'address_line2'   => '',
			'address_city'    => $checkout->cache['billing']['city'],
			'address_zip'     => $checkout->cache['billing']['postal_code'],
			'address_state'   => $checkout->cache['billing']['zone'],
			'address_country' => $checkout->cache['billing']['country'],
		);

		self::setup_stripe();
		try {

			$card = $customer->sources->create( array( 'source' => $card_data ) );

		} catch (Exception $e) {
			self::set_error_messages( $e->getMessage() );
			return false;
		}

		$source_id = $card->id;

		if ( ! isset( $_POST['sa_credit_store_payment_profile'] ) && ! isset( $checkout->cache['sa_credit_store_payment_profile'] ) ) {
			Sprout_Billings_Profiles::hide_payment_profile( $source_id, $invoice->get_id() );
		}

		return $source_id;
	}

	////////////////////////
	// Profile Managment //
	////////////////////////


	public static function get_payment_profile_by_invoice( $invoice ) {
		$update_payment_profile_id = false;
		$customer_id = Sprout_Billings_Profiles::get_customer_profile_id( $invoice->get_id(), true );

		if ( ! $customer_id ) {
			$update_payment_profile_id = true;
			$user_id = si_whos_user_id_is_paying( $invoice );
			$customer_id = get_user_meta( $user_id, self::STRIPE_CUSTOMER_KEY_USER_META, true );
		}

		if ( ! $customer_id ) {
			$customer_id = self::create_customer_profile_from_invoice( $invoice );
		}

		if ( $update_payment_profile_id ) {
			$client_id = SI_Sprout_Billings::get_client_id( $invoice->get_id() );
			Sprout_Billings_Profiles::update_client_payment_profile_id( $client_id, $customer_id );
		}
		return $customer_id;

	}

	public static function create_customer_profile_from_invoice( $invoice ) {
		$client_id = SI_Sprout_Billings::get_client_id( $invoice->get_id() );
		$client = SI_Client::get_instance( $client_id );
		$user = si_who_is_paying( $invoice );

		if ( ! is_a( $client, 'SI_Client' ) ) {
			do_action( 'si_error', __CLASS__ . '::' . __FUNCTION__ . ' - create_profile not a valid client_id: ', $client_id );
			return false;
		}

		$params = array(
					'description' => $client->get_title(),
					'email'       => $user->user_email,
				);
		return self::create_customer_profile( $params, $client_id );

	}

	public static function create_customer_profile( $params = array(), $client_id = 0 ) {
		self::setup_stripe();
		try {
			$customer = \Stripe\Customer::create( $params );
		} catch (Exception $e) {
			do_action( 'si_error', __CLASS__ . '::' . __FUNCTION__ . ' error: ', $e->getMessage() );
			return;
		}

		// Save profile id with the user for future reference.
		Sprout_Billings_Profiles::update_client_payment_profile_id( $client_id, $customer->id );

		return $customer->id;
	}

	///////////////////////////////////////////////
	// callbacks from SI_Auto_Payment_Processors //
	///////////////////////////////////////////////

	/**
	 * Return the customer object
	 * @param  integer $profile_id
	 * @param  integer $invoice_id
	 * @return customer object
	 */
	public static function get_customer_profile( $profile_id = 0, $invoice_id = 0 ) {
		if ( ! $profile_id && $invoice_id ) {
			$profile_id = Sprout_Billings_Profiles::get_customer_profile_id( $invoice_id );
			if ( ! $profile_id ) {
				return false;
			}
		}

		$stripe_customer = self::get_customer_object( $profile_id );

		if ( ! $stripe_customer ) {
			return false;
		}

		return $stripe_customer;
	}

	public static function get_customer_object( $customer_id ) {
		self::setup_stripe();
		try {
			$stripe_customer = \Stripe\Customer::retrieve( $customer_id );
		} catch (Exception $e) {
			do_action( 'si_error', __CLASS__ . '::' . __FUNCTION__ . ' error: ', $e->getMessage() );
			return false;
		}
		return $stripe_customer;
	}

	/**
	 * Validate the customer based on customer id
	 * @param  integer $profile_id
	 * @return bool
	 */
	public static function validate_profile_id( $profile_id = 0 ) {
		if ( $profile_id ) {
			$customer_profile = self::get_customer_profile( $profile_id );
		}

		self::setup_stripe();
		try {
			$stripe_customer = \Stripe\Customer::retrieve( $profile_id );
			if ( isset( $stripe_customer->deleted ) && $stripe_customer->deleted ) {
				return false;
			}
		} catch (Exception $e) {
			do_action( 'si_error', __CLASS__ . '::' . __FUNCTION__ . ' error: ', $e->getMessage() );
			return false;
		}

		return $profile_id;
	}

	public static function create_payment_profile( $customer_id = 0, $client_id = 0, $payment_info ) {
		if ( ! $customer_id ) {
			$client = SI_Client::get_instance( $client_id );
			if ( ! is_a( $client, 'SI_Client' ) ) {
				return __( 'Client not available for payment profile creation.' , 'sprout-invoices' );
			}
			$client_users = $client->get_associated_users();
			$client_user_id = array_shift( $client_users );
			$user = get_userdata( $client_user_id );

			$params = array(
					'description' => $client->get_title(),
					'email'       => $user->user_email,
				);
			$customer_id = self::create_customer_profile( $params, $client_id );
		}

		$customer = self::get_customer_profile( $customer_id );
		if ( ! $customer ) {
			do_action( 'si_error', __CLASS__ . '::' . __FUNCTION__ . ' response: ', $customer );
			return;
		}

		$source_data = array(
			'object'          => 'card',
			'number'          => $payment_info['cc_number'],
			'name'            => $payment_info['billing']['first_name'] . ' ' . $payment_info['billing']['last_name'],
			'exp_month'       => $payment_info['cc_expiration_month'],
			'exp_year'        => substr( $payment_info['cc_expiration_year'], -2 ),
			'cvc'             => $payment_info['cc_cvv'],
			'address_line1'   => $payment_info['billing']['street'],
			'address_line2'   => '',
			'address_city'    => $payment_info['billing']['city'],
			'address_zip'     => $payment_info['billing']['postal_code'],
			'address_state'   => $payment_info['billing']['zone'],
			'address_country' => $payment_info['billing']['country'],
		);

		self::setup_stripe();
		try {

			$card = $customer->sources->create( array( 'source' => $source_data ) );

		} catch (Exception $e) {
			return array(
						'error' => true,
						'message' => $e->getMessage(),
					);
		}

		$source_id = $card->id;

		Sprout_Billings_Profiles::save_payment_profile( $source_id, $client_id );
		return $source_id;
	}

	/**
	 * Deletes a card from the customers sources
	 * @param  integer  $profile_id
	 * @param  integer $invoice_id
	 * @return null
	 */
	public static function remove_payment_profile( $payment_source_id, $invoice_id = 0 ) {
		$customer = self::get_customer_profile( 0, $invoice_id );
		if ( ! $customer ) {
			do_action( 'si_error', __CLASS__ . '::' . __FUNCTION__ . ' response: ', $customer );
			return;
		}
		$customer->sources->retrieve( $payment_source_id )->delete();
		return true;
	}

	/**
	 * Build an array of card from the profile
	 *
	 * @param integer $profile_id CIM profile ID
	 * @return array
	 */
	public static function get_payment_profiles( $profile_id = 0 ) {
		// Get profile object
		$customer = self::get_customer_profile( $profile_id );
		if ( ! $customer ) {
			do_action( 'si_error', __CLASS__ . '::' . __FUNCTION__ . ' response: ', $customer );
			return array();
		}
		$sources = $customer->sources->all();
		if ( empty( $sources ) ) {
			return array();
		}
		// Create an array of payment profile card numbers
		$cards = array();
		foreach ( $sources->data as $key => $card ) {
			$desc = ( isset( $card->brand ) ) ? $card->brand : $card->bank_name ;
			$cards[ $card->id ] = $desc . ': ' . $card->last4;
		}
		return $cards;
	}

	///////////////
	// Auto Bill //
	///////////////

	public static function manual_payment_attempt( $invoice_id, $payment_source_id ) {
		$profile_id = Sprout_Billings_Profiles::get_customer_profile_id( $invoice_id );
		if ( ! $profile_id ) {
			return false;
		}
		$invoice = SI_Invoice::get_instance( $invoice_id );
		$charge_reciept = self::charge_stripe( $invoice, $payment_source_id );

		if ( ! $charge_reciept ) {
			return false;
		}

		$payment_id = SI_Payment::new_payment( array(
			'payment_method' => self::PAYMENT_METHOD,
			'invoice' => $invoice_id,
			'amount' => self::convert_cents_to_money( $charge_reciept['amount'] ),
			'data' => array(
			'invoice_id' => $invoice_id,
			'transaction_id' => $charge_reciept['id'],
			'profile_id' => $profile_id,
			'payment_profile_id' => $payment_source_id,
			'live' => ( self::$api_mode == self::MODE_LIVE ),
			'api_response' => $charge_reciept,
			),
		), SI_Payment::STATUS_AUTHORIZED );
		if ( ! $payment_id ) {
			return false;
		}

		// Go through the routine and do the authorized actions and then complete.
		$payment = SI_Payment::get_instance( $payment_id );
		do_action( 'payment_authorized', $payment );
		$payment->set_status( SI_Payment::STATUS_COMPLETE );
		do_action( 'payment_complete', $payment );

		return (int) $payment_id;
	}

	//////////////
	// Checkout //
	//////////////

	public static function si_add_css_js() {
		echo '<script type="text/javascript" src="https://js.stripe.com/v1/"></script>';
		echo '<script type="text/javascript" src="' . SA_ADDON_AUTO_BILLING_URL . '/payment-processors/stripe/resources/stripe.js"></script>';

		$pub_key = ( get_option( self::API_MODE_OPTION, self::MODE_TEST ) === self::MODE_TEST ) ? get_option( self::API_PUB_KEY_TEST_OPTION, '' ) : get_option( self::API_PUB_KEY_OPTION, '' );
		$si_js_object = array(
			'pub_key' => $pub_key,
			'token_input' => self::TOKEN_INPUT_NAME,
		);

		if ( '' !== self::$plaid_api_pub_key ) {
			$env = ( get_option( self::API_MODE_OPTION, self::MODE_TEST ) === self::MODE_TEST ) ? 'development' : 'production';
			$env = apply_filters( 'si_plaid_env', $env );
			$si_js_object += array(
				'plaid_env' => $env,
				'callback_action' => self::AJAX_ACTION_PLAID_TOKEN,
				'plaid_pub_key' => self::$plaid_api_pub_key,
				'clientName' => __( 'Authorize Bank Transfer', 'sprout-invoices' ),
				'proceedMessage' => __( 'Please proceed by submitting your payment below.', 'sprout-invoices' ),
			);
		} ?>
			<script type="text/javascript">
				/* <![CDATA[ */
				var si_stripe_js_object = <?php echo wp_json_encode( $si_js_object ); ?>;
				/* ]]> */
			</script>
		<?php
	}

	public static function add_plaid_js() {
		$env = ( get_option( self::API_MODE_OPTION, self::MODE_TEST ) === self::MODE_TEST ) ? 'development' : 'production';
		$env = apply_filters( 'si_plaid_env', $env );
		if ( '' !== self::$plaid_api_pub_key ) {
			?>
				<script src="https://cdn.plaid.com/link/v2/stable/link-initialize.js"></script>
				<script>

					var $plaid_auth_button = jQuery('#plaid_auth'),
						$auth_button_wrap = jQuery('.sa-control-group.bankbank_account');

					// hide/show the button based on selection.
					$auth_button_wrap.hide();
					jQuery('#sa_credit_payment_method_bank').on( 'click', function(event){
						$auth_button_wrap.show();
					});
					jQuery('#sa_credit_payment_method_credit').on( 'click', function(event){
						$auth_button_wrap.hide();
					});

				</script>
			<?php
		}
	}

	/////////////////
	// Plaid Token //
	/////////////////


	public static function callback_for_plaid_token() {
		$account_id = $_REQUEST['account_id'];
		$public_token = $_REQUEST['public_token'];
		if ( '' === $account_id ) {
			wp_send_json_error( array( 'message' => __( 'No Account ID Provided.', 'sprout-invoices' ) ) );
		}
		if ( '' === $public_token ) {
			wp_send_json_error( array( 'message' => __( 'No Public Token Provided.', 'sprout-invoices' ) ) );
		}

		$client_id = SI_Sprout_Billings::get_client_id( $_REQUEST['invoice_id'] );
		if ( ! $client_id ) {
			wp_send_json_error( array( 'message' => __( 'No Client Associated.', 'sprout-invoices' ) ) );
		}

		$token = self::plaid_token_exchange( $public_token, $account_id );
		if ( is_array( $token ) ) {
			wp_send_json_error( $token );
		}
		self::set_plaid_account_token( $client_id, $token );
		wp_send_json_success( $token );
	}

	public static function plaid_token_exchange( $public_token = '', $account_id = '' ) {

		// backwards compat for old filter
		$env = apply_filters( 'si_plaid_env', '' );
		if ( '' !== $env ) {
			$api_domain = ( 'development' === $env ) ? 'https://development.plaid.com' : 'https://api.plaid.com';
		} else {
			$api_domain = ( get_option( self::API_MODE_OPTION, self::MODE_TEST ) === self::MODE_TEST ) ? 'https://development.plaid.com' : 'https://api.plaid.com';
		}

		// new filter
		$api_domain = apply_filters( 'si_plaid_api_domain', $api_domain );

		// exchange token
		$post_data = array(
			'client_id' => self::$plaid_api_client_id,
			'secret' => self::$plaid_api_secret_key,
			'public_token' => $public_token,
		);
		// api
		$raw_response = wp_remote_post( $api_domain . '/item/public_token/exchange', array(
				'headers' => array(
					'Content-Type' => 'application/json',
				),
				'method' => 'POST',
				'body' => json_encode( $post_data ),
				'timeout' => apply_filters( 'http_request_timeout', 30 ),
				'sslverify' => false,
		) );

		// get access token
		$exchange_response = json_decode( wp_remote_retrieve_body( $raw_response ) );

		if ( ! isset( $exchange_response->access_token ) ) {
			if ( isset( $exchange_response->message ) ) {
				return array(
					'message' => $exchange_response->message,
				);
			} else {

				do_action( 'si_error', 'Plaid Token Exchange', $post_data, false );
				do_action( 'si_error', 'Plaid Token Exchange', $exchange_response, false );

				return array(
					'message' => sprintf( __( 'No access_token provided in exchange (%s). Please try again later.', 'sprout-invoices' ), $bank_response->request_id ),
				);
			}
		}

		// bank token
		$post_data = array(
			'client_id' => self::$plaid_api_client_id,
			'secret' => self::$plaid_api_secret_key,
			'access_token' => $exchange_response->access_token,
			'account_id' => $account_id,
		);
		// api
		$raw_response = wp_remote_post( $api_domain . '/processor/stripe/bank_account_token/create', array(
				'headers' => array(
					'Content-Type' => 'application/json',
				),
				'method' => 'POST',
				'body' => json_encode( $post_data ),
				'timeout' => apply_filters( 'http_request_timeout', 30 ),
				'sslverify' => false,
		) );

		$bank_response = json_decode( wp_remote_retrieve_body( $raw_response ) );
		if ( ! isset( $bank_response->stripe_bank_account_token ) ) {
			if ( isset( $bank_response->message ) ) {
				return array(
					'message' => $bank_response->message,
				);
			} else {

				do_action( 'si_error', 'Plaid Token Exchange', $post_data, false );
				do_action( 'si_error', 'Plaid Token Exchange', $bank_response, false );

				return array(
					'message' => sprintf( __( 'A Stripe Bank Account Token was not returned (%s). Please try again later. ', 'sprout-invoices' ), $bank_response->request_id ),
				);
			}
		}

		return $bank_response->stripe_bank_account_token;
	}

	public static function get_plaid_account_token( $client_id = 0 ) {
		$token = get_post_meta( $client_id, self::PLAID_ACCOUNT_TOKEN, true );
		return $token;
	}

	public static function set_plaid_account_token( $client_id = 0, $token = 0 ) {
		$token = update_post_meta( $client_id, self::PLAID_ACCOUNT_TOKEN, $token );
		return $token;
	}


	//////////////
	// Utility //
	//////////////

	private static function setup_stripe() {
		if ( ! class_exists( 'Stripe' ) ) {
			require_once 'stripe-php-6.13.0/init.php';
		} else {
			do_action( 'si_error', __CLASS__ . '::' . __FUNCTION__ . ' - the Stripe class is already included.', null );
		}
		try {
			// Setup the API
			$key = ( self::$api_mode === self::MODE_TEST ) ? self::$api_secret_key_test : self::$api_secret_key ;

			\Stripe\Stripe::setAppInfo(
				'Sprout Invoices',
				SI_VERSION,
				'https://sproutapps.co/sprout-invoices/'
			);

			\Stripe\Stripe::setApiKey( $key );

		} catch ( Exception $e ) {
			self::set_error_messages( $e->getMessage() );
			return false;
		}
	}

	private static function get_currency_code( $invoice_id ) {
		return apply_filters( 'si_currency_code', self::$currency_code, $invoice_id, self::PAYMENT_METHOD );
	}

	private static function convert_money_to_cents( $value ) {
		// strip out commas
		$value = preg_replace( '/\,/i', '', $value );
		// strip out all but numbers, dash, and dot
		$value = preg_replace( '/([^0-9\.\-])/i', '', $value );
		// make sure we are dealing with a proper number now, no +.4393 or 3...304 or 76.5895,94
		if ( ! is_numeric( $value ) ) {
			return 0.00;
		}
		// convert to a float explicitly
		$value = (float) $value;
		return round( $value, 2 ) * 100;
	}

	private static function convert_cents_to_money( $value ) {
		// strip out commas
		$value = preg_replace( '/\,/i', '', $value );
		// strip out all but numbers, dash, and dot
		$value = preg_replace( '/([^0-9\.\-])/i', '', $value );
		// make sure we are dealing with a proper number now, no +.4393 or 3...304 or 76.5895,94
		if ( ! is_numeric( $value ) ) {
			return 0.00;
		}
		// convert to a float explicitly
		return number_format( floatval( $value / 100 ), 2 );
	}


	/**
	 * Grabs error messages from a PayPal response and displays them to the user
	 *
	 * @param array   $response
	 * @param bool    $display
	 * @return void
	 */
	private function set_error_messages( $message, $display = true ) {
		if ( $display ) {
			self::set_message( $message, self::MESSAGE_STATUS_ERROR );
		} else {
			do_action( 'si_error', __CLASS__ . '::' . __FUNCTION__ . ' - error message from paypal', $message );
		}
	}
}
SA_Stripe_Profiles::register();
