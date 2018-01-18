<?php

/**
 * Authorize.net onsite credit card payment processor.
 *
 * @package SI
 * @subpackage Payment SI_Offsite_Processors
 */
class SA_Braintree extends SI_Credit_Card_Processors {
	const MODE_TEST = 'sandbox';
	const MODE_LIVE = 'production';
	const API_API_KEY_OPTION = 'si_bt_api_key';
	const API_USERNAME_OPTION = 'si_bt_username';
	const API_PASSWORD_OPTION = 'si_bt_password';

	const API_MODE_OPTION = 'si_bt_mode';
	const PAYMENT_METHOD = 'Credit (Braintree)';
	const PAYMENT_SLUG = 'bt';

	protected static $instance;
	private static $client_sdk;
	private static $api_mode = self::MODE_TEST;
	private static $api_merchant_id = '';
	private static $api_key = '';
	private static $api_private_key = '';
	private static $currency_code = 'AUD';

	public static function get_instance() {
		if ( ! ( isset( self::$instance ) && is_a( self::$instance, __CLASS__ ) ) ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	public function get_payment_method() {
		return self::PAYMENT_METHOD;
	}

	public function get_slug() {
		return self::PAYMENT_SLUG;
	}

	public static function returned_from_offsite() {
		return isset( $_GET['bt_payment'] ) && $_GET['bt_payment'] == 1;
	}

	public static function register() {
		self::add_payment_processor( __CLASS__, __( 'Braintree' , 'sprout-invoices' ) );
	}


	public static function public_name() {
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
				// 'diners',
				'discover',
				// 'jcb',
				// 'maestro'
				),
			);
		return apply_filters( 'si_bt_checkout_options', $option );
	}

	protected function __construct() {
		parent::__construct();
		self::$api_merchant_id = get_option( self::API_API_KEY_OPTION, '' );
		self::$api_key = get_option( self::API_USERNAME_OPTION, '' );
		self::$api_private_key = get_option( self::API_PASSWORD_OPTION, '' );
		self::$api_mode = get_option( self::API_MODE_OPTION, self::MODE_TEST );

		if ( is_admin() ) {
			add_action( 'init', array( get_class(), 'register_options' ) );
		}

		// Remove pages
		add_filter( 'si_checkout_pages', array( $this, 'remove_checkout_pages' ) );
	}

	/**
	 * The review page is unnecessary
	 *
	 * @param array   $pages
	 * @return array
	 */
	public function remove_checkout_pages( $pages ) {
		unset( $pages[ SI_Checkouts::REVIEW_PAGE ] );
		return $pages;
	}

	/**
	 * Hooked on init add the settings page and options.
	 *
	 */
	public static function register_options() {
		// Settings
		$settings = array(
			'si_bt_settings' => array(
				'title' => __( 'Braintree' , 'sprout-invoices' ),
				'weight' => 200,
				'tab' => self::get_settings_page( false ),
				'settings' => array(
					self::API_MODE_OPTION => array(
						'label' => __( 'Mode' , 'sprout-invoices' ),
						'option' => array(
							'type' => 'radios',
							'options' => array(
								self::MODE_LIVE => __( 'Live' , 'sprout-invoices' ),
								self::MODE_TEST => __( 'Sandbox' , 'sprout-invoices' ),
								),
							'default' => self::$api_mode,
							),
						),
					self::API_API_KEY_OPTION => array(
						'label' => __( 'Merchant ID' , 'sprout-invoices' ),
						'option' => array(
							'type' => 'text',
							'default' => get_option( self::API_API_KEY_OPTION, '' ),
							),
						),
					self::API_USERNAME_OPTION => array(
						'label' => __( 'Public Key' , 'sprout-invoices' ),
						'option' => array(
							'type' => 'text',
							'default' => get_option( self::API_USERNAME_OPTION, '' ),
							),
						),
					self::API_PASSWORD_OPTION => array(
						'label' => __( 'Private Key' , 'sprout-invoices' ),
						'option' => array(
							'type' => 'text',
							'default' => get_option( self::API_PASSWORD_OPTION, '' ),
							),
						),
					),
				),
			);
		do_action( 'sprout_settings', $settings, self::SETTINGS_PAGE );
	}

