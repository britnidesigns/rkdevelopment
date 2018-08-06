<?php

/**
 * NMI onsite credit card payment processor.
 *
 * @package SI
 * @subpackage Payment SI_Credit_Card_Processors
 */
class SA_NMI_Tokenized extends SI_Credit_Card_Processors {
	const API_ENDPOINT = 'https://secure.nationalprocessinggateway.com/api/transact.php';
	const MODE_TEST = 'sandbox';
	const MODE_LIVE = 'live';

	const API_USERNAME_OPTION = 'si_nmi_username';
	const API_PASSWORD_OPTION = 'si_nmi_password';
	const API_MODE_OPTION = 'si_nmi_mode';
	const CURRENCY_CODE_OPTION = 'si_nmi_currency';

	const PAYMENT_METHOD = 'Credit (NMI)';
	const PAYMENT_SLUG = 'nmmi';

	const NMI_CUSTOMER_KEY_VAULT_META = 'si_nmi_customer_id_v1';

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

	public function bank_supported() {
		return true;
	}

	public static function get_convenience_fee() {
		if ( method_exists( 'SI_Service_Fee', 'get_service_fee' )	) {
			$service_fee = SI_Service_Fee::get_service_fee( 'SA_NMI_Tokenized' );
			return $service_fee;
		}
		return get_option( self::CONVENIENCE_FEE_PERCENTAGE, '2.95' );
	}

	public static function is_active() {
		$enabled = SI_Payment_Processors::enabled_processors();
		return in_array( __CLASS__, $enabled );
	}

	public static function register() {
		self::add_payment_processor( __CLASS__, __( 'NMI BANK' , 'sprout-invoices' ) );

		if ( ! self::is_active() ) {
			return;
		}

		add_action( 'init', array( get_class(), 'modify_payment_controls' ), 1000 );
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

		// Remove pages
		add_filter( 'si_checkout_pages', array( $this, 'remove_checkout_pages' ) );
	}

