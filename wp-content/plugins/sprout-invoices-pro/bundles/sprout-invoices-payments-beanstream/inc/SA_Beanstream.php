<?php

/**
 * Authorize.net onsite credit card payment processor.
 *
 * @package SI
 * @subpackage Payment SI_Credit_Card_Processors
 */
class SA_BeanStream extends SI_Credit_Card_Processors {
	const HASH_ALGO = 'md5'; //Hash algorithms, use md5 or sha-1
	const TERM_URL = ''; //3d Secure return address (use address of index.php)
	const TRN_URL = 'https://www.beanstream.com/scripts/process_transaction.asp?';
	const AUTH_URL = 'https://www.beanstream.com/scripts/process_transaction_auth.asp?';
	const IO_FOUND = ''; //Interac approved page
	const IO_NON_FOUND = ''; //Interac declined page

	const MODE_TEST = 'sandbox';
	const MODE_LIVE = 'live';

	const API_USERNAME_OPTION = 'si_beanstream_username';
	const API_PASSWORD_OPTION = 'si_beanstream_password';
	const API_MERCHANT_ID = 'si_beanstream_merch_id';
	const API_HASKEY = 'si_beanstream_haskey';

	const API_MODE_OPTION = 'si_beanstream_mode';
	const PAYMENT_METHOD = 'Credit (Beanstream)';
	const PAYMENT_SLUG = 'beanstream';

	protected static $instance;
	private static $api_mode = self::MODE_TEST;
	private static $api_username = '';
	private static $api_password = '';
	private static $api_merch_id = '';
	private static $api_hash = '';
	private static $api_hash_value = '';
	private static $trn_type = 'P';
	private static $currency_code = 'CAD';

