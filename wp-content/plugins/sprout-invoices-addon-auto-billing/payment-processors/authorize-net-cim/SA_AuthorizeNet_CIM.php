<?php

/**
 * Authorize.net onsite credit card payment processor.
 *
 * @package SI
 * @subpackage Payment SI_Credit_Card_Processors
 */
class SI_AuthorizeNet_CIM extends SI_Credit_Card_Processors {
	const MODE_TEST = 'sandbox';
	const MODE_LIVE = 'live';

	const API_USERNAME_OPTION = 'si_authorize_net_username';
	const API_PASSWORD_OPTION = 'si_authorize_net_password';

	const API_MODE_OPTION = 'si_authorize_net_mode';
	const PAYMENT_METHOD = 'Credit (Authorize.Net CIM)';
	const PAYMENT_SLUG = 'authnet_cim';

	const CONVENIENCE_FEE_PERCENTAGE = 'si_auth_service_fee';

	protected static $instance;
	protected static $cim_request;

	private static $api_mode = self::MODE_TEST;
	private static $api_username = '';
	private static $api_password = '';

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

	public function bank_supported() {
		return true;
	}

	public static function is_active() {
		$enabled = SI_Payment_Processors::enabled_processors();
		return in_array( __CLASS__, $enabled );
	}

	public static function register() {
		self::add_payment_processor( __CLASS__, __( 'Authorize.net CIM' , 'sprout-invoices' ) );
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

	public static function get_convenience_fee() {
		if ( method_exists( 'SI_Service_Fee', 'get_service_fee' )	) {
			$service_fee = SI_Service_Fee::get_service_fee( 'SI_AuthorizeNet_CIM' );
			return $service_fee;
		}
		return get_option( self::CONVENIENCE_FEE_PERCENTAGE, false );
	}

	protected function __construct() {
		parent::__construct();

		// Not set since the init_authrequest needs and does it.
		// self::$api_username = get_option( self::API_USERNAME_OPTION, '' );
		// self::$api_password = get_option( self::API_PASSWORD_OPTION, '' );

		self::$api_mode = get_option( self::API_MODE_OPTION, self::MODE_TEST );

		if ( is_admin() ) {
			add_action( 'init', array( __CLASS__, 'register_options' ) );
		}

		// Remove review pages
		add_filter( 'si_checkout_pages', array( $this, 'remove_review_checkout_page' ) );

	}

	/**
	 * Load up the library and instantiate into a static object
	 *
	 * @return OBJECT
	 */
	public static function init_authrequest() {
		if ( ! ( isset( self::$cim_request ) && is_a( self::$cim_request, 'AuthorizeNetCIM' ) ) ) {
			if ( ! class_exists( 'AuthorizeNetCIM' ) ) {
				// set in case the class is static.
				self::$api_username = get_option( self::API_USERNAME_OPTION, '' );
				self::$api_password = get_option( self::API_PASSWORD_OPTION, '' );
				self::$api_mode = get_option( self::API_MODE_OPTION, self::MODE_TEST );

				define( 'AUTHORIZENET_API_LOGIN_ID', self::$api_username );
				define( 'AUTHORIZENET_TRANSACTION_KEY', self::$api_password );
				require_once 'sdk/autoload.php';
			} else {
				do_action( 'si_error', __CLASS__ . '::' . __FUNCTION__ . ' - Authorize Net SDK Loaded from another library', time() );
			}
			self::$cim_request = new AuthorizeNetCIM;
			self::$cim_request->setSandbox( false );
			if ( self::MODE_TEST === self::$api_mode ) {
				self::$cim_request->setSandbox( true );
			}
		}
		return self::$cim_request;

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
	 * Hooked on init add the settings page and options.
	 *
	 */
	public static function register_options() {
		// Settings
		$settings = array(
			'si_authorizenet_cim_settings' => array(
				'title' => __( 'Authorize.net CIM' , 'sprout-invoices' ),
				'weight' => 210,
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

		self::init_authrequest();

		// Create Profile
		$profile_id = $this->maybe_create_profile_from_invoice( $invoice );
		if ( ! $profile_id ) {
			do_action( 'si_log', __CLASS__ . '::' . __FUNCTION__ . ' - could not create profile id: ', $profile_id );
			return false;
		}

		$payment_profile_id = false;
		$new_profile_created = false;
		// If no CC was submitted determine what the payment profile id was selected, if any.
		if ( ! $payment_profile_id ) {
			// Check if the payment profile id was passed
			if ( isset( $_POST['sa_credit_payment_method'] ) ) {
				$payment_profile_id = $_POST['sa_credit_payment_method'];
			} // If the payment profile id wasn't passed check the checkout cache for the cim profile id
			elseif ( isset( $checkout->cache['ab_payment_profile'] ) ) {
				$payment_profile_id = $checkout->cache['ab_payment_profile'];
			}
		}

		do_action( 'si_log', __CLASS__ . '::' . __FUNCTION__ . ' - payment_profile_id: ', $payment_profile_id );

		// No payment profile id given
		if ( ! is_numeric( $payment_profile_id ) ) {
			$payment_profile_id = $this->add_payment_profile( $profile_id, $checkout, $invoice );
			$new_profile_created = true;
			do_action( 'si_log', __CLASS__ . '::' . __FUNCTION__ . ' - adding payment profile: ', $payment_profile_id );
		}

		if ( ! $payment_profile_id ) {
			self::set_error_messages( 'Payment Error: 3742' );
			return false;
		}

		$transaction_id = 0; // If not reset than a PriorCapture
		$response_array = array();

		// Create AUTHORIZATION/CAPTURE Transaction
		$transaction_response = self::create_transaction( $profile_id, $payment_profile_id, $invoice );

		if ( ! is_object( $transaction_response ) ) {
			self::set_error_messages( $transaction_response );
			return false;
		}

		do_action( 'si_log', __CLASS__ . '::' . __FUNCTION__ . ' - create response: ', $transaction_response );

		if ( 1 !== (int) $transaction_response->response_reason_code ) {
			self::set_error_messages( $transaction_response->response_reason_text );
			return false;
		}

		$transaction_id = $transaction_response->transaction_id;

		// remove the payment profile if store cc is unchecked
		if ( $new_profile_created ) {
			if ( ! isset( $_POST['sa_credit_store_payment_profile'] ) && ! isset( $checkout->cache['sa_credit_store_payment_profile'] ) ) {
				Sprout_Billings_Profiles::remove_payment_profile( $payment_profile_id, $invoice->get_id() );
				do_action( 'si_log', __CLASS__ . '::' . __FUNCTION__ . ' - delete profile response: ', $payment_profile_id );
			}
		}
		// convert the transaction_response object to an array for the payment record
		$transaction_json = wp_json_encode( $transaction_response );
		$transaction = json_decode( $transaction_json, true );

		$payment_id = SI_Payment::new_payment( array(
			'payment_method' => $this->get_payment_method(),
			'invoice' => $invoice->get_id(),
			'amount' => $transaction['amount'],
			'data' => array(
			'invoice_id' => $invoice->get_id(),
			'transaction_id' => $transaction_id,
			'profile_id' => $profile_id,
			'payment_profile_id' => $payment_profile_id,
			'live' => ( self::$api_mode == self::MODE_LIVE ),
			'api_response' => $transaction,
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


	public static function create_transaction( $profile_id, $payment_profile_id, SI_Invoice $invoice, $set_error_message = true ) {

		self::init_authrequest();

		// Vars
		$client = $invoice->get_client();

		$user = si_who_is_paying( $invoice );
		// User email or none
		$user_email = ( $user ) ? $user->user_email : '' ;
		$user_id = ( $user ) ? $user->ID : 0 ;

		// Charge
		$amount = ( si_has_invoice_deposit( $invoice->get_id() ) ) ? $invoice->get_deposit() : $invoice->get_balance();
		if ( isset( $_POST['si_payment_amount_change'] ) && is_numeric( $_POST['si_payment_amount_option'] ) ) {
			if ( $_POST['si_payment_amount_option'] < $amount ) {
					$amount = $_POST['si_payment_amount_option'];
			}
		}

		$_service_fee = self::get_convenience_fee();
		if ( is_numeric( $_service_fee ) && 0.00 < $_service_fee ) {
			$service_fee = $amount * ( $_service_fee / 100 );
			$amount = si_get_number_format( $amount + $service_fee );
		}

		// Create Auth & Capture Transaction
		$transaction = new AuthorizeNetTransaction;
		$transaction->amount = si_get_number_format( $amount );
		$shipping_total = 0;
		if ( $shipping_total > 0.01 ) {
			$transaction->shipping->amount = $shipping_total;
		}
		$transaction->customerProfileId = $profile_id;
		$transaction->customerPaymentProfileId = $payment_profile_id;
		// $transaction->customerShippingAddressId = $customer_address_id;
		$transaction->order->invoiceNumber = (int) $invoice->get_id();

		$response = self::$cim_request->createCustomerProfileTransaction( 'AuthCapture', $transaction );

		do_action( 'si_log', __CLASS__ . '::' . __FUNCTION__ . ' - createCustomerProfileTransaction response: ', $response );

		// Error check
		if ( 'Error' == $response->xpath_xml->messages->resultCode ) {
			if ( $set_error_message ) {
				self::set_error_messages( (string) $response->xpath_xml->messages->message->text );
				return false;
			}
			return (string) $response->xpath_xml->messages->message->text;
		}

		$transaction_response = $response->getTransactionResponse();
		// $transaction_id = $transaction_response->transaction_id;

		return $transaction_response;
	}

	//////////////////////
	// Payment Profile //
	//////////////////////

	/**
	 * Create the Profile within CIM if one doesn't exist.
	 *
	 */
	public function add_payment_profile( $profile_id, SI_Checkouts $checkout, SI_Invoice $invoice ) {
		// Create new customer profile
		$paymentProfile = new AuthorizeNetPaymentProfile;
		$paymentProfile->customerType = 'individual';
		$paymentProfile->billTo->firstName = $checkout->cache['billing']['first_name'];
		$paymentProfile->billTo->lastName = $checkout->cache['billing']['last_name'];
		$paymentProfile->billTo->address = $checkout->cache['billing']['street'];
		$paymentProfile->billTo->city = $checkout->cache['billing']['city'];
		$paymentProfile->billTo->state = $checkout->cache['billing']['zone'];
		$paymentProfile->billTo->zip = $checkout->cache['billing']['postal_code'];
		$paymentProfile->billTo->country = $checkout->cache['billing']['country'];
		$paymentProfile->billTo->phoneNumber = '';
		// $paymentProfile->billTo->customerAddressId = $customer_address_id;

		if ( isset( $checkout->cache['bank_routing'] ) ) {
			// bank info
			$paymentProfile->payment->bankAccount->accountType = 'businessChecking';
			$paymentProfile->payment->bankAccount->routingNumber = $checkout->cache['bank_routing'];
			$paymentProfile->payment->bankAccount->accountNumber = $checkout->cache['bank_account'];
			$paymentProfile->payment->bankAccount->nameOnAccount = substr( $checkout->cache['billing']['first_name'] . ' ' . $checkout->cache['billing']['last_name'], 0, 22 );
			//$paymentProfile->payment->bankAccount->echeckType = 'WEB';
			//$paymentProfile->payment->bankAccount->bankName = 'Unknown';
		} else {
			// CC info
			$paymentProfile->payment->creditCard->cardNumber = $this->cc_cache['cc_number'];
			$paymentProfile->payment->creditCard->expirationDate = $this->cc_cache['cc_expiration_year'] . '-' . sprintf( '%02s', $this->cc_cache['cc_expiration_month'] );
			$paymentProfile->payment->creditCard->cardCode = $this->cc_cache['cc_cvv'];
		}
		do_action( 'si_log', __CLASS__ . '::' . __FUNCTION__ . ' - paymentProfile:', $paymentProfile );

		// Create
		$create_profile_response = self::$cim_request->createCustomerPaymentProfile( $profile_id, $paymentProfile );
		if ( ! $create_profile_response->isOk() ) {
			// In case no validation response is given but there's an error.
			if ( isset( $create_profile_response->xml->messages->message->text ) ) {
				self::set_error_messages( (string) $create_profile_response->xml->messages->message->text );
				return false;
			}
		}
		// Get profile id
		$payment_profile_id = $create_profile_response->getPaymentProfileId();

		do_action( 'si_log', __CLASS__ . '::' . __FUNCTION__ . ' - createCustomerPaymentProfile create_profile_response:', $create_profile_response );
		do_action( 'si_log', __CLASS__ . '::' . __FUNCTION__ . ' - payment_profile_id', $payment_profile_id );

		// Save the profile, even if it may be removed later
		Sprout_Billings_Profiles::save_payment_profile( $payment_profile_id, $invoice->get_id() );

		return $payment_profile_id;
	}


	/**
	 * Create the Profile within CIM if one doesn't exist.
	 *
	 */
	public static function create_payment_profile( $profile_id, $client_id = 0, $payment_info ) {
		self::init_authrequest();

		if ( ! $profile_id ) {
			$client = SI_Client::get_instance( $client_id );
			if ( ! is_a( $client, 'SI_Client' ) ) {
				return __( 'Client not available for payment profile creation.' , 'sprout-invoices' );
			}
			$profile_id = self::create_profile( $client_id );
		}

		// Create new customer profile
		$paymentProfile = new AuthorizeNetPaymentProfile;
		$paymentProfile->customerType = 'individual';
		$paymentProfile->billTo->firstName = $payment_info['billing']['first_name'];
		$paymentProfile->billTo->lastName = $payment_info['billing']['last_name'];
		$paymentProfile->billTo->address = $payment_info['billing']['street'];
		$paymentProfile->billTo->city = $payment_info['billing']['city'];
		$paymentProfile->billTo->state = $payment_info['billing']['zone'];
		$paymentProfile->billTo->zip = $payment_info['billing']['postal_code'];
		$paymentProfile->billTo->country = $payment_info['billing']['country'];
		$paymentProfile->billTo->phoneNumber = '';
		// $paymentProfile->billTo->customerAddressId = $customer_address_id;

		if ( isset( $payment_info['bank_routing'] ) ) {
			// bank info
			$paymentProfile->payment->bankAccount->accountType = 'businessChecking';
			$paymentProfile->payment->bankAccount->routingNumber = $payment_info['bank_routing'];
			$paymentProfile->payment->bankAccount->accountNumber = $payment_info['bank_account'];
			$paymentProfile->payment->bankAccount->nameOnAccount = $payment_info['billing']['first_name'] . ' ' . $payment_info['billing']['last_name'];
			//$paymentProfile->payment->bankAccount->echeckType = 'WEB';
			//$paymentProfile->payment->bankAccount->bankName = 'Unknown';
		} else {
			// CC info
			$paymentProfile->payment->creditCard->cardNumber = $payment_info['cc_number'];
			$paymentProfile->payment->creditCard->expirationDate = $payment_info['cc_expiration_year'] . '-' . sprintf( '%02s', $payment_info['cc_expiration_month'] );
			$paymentProfile->payment->creditCard->cardCode = $payment_info['cc_cvv'];
		}
		do_action( 'si_log', __CLASS__ . '::' . __FUNCTION__ . ' - paymentProfile:', $paymentProfile );

		// Create
		$create_profile_response = self::$cim_request->createCustomerPaymentProfile( $profile_id, $paymentProfile );

		do_action( 'si_log', __CLASS__ . '::' . __FUNCTION__ . ' - create_profile_response:', $create_profile_response );

		if ( ! $create_profile_response->isOk() ) {
			// In case no validation response is given but there's an error.
			if ( isset( $create_profile_response->xml->messages->message->text ) ) {
				return array(
						'error' => true,
						'message' => $create_profile_response->xml->messages->message->text,
					);
			}
		}
		// Get profile id
		$payment_profile_id = $create_profile_response->getPaymentProfileId();

		do_action( 'si_log', __CLASS__ . '::' . __FUNCTION__ . ' - createCustomerPaymentProfile create_profile_response:', $create_profile_response );
		do_action( 'si_log', __CLASS__ . '::' . __FUNCTION__ . ' - payment_profile_id', $payment_profile_id );

		Sprout_Billings_Profiles::save_payment_profile( $payment_profile_id, $client_id );

		return $payment_profile_id;
	}

	/**
	 * Build an array of card from the profile
	 *
	 * @param integer $profile_id CIM profile ID
	 * @return array
	 */
	public static function get_payment_profiles( $profile_id = 0 ) {
		// Get profile object
		$customer_profile = self::get_customer_profile( $profile_id );
		if ( ! $customer_profile ) {
			return false;
		}
		// Create an array of payment profile card numbers
		$cards = array();
		if ( isset( $customer_profile->xpath_xml->profile->paymentProfiles ) ) {
			if ( ! empty( $customer_profile->xpath_xml->profile->paymentProfiles[0] ) ) {
				foreach ( $customer_profile->xpath_xml->profile->paymentProfiles as $key => $profile ) {
					$name = ( isset( $profile->payment->creditCard->cardNumber ) ) ? __( 'Credit Card' , 'sprout-invoices' ) . ': ' . $profile->payment->creditCard->cardNumber : __( 'Checking' , 'sprout-invoices' ) . ': ' . $profile->payment->bankAccount->accountNumber ;
					$cards[ (int) $profile->customerPaymentProfileId ] = (string) $name;
				}
			} else {
				$name = ( isset( $profile->payment->creditCard->cardNumber ) ) ? __( 'Credit Card' , 'sprout-invoices' ) . ': ' . $profile->payment->creditCard->cardNumber : __( 'Checking' , 'sprout-invoices' ) . ': ' . $profile->payment->bankAccount->accountNumber ;
				$cards[ (int) $customer_profile->xpath_xml->profile->paymentProfiles->customerPaymentProfileId ] = (string) $name;
			}
		}
		return $cards;
	}

	////////////////////////
	// Profile Managment //
	////////////////////////

	public function maybe_create_profile_from_invoice( SI_Invoice $invoice ) {

		$profile_id = Sprout_Billings_Profiles::get_customer_profile_id( $invoice->get_id(), true );
		if ( $profile_id ) {
			do_action( 'si_log', __CLASS__ . '::' . __FUNCTION__ . ' - create_profile return saved id: ', $profile_id );
			return $profile_id;
		}

		$client_id = SI_Sprout_Billings::get_client_id( $invoice->get_id() );
		$client = SI_Client::get_instance( $client_id );

		if ( ! is_a( $client, 'SI_Client' ) ) {
			do_action( 'si_error', __CLASS__ . '::' . __FUNCTION__ . ' - create_profile not a valid client_id: ', $client_id );
			return false;
		}

		return self::create_profile( $client_id );

	}

	public static function create_profile( $client_id, $profile_atts = array() ) {

		$client = SI_Client::get_instance( $client_id );
		$client_users = $client->get_associated_users();
		$client_user_id = array_shift( $client_users );
		$user = get_userdata( $client_user_id );

		$profile_atts = wp_parse_args( $profile_atts, array(
			'merchantCustomerId' => $client_id,
			'description' => $client->get_title(),
			'email' => $user->user_email,
		) );
		$profile_atts = apply_filters( 'si_authorize_net_cim_create_profile_atts', $profile_atts, $client_id );

		self::init_authrequest();

		// Create new customer profile
		$customerProfile = new AuthorizeNetCustomer;
		$customerProfile->description = $profile_atts['description'];
		$customerProfile->merchantCustomerId = $profile_atts['merchantCustomerId'];
		$customerProfile->email = $profile_atts['email'];

		// Request and response
		$response = self::$cim_request->createCustomerProfile( $customerProfile );

		do_action( 'si_log', __CLASS__ . '::' . __FUNCTION__ . ' - create customer profile response: ', $response );

		// Error check
		if ( ! $response->isOk() ) {
			$error_message = $response->getMessageText();
			// If the ID already exists tie it to the current user, hopefully the CIM profile is based on more than just email.
			if ( strpos( $error_message, 'duplicate record with ID' ) ) {
				preg_match( '~ID\s+(\S+)~', $error_message, $matches );
				$new_customer_id = (int) $matches[1];
				if ( '' === $new_customer_id ) {
					self::set_error_messages( sa__( 'A duplicate profile was found. Please contact the site administrator.' ) );
					return false;
				}
			} else {
				self::set_error_messages( $error_message );
				return false;
			}
		} else { // New customer profile was created.
			$new_customer_id = $response->getCustomerProfileId();
		}
		// Save profile id with the user for future reference.
		Sprout_Billings_Profiles::update_client_payment_profile_id( $client_id, $new_customer_id );

		do_action( 'si_log', __CLASS__ . '::' . __FUNCTION__ . ' - create_profile return:  ', $new_customer_id );

		// Return
		return $new_customer_id;

	}

	// callbacks from SI_Auto_Payment_Processors

	/**
	 * Get the profile id of a user.
	 *
	 * @param int     $profile_id Profile id stored in user meta
	 * @return object
	 */
	public static function get_customer_profile( $profile_id = 0, $invoice_id = 0 ) {
		if ( ! $profile_id && $invoice_id ) {
			$profile_id = Sprout_Billings_Profiles::get_customer_profile_id( $invoice_id );
			if ( ! $profile_id ) {
				return false;
			}
		}
		self::init_authrequest();
		$customer_profile = self::$cim_request->getCustomerProfile( $profile_id );
		return $customer_profile;
	}

	public static function validate_profile_id( $profile_id = 0 ) {
		if ( $profile_id ) {
			$customer_profile = self::get_customer_profile( $profile_id );
			// If the profile exists than return it's id
			if ( ! $customer_profile->isError() || $customer_profile->getMessageCode() != 'E00040' ) {
				return $profile_id;
			}
			// profile validation produced an error, remove it from this user and continue creating a new one.
			$profile_id = 0;
			Sprout_Billings_Profiles::destroy_profile( $client_id );

			do_action( 'si_log', __CLASS__ . '::' . __FUNCTION__ . ' - get customer profile from create_profile resulted in an error:  ', $response );
		}

		return $profile_id;
	}

	///////////////////////////////////////
	// Local payment profile management //
	///////////////////////////////////////

	public static function remove_payment_profile( $profile_id, $invoice_id = 0 ) {
		self::init_authrequest();
		$customer_profile = Sprout_Billings_Profiles::get_customer_profile_id( $invoice_id );
		$response = self::$cim_request->deleteCustomerPaymentProfile( $customer_profile, $profile_id );
		return $response;
	}

	///////////////
	// Auto Bill //
	///////////////

	public static function manual_payment_attempt( $invoice_id, $payment_profile_id ) {
		$profile_id = Sprout_Billings_Profiles::get_customer_profile_id( $invoice_id );
		if ( ! $profile_id ) {
			return false;
		}
		$invoice = SI_Invoice::get_instance( $invoice_id );
		$transaction_response = self::create_transaction( $profile_id, $payment_profile_id, $invoice, false );

		if ( ! is_object( $transaction_response ) ) {
			return $transaction_response;
		}

		do_action( 'si_log', __CLASS__ . '::' . __FUNCTION__ . ' - create response: ', $transaction_response );

		if ( 1 !== (int) $transaction_response->response_reason_code ) {
			return $transaction_response->response_reason_text;
		}

		// convert the transaction_response object to an array for the payment record
		$transaction_json = wp_json_encode( $transaction_response );
		$transaction = json_decode( $transaction_json, true );

		$payment_id = SI_Payment::new_payment( array(
			'payment_method' => self::PAYMENT_METHOD,
			'invoice' => $invoice_id,
			'amount' => $transaction['amount'],
			'data' => array(
			'invoice_id' => $invoice->get_id(),
			'transaction_id' => $transaction['transaction_id'],
			'profile_id' => $profile_id,
			'payment_profile_id' => $payment_profile_id,
			'live' => ( self::$api_mode == self::MODE_LIVE ),
			'api_response' => $transaction,
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
	private static function set_error_messages( $response, $display = true ) {
		if ( $display ) {
			self::set_message( (string) $response, self::MESSAGE_STATUS_ERROR );
		} else {
			do_action( 'si_error', __CLASS__ . '::' . __FUNCTION__ . ' - Auth.net Error Response', $response );
		}
	}
}
SI_AuthorizeNet_CIM::register();
