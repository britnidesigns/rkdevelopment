<?php

/**
 * BluePay onsite credit card payment processor.
 *
 * @package SI
 * @subpackage Payment SI_Credit_Card_Processors
 */
class SA_BluePay extends SI_Credit_Card_Processors {
	const API_ENDPOINT_SANDBOX = 'https://secure.bluepay.com/interfaces/a.net.test';
	const API_ENDPOINT_LIVE = 'https://secure.bluepay.com/gateway/transact.dll';
	const MODE_TEST = 'sandbox';
	const MODE_LIVE = 'live';
	const API_USERNAME_OPTION = 'si_bluepay_username';
	const API_PASSWORD_OPTION = 'si_bluepay_password';

	const API_MODE_OPTION = 'si_bluepay_mode';
	const PAYMENT_METHOD = 'Credit (BluePay)';
	const PAYMENT_SLUG = 'bluepay';

	protected static $instance;
	private static $api_mode = self::MODE_TEST;
	private static $api_username = '';
	private static $api_password = '';

	public static function get_instance() {
		if ( ! ( isset( self::$instance ) && is_a( self::$instance, __CLASS__ ) ) ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function get_api_url() {
		if ( self::$api_mode == self::MODE_LIVE ) {
			return self::API_ENDPOINT_LIVE;
		} else {
			return self::API_ENDPOINT_SANDBOX;
		}
	}

	public function get_payment_method() {
		return self::PAYMENT_METHOD;
	}

	public function get_slug() {
		return self::PAYMENT_SLUG;
	}

	public static function register() {
		self::add_payment_processor( __CLASS__, __( 'BluePay' , 'sprout-invoices' ) );
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
		self::$api_username = get_option( self::API_USERNAME_OPTION, '' );
		self::$api_password = get_option( self::API_PASSWORD_OPTION, '' );
		self::$api_mode = get_option( self::API_MODE_OPTION, self::MODE_TEST );

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
	public static function register_settings( $settings = array() ) {
		// Settings
		$settings['payments'] = array(
			'si_bluepay_settings' => array(
				'title' => __( 'BluePay' , 'sprout-invoices' ),
				'weight' => 200,
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
					self::API_USERNAME_OPTION => array(
						'label' => __( 'API Login ID' , 'sprout-invoices' ),
						'option' => array(
							'type' => 'text',
							'default' => get_option( self::API_USERNAME_OPTION, '' ),
							),
						),
					self::API_PASSWORD_OPTION => array(
						'label' => __( 'Transaction Key' , 'sprout-invoices' ),
						'option' => array(
							'type' => 'text',
							'default' => get_option( self::API_PASSWORD_OPTION, '' ),
							),
						),
					),
				),
			);
		return $settings;
	}

	/**
	 * Process a payment
	 *
	 * @param SI_Checkouts $checkout
	 * @param SI_Invoice $invoice
	 * @return SI_Payment|bool FALSE if the payment failed, otherwise a Payment object
	 */
	public function process_payment( SI_Checkouts $checkout, SI_Invoice $invoice ) {

		$post_data = $this->aim_data( $checkout, $invoice );
		do_action( 'si_log', __CLASS__ . '::' . __FUNCTION__ . ' - BluePay post_data', $post_data );

		// convert NVP to a string
		$post_string = '';
		foreach ( $post_data as $key => $value ) {
			if ( $key == 'x_line_item' ) {
				$post_string .= "{$key}=".$value.'&';
			} else {
				$post_string .= "{$key}=".urlencode( $value ).'&';
			}
		}
		$post_string = rtrim( $post_string, '& ' );
		do_action( 'si_log', __CLASS__ . '::' . __FUNCTION__ . ' - post_string', $post_string );

		// Send request
		$response = wp_remote_post( $this->get_api_url(), array(
				'httpversion' => '1.1',
				'body' => $post_string,
				'timeout' => apply_filters( 'http_request_timeout', 30 ),
				'sslverify' => false,
		) );
		do_action( 'si_log', __CLASS__ . '::' . __FUNCTION__ . ' - BluePay RAW $response', $response );

		if ( is_wp_error( $response ) ) {
			return false;
		}

		$response = explode( $post_data['x_delim_char'], wp_remote_retrieve_body( $response ) );
		$response_code = $response[0]; // The response we want to validate on
		do_action( 'si_log', __CLASS__ . '::' . __FUNCTION__ . ' - BluePay Net Response', $response );

		if ( $response_code != 1 ) {
			$this->set_error_messages( $response[3] );
			return false;
		}

		if ( 'DUPLICATE' === $response[3] ) {
			$this->set_error_messages( __( 'Payment Cancelled: Duplicate charge detected.' , 'sprout-invoices' ) );
			return false;
		}

		// Success

		$payment_id = SI_Payment::new_payment( array(
			'payment_method' => $this->get_payment_method(),
			'invoice' => $invoice->get_id(),
			'amount' => $post_data['x_amount'],
			'data' => array(
			'live' => ( self::$api_mode == self::MODE_LIVE ),
			'api_response' => $response,
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



	/**
	 * Build the NVP data array for submitting the current checkout to BluePay as an Authorization request
	 */
	private function aim_data( SI_Checkouts $checkout, SI_Invoice $invoice ) {
		$client = $invoice->get_client();

		$user = si_who_is_paying( $invoice );
		// User email or none
		$user_email = ( $user ) ? $user->user_email : '' ;
		$user_id = ( $user ) ? $user->ID : 0 ;

		$AIMdata = array();
		$AIMdata['x_login'] = self::$api_username;
		$AIMdata['x_tran_key'] = self::$api_password;

		$AIMdata['x_version'] = '3.1';
		$AIMdata['x_delim_data'] = 'TRUE';
		$AIMdata['x_delim_char'] = '|';
		$AIMdata['x_relay_response'] = 'FALSE';
		$AIMdata['x_type'] = 'AUTH_CAPTURE';
		$AIMdata['x_method'] = 'CC';

		$AIMdata['x_card_num'] = $this->cc_cache['cc_number'];
		$AIMdata['x_exp_date'] = substr( '0' . $this->cc_cache['cc_expiration_month'], -2 ) . substr( $this->cc_cache['cc_expiration_year'], -2 );
		$AIMdata['x_card_code'] = $this->cc_cache['cc_cvv'];

		$AIMdata['x_amount'] = si_get_number_format( $invoice->get_balance() );

		$AIMdata['x_first_name'] = $checkout->cache['billing']['first_name'];
		$AIMdata['x_last_name'] = $checkout->cache['billing']['last_name'];
		$AIMdata['x_address'] = $checkout->cache['billing']['street'];
		$AIMdata['x_city'] = $checkout->cache['billing']['city'];
		$AIMdata['x_state'] = $checkout->cache['billing']['zone'];
		$AIMdata['x_zip'] = $checkout->cache['billing']['postal_code'];

		$AIMdata['x_email'] = $user_email;
		$AIMdata['x_cust_id'] = $user_id;

		$AIMdata['x_invoice_num'] = $invoice->get_id();

		if ( self::$api_mode == self::MODE_TEST ) {
			$AIMdata['x_test_request'] = 'TRUE';
		}

		$AIMdata = apply_filters( 'si_bluepay_nvp_data', $AIMdata );

		return $AIMdata;
	}


	//////////////
	// Utility //
	//////////////


	private function get_currency_code( $invoice_id ) {
		return apply_filters( 'si_currency_code', self::$currency_code, $invoice_id, self::PAYMENT_METHOD );
	}

	/**
	 * Grabs error messages from a BluePay response and displays them to the user
	 *
	 * @param array   $response
	 * @param bool    $display
	 * @return void
	 */
	private function set_error_messages( $response, $display = true ) {
		if ( $display ) {
			self::set_message( $response, self::MESSAGE_STATUS_ERROR );
		} else {
			do_action( 'si_error', __CLASS__ . '::' . __FUNCTION__ . ' - Bluepay Error Response', $response );
		}
	}
}
SA_BluePay::register();