	public static function get_instance() {
		if ( ! ( isset( self::$instance ) && is_a( self::$instance, __CLASS__ ) ) ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function get_api_url() {
		if ( self::$api_mode == self::MODE_LIVE ) {
			return self::TRN_URL;
		} else {
			return self::TRN_URL;
		}
	}

	public function get_payment_method() {
		return self::PAYMENT_METHOD;
	}

	public function get_slug() {
		return self::PAYMENT_SLUG;
	}

	public static function register() {
		self::add_payment_processor( __CLASS__, __( 'BeanStream' , 'sprout-invoices' ) );
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
		return $option;
	}

	protected function __construct() {
		parent::__construct();
		self::$api_username = get_option( self::API_USERNAME_OPTION, '' );
		self::$api_password = get_option( self::API_PASSWORD_OPTION, '' );
		self::$api_merch_id = get_option( self::API_MERCHANT_ID, '' );
		self::$api_hash = get_option( self::API_HASKEY, '' );
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
			'si_beanstream_settings' => array(
				'title' => __( 'BeanStream' , 'sprout-invoices' ),
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
					self::API_MERCHANT_ID => array(
						'label' => __( 'Merchant ID' , 'sprout-invoices' ),
						'option' => array(
							'type' => 'text',
							'default' => get_option( self::API_MERCHANT_ID, '' ),
							),
						),
					self::API_HASKEY => array(
						'label' => __( 'Hash Key' , 'sprout-invoices' ),
						'option' => array(
							'type' => 'text',
							'default' => get_option( self::API_HASKEY, '' ),
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

		$post_data = $this->process_data( $checkout, $invoice );
		do_action( 'si_log', __CLASS__ . '::' . __FUNCTION__ . ' - BeanStream post_data', $post_data );

		// Send request
		$response = wp_remote_post( $this->get_api_url(), array(
				'method' => 'POST',
				'body' => $post_data,
				'timeout' => apply_filters( 'http_request_timeout', 30 ),
				'sslverify' => false,
		) );
		do_action( 'si_log', __CLASS__ . '::' . __FUNCTION__ . ' - BeanStream RAW $response', $response );

		if ( is_wp_error( $response ) ) {
			return false;
		}
		if ( $response['response']['code'] != '200' ) {
			return false;
		}

		$response = wp_parse_args( wp_remote_retrieve_body( $response ) );
		do_action( 'si_log', __CLASS__ . '::' . __FUNCTION__ . ' - BeanStream parsed $response', $response );

		if ( '1' != $response['trnApproved'] ) {
			$this->set_error_messages( explode( '<LI>', $response['messageText'] ) );
			return false;
		}

		// Success
		$payment_id = SI_Payment::new_payment( array(
			'payment_method' => $this->get_payment_method(),
			'invoice' => $invoice->get_id(),
			'amount' => si_get_number_format( $invoice->get_balance() ),
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

	public function process_data( SI_Checkouts $checkout, SI_Invoice $invoice ) {

		$client = $invoice->get_client();

		$user = si_who_is_paying( $invoice );
		// User email or none
		$user_email = ( $user ) ? $user->user_email : '' ;
		$user_id = ( $user ) ? $user->ID : 0 ;

		$date = strtotime( $this->cc_cache['cc_expiration_month'].'/'.$this->cc_cache['cc_expiration_month'].'/'.$this->cc_cache['cc_expiration_year'] );

		$NVPdata = array();
		$NVPdata['ordName'] = __( 'Invoice: ' , 'sprout-invoices' ) . $invoice->get_invoice_id();
		$NVPdata['trnOrderNumber'] = $invoice->get_id();
		$NVPdata['paymentMethod'] = 'CC';

		$NVPdata['trnCardOwner'] = $checkout->cache['billing']['first_name'] .' '.$checkout->cache['billing']['last_name'];
		$NVPdata['trnCardNumber'] = $this->cc_cache['cc_number'];
		$NVPdata['trnExpMonth'] = date( 'm', $date );
		$NVPdata['trnExpYear'] = date( 'y', $date );
		$NVPdata['trnCardCvd'] = $this->cc_cache['cc_cvv'];

		$NVPdata['trnAmount'] = si_get_number_format( $invoice->get_balance() );

		$NVPdata['ordEmailAddress'] = $user_email;

		$NVPdata['ordAddress1'] = $checkout->cache['billing']['street'];
		$NVPdata['ordCity'] = $checkout->cache['billing']['city'];
		$NVPdata['ordProvince'] = $checkout->cache['billing']['zone'];
		$NVPdata['ordPostalCode'] = $checkout->cache['billing']['postal_code'];
		$NVPdata['ordCountry'] = $checkout->cache['billing']['country'];
		$NVPdata['ordPhoneNumber'] = ( ! empty( $checkout->cache['billing']['phone'] ) ) ? $checkout->cache['billing']['phone'] : '1111111111';

		$NVPdata['customerIp'] = $_SERVER['REMOTE_ADDR'];

		$NVPdata = apply_filters( 'si_beanstream_nvp_data', $NVPdata );

		$req_data = http_build_query( $NVPdata );

		// Formats all user data into a string
		$trn_string = 'requestType=BACKEND&merchant_id=' . self::$api_merch_id . '&termUrl=' . self::TERM_URL . '&ioFunded=' . self::IO_FOUND . '&ioNonFunded=' . self::IO_NON_FOUND . '&username=' . self::$api_username . '&password=' . self::$api_password . '&trnType=' . self::$trn_type . '&' . $req_data;

		// Optional Hash validation
		return self::hash_transaction( $trn_string );
	}


	//////////////
	// Utility //
	//////////////


	private static function hash_transaction( $trn_string ) {
		//Must be enabled in Gateway account and unique key must be entered into const hashKey

		//Formats a hash of the transaction string
		$api_hash_value = hash( self::HASH_ALGO, $trn_string . self::$api_hash );

		//Appends hashValue to the end of the transaction string
		$trn_string = $trn_string . '&hashValue=' . $api_hash_value;

		$trn_string = apply_filters( 'si_beanstream_trn_string', $trn_string );

		return $trn_string;
	}

	private function get_currency_code( $invoice_id ) {
		return apply_filters( 'si_currency_code', self::$currency_code, $invoice_id, self::PAYMENT_METHOD );
	}

	/**
	 * Grabs error messages from a Authorize response and displays them to the user
	 *
	 * @param array   $response
	 * @param bool    $display
	 * @return void
	 */
	private function set_error_messages( $response, $display = true ) {
		foreach ( $response as $key => $value ) {
			if ( $display ) {
				$message = str_replace( '<br>', '', $value );
				self::set_message( $message, self::MESSAGE_STATUS_ERROR );
			} else {
				do_action( 'si_error', __CLASS__ . '::' . __FUNCTION__ . ' - Beanstream Error Response', $response );
			}
		}
	}
}
SA_BeanStream::register();
