<?php

/**
 * NMI onsite credit card payment processor.
 *
 * @package SI
 * @subpackage Payment SI_Credit_Card_Processors
 */
class SA_NMI extends SI_Credit_Card_Processors {
	const API_ENDPOINT = 'https://secure.networkmerchants.com/api/transact.php';
	const MODE_TEST = 'sandbox';
	const MODE_LIVE = 'live';

	const API_USERNAME_OPTION = 'si_nmi_username';
	const API_PASSWORD_OPTION = 'si_nmi_password';
	const API_MODE_OPTION = 'si_nmi_mode';
	const CURRENCY_CODE_OPTION = 'si_nmi_currency';

	const PAYMENT_METHOD = 'Credit (NMI)';
	const PAYMENT_SLUG = 'nmmi';


	const CONVENIENCE_FEE_PERCENTAGE = 'si_nmi_service_fee';

	protected static $instance;
	private static $api_mode = self::MODE_TEST;
	private static $api_username = '';
	private static $api_password = '';
	private static $currency_code = 'USD';

	public static function get_instance() {
		if ( ! ( isset( self::$instance ) && is_a( self::$instance, __CLASS__ ) ) ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function get_api_url() {
		return self::API_ENDPOINT;
	}

	public function get_payment_method( $check = false ) {
		if ( $check ) {
			return 'Bank (NMI)';
		}

		return self::PAYMENT_METHOD;
	}

	public function get_slug() {
		return self::PAYMENT_SLUG;
	}

	public static function get_convenience_fee() {
		return get_option( self::CONVENIENCE_FEE_PERCENTAGE, '2.95' );
	}

	public static function register() {
		self::add_payment_processor( __CLASS__, __( 'NMI' , 'sprout-invoices' ) );

		if ( ! self::is_active() ) {
			return;
		}

		add_action( 'init', array( get_class(), 'modify_payment_controls' ) );
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
				'discover',
				'jcb',
				'maestro',
				),
			);
		return $option;
	}

	protected function __construct() {
		parent::__construct();
		self::$api_username = get_option( self::API_USERNAME_OPTION, '' );
		self::$api_password = get_option( self::API_PASSWORD_OPTION, '' );
		self::$currency_code = get_option( self::CURRENCY_CODE_OPTION, 'USD' );
		self::$api_mode = get_option( self::API_MODE_OPTION, self::MODE_TEST );

		if ( is_admin() ) {
			add_action( 'init', array( get_class(), 'register_options' ) );
		}
		// Remove pages
		add_filter( 'si_checkout_pages', array( $this, 'remove_checkout_pages' ) );
	}