	public static function modify_payment_controls() {
		add_action( 'si_head', array( __CLASS__, 'add_style_and_js' ) );

		// modify the checkout page
		add_filter( 'sprout_invoice_template_templates/checkout/credit-card/form.php', array( __CLASS__, 'change_file_for_credit_card' ), 100 );
		add_filter( 'load_view_args_templates/checkout/credit-card/form.php', array( __CLASS__, 'change_cc_file_args' ), 1000 );

		// mod for NMI customizations
		remove_filter( 'sa_credit_fields', array( 'SI_Sprout_Billings_Checkout', 'add_card_selection' ), 100, 2 );
		remove_action( 'si_credit_card_payment_fields', array( 'SI_Sprout_Billings_Checkout', 'add_checking_info' ) );

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
	public static function register_settings( $settings = array() ) {
		// Settings
		$settings['payments'] = array(
			'si_nmi_bank_settings' => array(
				'title' => __( 'NMI Bank' , 'sprout-invoices' ),
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
						'label' => __( 'Credit Service Rate' , 'sprout-invoices' ),
						'option' => array(
							'type' => 'text',
							'default' => get_option( self::CONVENIENCE_FEE_PERCENTAGE, '2.95' ),
							'description' => __( 'Percentage applied to the total if a credit card is used.' , 'sprout-invoices' ),
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
		$client = $invoice->get_client();

		if ( ! is_a( $client, 'SI_Client' ) ) {
			self::set_error_messages( 'A client must be assigned for this payment option to work.' );
			return false;
		}

		// Create AUTHORIZATION/CAPTURE Transaction
		$post_data = $this->post_data_vault_payment( $checkout, $invoice );
		do_action( 'si_log', __CLASS__ . '::' . __FUNCTION__ . ' - NMI post_data', $post_data );

		$transaction_response = self::api_transaction( $post_data );
		if ( ! $transaction_response ) {
			return false;
		}

		// Service Fee
		$is_check_payment = ( isset( $post_data['_service_fee'] ) && $post_data['_service_fee'] > 0.00 ) ? true : false ;
		if ( isset( $post_data['_service_fee'] ) && $post_data['_service_fee'] !== 0 ) {
			self::add_service_fee( $invoice, $post_data['_service_fee'] );
		}

		// update the payment profile
		if ( isset( $post_data['customer_vault_id'] ) ) {
			Sprout_Billings_Profiles::update_client_payment_profile_id( $client->get_id(), $post_data['customer_vault_id'] );
		}

		// update the payment profile
		if ( isset( $post_data['_profile'] ) ) {
			self::update_customer_profile_meta( $client->get_id(), $post_data['_profile'] );
		}

		// Success
		$payment_id = SI_Payment::new_payment( array(
			'payment_method' => $this->get_payment_method( $is_check_payment ),
			'invoice' => $invoice->get_id(),
			'amount' => $post_data['amount'],
			'data' => array(
			'invoice_id' => $invoice->get_id(),
			'transaction_id' => $transaction_response['transactionid'],
			'profile_id' => $transaction_response['customer_vault_id'],
			'payment_profile_id' => $transaction_response['customer_vault_id'],
			'live' => ( self::$api_mode == self::MODE_LIVE ),
			'api_response' => $transaction_response,
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
	private function post_data_vault_payment( SI_Checkouts $checkout, SI_Invoice $invoice ) {
		$client = $invoice->get_client();

		$user = si_who_is_paying( $invoice );
		// User email or none
		$user_email = ( $user ) ? $user->user_email : '' ;
		$user_id = ( $user ) ? $user->ID : 0 ;

		$DPdata = array();
		$DPdata['username'] = self::$api_username;
		$DPdata['password'] = self::$api_password;
		$DPdata['type'] = 'sale';

		$vault_id = self::get_vault_id_by_invoice( $invoice );
		$payment_profile = self::get_payment_profiles( $vault_id );

		$selected_stored_payment_method = ( isset( $checkout->cache['vault_payment'] ) ) ? true : false ;
		$has_stored_payment_profile = ( ! $payment_profile ) ? false : true ;

		$payment_amount = ( si_has_invoice_deposit( $invoice->get_id() ) ) ? $invoice->get_deposit() : $invoice->get_balance();
		if ( isset( $_POST['si_payment_amount_change'] ) && is_numeric( $_POST['si_payment_amount_option'] ) ) {
			if ( $_POST['si_payment_amount_option'] < $payment_amount ) {
					$payment_amount = $_POST['si_payment_amount_option'];
			}
		}

		$DPdata['_service_fee'] = 0;

		// If stored payment selected than it's basic
		if ( $selected_stored_payment_method ) {
			// if a CC than add a fee
			if ( strpos( $payment_profile[ $vault_id ], 'Credit' ) !== false ) {
				$DPdata['_service_fee'] = $payment_amount * ( self::get_convenience_fee() / 100 );
			}
			$DPdata['amount'] = si_get_number_format( $payment_amount + $DPdata['_service_fee'] );
			$DPdata['customer_vault_id'] = $vault_id;
			$DPdata = apply_filters( 'si_nmi_nvp_data_vaulted', $DPdata );
			return $DPdata;
		}

		// if vault exists but stored payment not selected
		$DPdata['customer_vault'] = ( $vault_id ) ? 'update_customer' : 'add_customer';

		// reset the id since it needs to be created
		$vault_id = ( is_numeric( $vault_id ) ) ? $vault_id : time().$client->get_id();
		$DPdata['customer_vault_id'] = $vault_id;

		// for the payment profile.
		$DPdata['_profile'] = array();

		if ( isset( $_POST['sa_credit_payment_method'] ) && 'bank' !== $_POST['sa_credit_payment_method'] ) {
			$DPdata['payment'] = 'creditcard';
			$DPdata['ccnumber'] = $this->cc_cache['cc_number'];
			$DPdata['ccexp'] = str_pad( $this->cc_cache['cc_expiration_month'], 2, '0', STR_PAD_LEFT ) . '/' . substr( $this->cc_cache['cc_expiration_year'], -2 );
			$DPdata['cvv'] = $this->cc_cache['cc_cvv'];
			$DPdata['_service_fee'] = $payment_amount * ( self::get_convenience_fee() / 100 );

			$DPdata['_profile'][ $vault_id ] = sprintf( __( 'Credit: %s', 'sprout-invoices' ), SI_Credit_Card_Processors::mask_card_number( $this->cc_cache['cc_number'] ) );

		} else {
			$DPdata['payment'] = 'check';
			$DPdata['checkname'] = $checkout->cache['bank_name'];
			$DPdata['checkaba'] = $checkout->cache['bank_routing'];
			$DPdata['checkaccount'] = $checkout->cache['bank_account'];
			$DPdata['account_holder_type'] = 'personal';
			$DPdata['account_type'] = 'checking';

			$DPdata['_profile'][ $vault_id ] = sprintf( __( 'Bank: %s', 'sprout-invoices' ), SI_Credit_Card_Processors::mask_card_number( $checkout->cache['bank_account'] ) );
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



	/////////////////////////////////
	// Profile Options at Checkout //
	/////////////////////////////////

	public static function add_style_and_js() {
		if ( 'estimate' === si_get_doc_context() ) {
			return;
		}
		echo '<link rel="stylesheet" id="si_payments_checkout" href="' . SA_ADDON_AUTO_BILLING_URL . '/payment-processors/nmi/resources/front-end/css/si-payments-checkout.css" type="text/css" media="all">';
		echo '<script type="text/javascript" id="si_payments_checkout" src="' . SA_ADDON_AUTO_BILLING_URL . '/payment-processors/nmi/resources/front-end/js/si-payments-checkout.js"></script>';
	}

	public static function change_file_for_credit_card( $file = '' ) {
		$file = SA_ADDON_AUTO_BILLING_PATH . '/payment-processors/nmi/views/checkout/credit-card/form.php';
		return $file;
	}

	public static function change_cc_file_args( $args = array() ) {
		$invoice = $args['checkout']->get_invoice();
		$payment_amount = ( si_has_invoice_deposit( $invoice->get_id() ) ) ? $invoice->get_deposit() : $invoice->get_balance();

		$client = $invoice->get_client();
		$vault_id = Sprout_Billings_Profiles::get_customer_profile_id( $invoice->get_id(), false );
		$cards = self::get_payment_profiles( $vault_id );

		$deposit = $invoice->get_deposit();
		if ( 0.01 > (float) $deposit ) {
			$deposit = 1.00;
		}

		$new_args = array(
			'deposit' => $deposit,
			'cards' => $cards,
			'bank_fields' => self::bank_fields(),
			'payment_amount' => $payment_amount,
			'convenience_percentage' => self::get_convenience_fee(),
			'convenience_fee' => si_get_number_format( $payment_amount * ( self::get_convenience_fee() / 100 ) ),
			'invoice_id' => $invoice->get_id(),
			 );

		$args = array_merge( $new_args, $args );
		return $args;
	}

	public static function bank_fields() {
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

		if ( isset( $_POST['sa_credit_payment_method'] ) && 'vault' === $_POST['sa_credit_payment_method'] ) {
			$checkout->cache['vault_payment'] = true;
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

		// vault
		if ( isset( $_POST['sa_credit_payment_method'] ) && 'vault' === $_POST['sa_credit_payment_method'] ) {
			self::clear_messages();
			return true;
		}
		return $valid;
	}

	///////////////////////////////////////////////
	// callbacks from SI_Auto_Payment_Processors //
	///////////////////////////////////////////////


	/**
	 * Build an array of card from the profile
	 *
	 * @param integer $client_id CIM profile ID
	 * @return array
	 */
	public static function get_payment_profiles( $profile_id = 0 ) {
		if ( ! $profile_id ) {
			return false;
		}
		$client_id = Sprout_Billings_Profiles::get_client_id_by_profile_id( $profile_id );
		// Get profile object
		$customer_profile = self::get_customer_profile_meta( $client_id );
		if ( ! is_array( $customer_profile ) ) {
			return false;
		}

		return $customer_profile;
	}

	public static function validate_client_id( $client_id = 0 ) {
		return $client_id;
	}

	/**
	 * Get the profile id of a user.
	 *
	 * @param int     $client_id Profile id stored in user meta
	 * @return object
	 */
	public static function get_customer_profile_meta( $client_id = 0, $invoice_id = 0 ) {

		$customer_profile = get_post_meta( $client_id, self::NMI_CUSTOMER_KEY_VAULT_META, true );
		return $customer_profile;
	}

	public static function update_customer_profile_meta( $client_id = 0, $profile = array() ) {
		$customer_profile = update_post_meta( $client_id, self::NMI_CUSTOMER_KEY_VAULT_META, $profile );

		// clear hidden profiles
		update_post_meta( $client_id, Sprout_Billings_Profiles::CLIENT_HIDDEN_CARDS_PAYMENT_PROFILES, array() );

		return $customer_profile;
	}



	public static function get_vault_id_by_invoice( $invoice ) {
		$update_payment_profile_id = false;
		$customer_id = Sprout_Billings_Profiles::get_customer_profile_id( $invoice->get_id(), false );
		return $customer_id;
	}

	public static function delete_vault_id_by_invoice( $invoice ) {
		$client_id = SI_Sprout_Billings::get_client_id( $invoice->get_id() );
		$customer_id = Sprout_Billings_Profiles::delete_profile( $client_id );
		return $customer_id;

	}

	public static function create_payment_profile( $vault_id = 0, $client_id = 0, $payment_info ) {

		$customer_vault = 'update_customer';

		// cusotmer needs to be added.
		if ( ! $vault_id ) {
			$client = SI_Client::get_instance( $client_id );
			if ( ! is_a( $client, 'SI_Client' ) ) {
				return __( 'Client not available for payment profile creation.' , 'sprout-invoices' );
			}
			$vault_id = Sprout_Billings_Profiles::get_client_payment_profile_id( $client_id );
			if ( ! $vault_id ) {
				// adding a customer at this point
				$customer_vault = 'add_customer';
				// create a unique id
				$vault_id = time().$client->get_id();
				// update the client record
				Sprout_Billings_Profiles::update_client_payment_profile_id( $client->get_id(), $vault_id );
			}
		}
		$post_data = array(
				'username' => self::$api_username,
				'password' => self::$api_password,
				'customer_vault' => $customer_vault,
				'customer_vault_id' => $vault_id,
				'ccnumber' => $payment_info['cc_number'],
				'ccexp' => str_pad( $payment_info['cc_expiration_month'], 2, '0', STR_PAD_LEFT ) . '/' . substr( $payment_info['cc_expiration_year'], -2 ),
			);

		$transaction_response = self::api_transaction( $post_data );

		if ( ! $transaction_response ) {
			return false;
		}

		// for the payment profile.
		$profile = array();
		$profile[ $vault_id ] = sprintf( __( 'Credit: %s', 'sprout-invoices' ), SI_Credit_Card_Processors::mask_card_number( $payment_info['cc_number'] ) );

		self::update_customer_profile_meta( $client_id, $profile );
		return $vault_id;
	}


	///////////////
	// Auto Bill //
	///////////////

	public static function manual_payment_attempt( $invoice_id ) {
		$profile_id = Sprout_Billings_Profiles::get_customer_profile_id( $invoice_id );
		if ( ! $profile_id ) {
			return false;
		}

		$invoice = SI_Invoice::get_instance( $invoice_id );
		$client = $invoice->get_client();

		$user = si_who_is_paying( $invoice );
		// User email or none
		$user_email = ( $user ) ? $user->user_email : '' ;
		$user_id = ( $user ) ? $user->ID : 0 ;

		$vault_id = self::get_vault_id_by_invoice( $invoice );
		$payment_profile = self::get_payment_profiles( $vault_id );

		$DPdata = array();
		$DPdata['username'] = self::$api_username;
		$DPdata['password'] = self::$api_password;
		$DPdata['type'] = 'sale';

		$_service_fee = 0;
		$payment_amount = ( si_has_invoice_deposit( $invoice->get_id() ) ) ? $invoice->get_deposit() : $invoice->get_balance();

		if ( strpos( $payment_profile[ $vault_id ], 'Credit' ) !== false ) {
			$_service_fee = $payment_amount * ( self::get_convenience_fee() / 100 );

		}
		$DPdata['amount'] = si_get_number_format( $payment_amount + $_service_fee );
		$DPdata['customer_vault_id'] = $vault_id;
		$DPdata = apply_filters( 'si_nmi_nvp_data_vaulted', $DPdata );

		$transaction_response = self::api_transaction( $DPdata );
		if ( ! $transaction_response ) {
			return false;
		}

		if ( $_service_fee > 0.00 ) {
			self::add_service_fee( $invoice, $_service_fee );
		}

		$payment_id = SI_Payment::new_payment( array(
			'payment_method' => self::PAYMENT_METHOD,
			'invoice' => $invoice_id,
			'amount' => $DPdata['amount'],
			'data' => array(
			'invoice_id' => $invoice->get_id(),
			'transaction_id' => $transaction_response['transactionid'],
			'profile_id' => $transaction_response['customer_vault_id'],
			'payment_profile_id' => $transaction_response['customer_vault_id'],
			'live' => ( self::$api_mode == self::MODE_LIVE ),
			'api_response' => $transaction_response,
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


	/////////////////
	// API Utility //
	/////////////////

	public static function api_transaction( $post_data = array() ) {

		// convert NVP to a string
		$post_string = '';
		foreach ( $post_data as $key => $value ) {
			if ( is_array( $value ) ) {
				continue;
			}
			$post_string .= "{$key}=".urlencode( $value ).'&';
		}
		$post_string = rtrim( $post_string, '& ' );
		do_action( 'si_log', __CLASS__ . '::' . __FUNCTION__ . ' - post_string', $post_string );

		// Send request
		$response = wp_remote_post( self::API_ENDPOINT, array(
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
		do_action( 'si_log', __CLASS__ . '::' . __FUNCTION__ . ' - NMI Response', $response );

		$response_code = $response['response']; // The response we want to validate on
		if ( $response_code != 1 ) {
			self::set_error_messages( $response['responsetext'] );
			return false;
		}

		return $response;
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
			self::set_message( $response, self::MESSAGE_STATUS_ERROR );
		} else {
			do_action( 'si_error', __CLASS__ . '::' . __FUNCTION__ . ' - Auth.net Error Response', $response );
		}
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
SA_NMI_Tokenized::register();