	/**
	 * Process a payment
	 *
	 * @param SI_Checkouts $checkout
	 * @param SI_Invoice $invoice
	 * @return SI_Payment|bool FALSE if the payment failed, otherwise a Payment object
	 */
	public function process_payment( SI_Checkouts $checkout, SI_Invoice $invoice ) {
		$payment_data = $this->make_payment( $checkout, $invoice );
		if ( ! $payment_data ) {
			return false;
		}

		$payment_amount = ( si_has_invoice_deposit( $invoice->get_id() ) ) ? $invoice->get_deposit() : $invoice->get_balance();
		$payment_id = SI_Payment::new_payment( array(
			'payment_method' => $this->get_payment_method(),
			'invoice' => $invoice->get_id(),
			'amount' => $payment_amount,
			'data' => array(
			'live' => ( self::$api_mode == self::MODE_LIVE ),
			'api_response' => $payment_data,
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

		return $payment;
	}

	public static function init_bt_sdk() {
		require_once( SA_ADDON_BRAINTREE_PATH . '/lib/braintree-php-3.12.0/lib/autoload.php' );

		Braintree\Configuration::environment( self::$api_mode );
		Braintree\Configuration::merchantId( self::$api_merchant_id );
		Braintree\Configuration::publicKey( self::$api_key );
		Braintree\Configuration::privateKey( self::$api_private_key );

	}

	public function make_payment( SI_Checkouts $checkout, SI_Invoice $invoice ) {
		$client = $invoice->get_client();
		$user = si_who_is_paying( $invoice );
		// User email or none
		$user_email = ( $user ) ? $user->user_email : '' ;
		$payment_amount = ( si_has_invoice_deposit( $invoice->get_id() ) ) ? $invoice->get_deposit() : $invoice->get_balance();
		$payment_amount = si_get_number_format( $payment_amount );

		self::init_bt_sdk();

		// Create customer in braintree Vault
		$result = Braintree_Customer::create( array(
			'firstName' => $checkout->cache['billing']['first_name'],
			'lastName'  => $checkout->cache['billing']['last_name'],
			'email'     => $user_email,
			'creditCard' => array(
			'number'          => $this->cc_cache['cc_number'],
			'cardholderName'  => $checkout->cache['billing']['first_name'] . ' ' . $checkout->cache['billing']['last_name'],
			'expirationMonth' => $this->cc_cache['cc_expiration_month'],
			'expirationYear'  => $this->cc_cache['cc_expiration_year'],
			'cvv'             => $this->cc_cache['cc_cvv'],
			'billingAddress' => array(
			'postalCode'        => $checkout->cache['billing']['postal_code'],
			'streetAddress'     => $checkout->cache['billing']['street'],
			'locality'          => $checkout->cache['billing']['city'],
			'region'            => $checkout->cache['billing']['zone'],
			'countryCodeAlpha2' => self::country_code( $checkout->cache['billing']['country'] ),
			),
			),
		));

		if ( $result->success ) {
			// Save this Braintree_cust_id in DB and use for future transactions too
			$braintree_cust_id = $result->customer->id;
		} else {
			self::set_error_messages( 'ERROR: ' . $result->message );
		}

		$sale = array(
				'customerId' => $braintree_cust_id,
				'amount'   => $payment_amount,
				'orderId'  => $invoice->get_id(),
				'options' => array( 'submitForSettlement' => true ),
			);

		$result = Braintree_Transaction::sale( $sale );

		if ( ! $result->success ) {
			self::set_error_messages( 'ERROR: ' . $result->message );
			return false;
		}

		return $result;
	}


	//////////////
	// Utility //
	//////////////

	private static function country_code( $country = null ) {
		if ( null != $country ) {
			return $country;
		}
		return 'US';
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

	private static function get_currency_code( $invoice_id ) {
		return apply_filters( 'si_currency_code', self::$currency_code, $invoice_id, self::PAYMENT_METHOD );
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
			do_action( 'si_error', __CLASS__ . '::' . __FUNCTION__ . ' - error message from braintree', $message );
		}
	}
}
SA_Braintree::register();