	public static function modify_payment_controls() {
		add_action( 'si_head', array( __CLASS__, 'add_style_and_js' ) );

		// modify the checkout page
		add_filter( 'sprout_invoice_template_templates/checkout/credit-card/form.php', array( __CLASS__, 'change_file_for_credit_card' ), 100 );
		add_filter( 'load_view_args_templates/checkout/credit-card/form.php', array( __CLASS__, 'change_cc_file_args' ), 100 );

		// Processing checkout
		add_action( 'si_checkout_action_'.SI_Checkouts::PAYMENT_PAGE, array( __CLASS__, 'process_payment_page_for_ab' ), 20, 1 );
		add_filter( 'si_validate_credit_card_cc', array( __CLASS__, 'maybe_not_check_credit_cards' ), 100, 2 );
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
			'si_nmi_settings' => array(
				'title' => __( 'NMI' , 'sprout-invoices' ),
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
					self::CURRENCY_CODE_OPTION => array(
						'label' => __( 'Currency Code' , 'sprout-invoices' ),
						'option' => array(
							'type' => 'text',
							'default' => self::$currency_code,
							'attributes' => array( 'class' => 'small-text' ),
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
					self::CONVENIENCE_FEE_PERCENTAGE => array(
						'label' => __( 'Service Fee Rate' , 'sprout-invoices' ),
						'option' => array(
							'type' => 'text',
							'default' => get_option( self::CONVENIENCE_FEE_PERCENTAGE, '2.95' ),
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

		$post_data = $this->dp_data( $checkout, $invoice );
		do_action( 'si_log', __CLASS__ . '::' . __FUNCTION__ . ' - NMI post_data', $post_data );

		// convert NVP to a string
		$post_string = '';
		foreach ( $post_data as $key => $value ) {
			$post_string .= "{$key}=".urlencode( $value ).'&';
		}
		$post_string = rtrim( $post_string, '& ' );
		do_action( 'si_log', __CLASS__ . '::' . __FUNCTION__ . ' - post_string', $post_string );

		// Send request
		$response = wp_remote_post( $this->get_api_url(), array(
				'method' => 'POST',
				'body' => $post_string,
				'timeout' => apply_filters( 'http_request_timeout', 30 ),
				'sslverify' => false,
		) );
		do_action( 'si_log', __CLASS__ . '::' . __FUNCTION__ . ' - NMI RAW $response', $response );

		if ( is_wp_error( $response ) ) {
			return false;
		}

		$response = wp_parse_args( wp_remote_retrieve_body( $response ) );
		do_action( 'si_log', __CLASS__ . '::' . __FUNCTION__ . ' - Authorize Net Response', $response );

		$response_code = $response['response']; // The response we want to validate on
		if ( $response_code != 1 ) {
			$this->set_error_messages( $response['responsetext'] );
			return false;
		}

		$is_check_payment = ( 'check' === $post_data['payment'] ) ? true : false ;
		if ( isset( $post_data['_service_fee'] ) && $post_data['_service_fee'] !== 0 ) {
			self::add_service_fee( $invoice, $post_data['_service_fee'] );
		}

		// Success
		$payment_id = SI_Payment::new_payment( array(
			'payment_method' => $this->get_payment_method( $is_check_payment ),
			'invoice' => $invoice->get_id(),
			'amount' => $post_data['amount'],
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
	 * Build the NVP data array for submitting the current checkout to Authorize as an Authorization request
	 */
	private function dp_data( SI_Checkouts $checkout, SI_Invoice $invoice ) {
		$client = $invoice->get_client();

		$user = si_who_is_paying( $invoice );
		// User email or none
		$user_email = ( $user ) ? $user->user_email : '' ;
		$user_id = ( $user ) ? $user->ID : 0 ;

		$DPdata = array();
		$DPdata['username'] = self::$api_username;
		$DPdata['password'] = self::$api_password;

		$DPdata['type'] = 'sale';

		$payment_amount = ( si_has_invoice_deposit( $invoice->get_id() ) ) ? $invoice->get_deposit() : $invoice->get_balance();

		$DPdata['_service_fee'] = 0;

		if ( isset( $_POST['sa_credit_payment_method'] ) && 'bank' !== $_POST['sa_credit_payment_method'] ) {
			$DPdata['payment'] = 'creditcard';
			$DPdata['ccnumber'] = $this->cc_cache['cc_number'];
			$DPdata['ccexp'] = $this->cc_cache['cc_expiration_month'] . '/' . $this->cc_cache['cc_expiration_year'];
			$DPdata['cvv'] = $this->cc_cache['cc_cvv'];
			$DPdata['_service_fee'] = $payment_amount * ( self::get_convenience_fee() / 100 );
		} else {
			$DPdata['payment'] = 'check';
			$DPdata['checkname'] = $checkout->cache['bank_name'];
			$DPdata['checkaba'] = $checkout->cache['bank_routing'];
			$DPdata['checkaccount'] = $checkout->cache['bank_account'];
		}

		$DPdata['firstname'] = $checkout->cache['billing']['first_name'];
		$DPdata['lastname'] = $checkout->cache['billing']['last_name'];
		$DPdata['address1'] = $checkout->cache['billing']['street'];
		$DPdata['city'] = $checkout->cache['billing']['city'];
		$DPdata['state'] = $checkout->cache['billing']['zone'];
		$DPdata['zip'] = $checkout->cache['billing']['postal_code'];
		$DPdata['phone'] = ( ! empty( $checkout->cache['billing']['phone'] ) ) ? $checkout->cache['billing']['phone'] : '1111111111';

		$DPdata['currency'] = self::get_currency_code( $invoice->get_id() );

		$DPdata['email'] = $user_email;
		$DPdata['x_cust_id'] = $user_id;

		$DPdata['orderid'] = $invoice->get_id();

		$DPdata['amount'] = si_get_number_format( $payment_amount + $DPdata['_service_fee'] );

		$DPdata = apply_filters( 'si_nmi_nvp_data', $DPdata );

		return $DPdata;
	}


	//////////////
	// Utility //
	//////////////

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
		if ( $display ) {
			self::set_message( $response, self::MESSAGE_STATUS_ERROR );
		} else {
			do_action( 'si_error', __CLASS__ . '::' . __FUNCTION__ . ' - Auth.net Error Response', $response );
		}
	}




	/////////////////////////////////
	// Profile Options at Checkout //
	/////////////////////////////////

	public static function add_style_and_js() {
		if ( 'estimate' === si_get_doc_context() ) {
			return;
		}
		echo '<link rel="stylesheet" id="si_payments_checkout" href="' . SA_ADDON_NMI_URL . '/resources/front-end/css/si-payments-checkout.css" type="text/css" media="all">';
		echo '<script type="text/javascript" id="si_payments_checkout" src="' . SA_ADDON_NMI_URL . '/resources/front-end/js/si-payments-checkout.js"></script>';
	}

	public static function change_file_for_credit_card( $file = '' ) {
		$file = self::addons_view_path() . 'checkout/credit-card/form.php';
		return $file;
	}

	public static function change_cc_file_args( $args = array() ) {
		$invoice = $args['checkout']->get_invoice();
		$payment_amount = ( si_has_invoice_deposit( $invoice->get_id() ) ) ? $invoice->get_deposit() : $invoice->get_balance();
		$new_args = array(
			'bank_fields' => self::checking_account_fields(),
			'payment_amount' => $payment_amount,
			'convenience_percentage' => self::get_convenience_fee(),
			'convenience_fee' => si_get_number_format( $payment_amount * ( self::get_convenience_fee() / 100 ) ),
			'invoice_id' => $invoice->get_id(),
			 );
		$args = array_merge( $new_args, $args );
		return $args;
	}

	public static function checking_account_fields() {
		$bank_fields = array();

		$bank_fields['bank_name'] = array(
			'type' => 'text',
			'weight' => 4,
			'label' => __( 'Name on Account' , 'sprout-invoices' ),
			'attributes' => array(
				//'autocomplete' => 'off',
			),
			'required' => true,
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
		/*/
		$bank_fields['store_payment_profile'] = array(
			'type' => 'checkbox',
			'weight' => 100,
			'label' => __( 'Save Bank Info' , 'sprout-invoices' ),
			'default' => true,
		);
		/**/
		return $bank_fields;
	}

	public static function load_addon_view( $view, $args, $allow_theme_override = true ) {
		add_filter( 'si_views_path', array( __CLASS__, 'addons_view_path' ) );
		$view = self::load_view( $view, $args, $allow_theme_override );
		remove_filter( 'si_views_path', array( __CLASS__, 'addons_view_path' ) );
		return $view;
	}

	public static function load_addon_view_to_string( $view, $args, $allow_theme_override = true ) {
		ob_start();
		self::load_addon_view( $view, $args, $allow_theme_override );
		return ob_get_clean();
	}

	public static function addons_view_path() {
		return SA_ADDON_NMI_PATH . '/views/';
	}

	//////////////////////
	// Process Checkout //
	//////////////////////

	public static function process_payment_page_for_ab( SI_Checkouts $checkout ) {
		// Banking options
		if ( isset( $_POST['sa_bank_bank_routing'] ) && '' !== $_POST['sa_bank_bank_routing']  ) {
			$checkout->cache['bank_routing'] = $_POST['sa_bank_bank_routing'];
		}

		if ( isset( $_POST['sa_bank_bank_account'] ) && '' !== $_POST['sa_bank_bank_account'] ) {
			$checkout->cache['bank_account'] = $_POST['sa_bank_bank_account'];
		}

		if ( isset( $_POST['sa_bank_bank_name'] ) && '' !== $_POST['sa_bank_bank_name'] ) {
			$checkout->cache['bank_name'] = $_POST['sa_bank_bank_name'];
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

	//////////
	// Fees //
	//////////

	public static function add_service_fee( SI_Invoice $invoice, $fee_total ) {
		$fees = $invoice->get_fees();
		$fees['cc_service_fee'] = array(
				'label' => __( 'Service Fee', 'sprout-invoices' ),
				'always_show' => true,
				'total' => $fee_total,
				'weight' => 30,
			);
		$invoice->set_fees( $fees );
	}
}
SA_NMI::register();
