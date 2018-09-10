<?php

/**
 * Paypal offsite payment processor.
 *
 * These actions are fired for each checkout page.
 *
 * Payment page - 'si_checkout_action_'.SI_Checkouts::PAYMENT_PAGE
 * Review page - 'si_checkout_action_'.SI_Checkouts::REVIEW_PAGE
 * Confirmation page - 'si_checkout_action_'.SI_Checkouts::CONFIRMATION_PAGE
 *
 * Necessary methods:
 * get_instance -- duh
 * get_slug -- slug for the payment process
 * get_options -- used on the invoice payment dropdown
 * process_payment -- called when the checkout is complete before the confirmation page is shown. If a
 * payment fails than the user will be redirected back to the invoice.
 *
 * CC processors can override the credit card form with a credit_card_payment_form method
 *
 * FUTURE Create a customer with the total invoice amount, then capture X amount manually via admin.
 *
 * @package SI
 * @subpackage Payment Processing_Processor
 */
class SA_Stripe extends SI_Credit_Card_Processors {
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

	const STRIPE_CUSTOMER_KEY_USER_META = 'si_stripe_customer_id_v1';
	const PLAID_ACCOUNT_TOKEN = 'si_plaid_account_id_v1';

	const TOKEN_INPUT_NAME = 'stripe_charge_token';

	const API_MODE_OPTION = 'si_stripe_mode';
	const CURRENCY_CODE_OPTION = 'si_stripe_currency';
	const PAYMENT_METHOD = 'Credit/ACH (Stripe)';
	const PAYMENT_SLUG = 'stripe';

	const UPDATE = 'stripe_version_upgrade_v1';

	protected static $instance;
	protected static $api_mode = self::MODE_TEST;
	private static $payment_modal;
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

	public static function register() {
		// Register processor
		self::add_payment_processor( __CLASS__, __( 'Stripe' , 'sprout-invoices' ) );

		if ( ! self::is_active() ) {
			return;
		}

		// Enqueue Scripts
		if ( apply_filters( 'si_remove_scripts_styles_on_doc_pages', '__return_true' ) ) {
			// enqueue after enqueue is filtered
			add_action( 'si_doc_enqueue_filtered', array( __CLASS__, 'enqueue' ) );
		} else { // enqueue normal
			add_action( 'wp_enqueue_scripts', array( __CLASS__, 'enqueue' ) );
		}

		add_action( 'si_head', array( __CLASS__, 'si_add_stylesheet' ), 100 );

		// Add Recurring button
		add_action( 'recurring_payments_profile_info', array( __CLASS__, 'stripe_profile_link' ) );

		// plaid token generation
		add_filter( 'si_payment_options', array( get_class(), 'add_plaid_option' ), 20, 2 );
		add_action( 'wp_ajax_' . self::AJAX_ACTION_PLAID_TOKEN, array( get_class(), 'callback_for_plaid_token' ) );
		add_action( 'wp_ajax_nopriv_' . self::AJAX_ACTION_PLAID_TOKEN, array( get_class(), 'callback_for_plaid_token' ) );
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
		if ( 'true' == self::$payment_modal ) {
			$option['purchase_button_callback'] = array( __CLASS__, 'payment_button' );
		}
		return $option;
	}

	protected function __construct() {
		parent::__construct();
		self::$api_mode = get_option( self::API_MODE_OPTION, self::MODE_TEST );
		self::$payment_modal = get_option( self::MODAL_JS_OPTION, true );
		self::$disable_stripe_js = get_option( self::DISABLE_JS_OPTION, false );
		self::$currency_code = get_option( self::CURRENCY_CODE_OPTION, 'usd' );

		self::$api_secret_key = get_option( self::API_SECRET_KEY_OPTION, '' );
		self::$api_pub_key = get_option( self::API_PUB_KEY_OPTION, '' );
		self::$api_secret_key_test = get_option( self::API_SECRET_KEY_TEST_OPTION, '' );
		self::$api_pub_key_test = get_option( self::API_PUB_KEY_TEST_OPTION, '' );

		// plaid
		self::$plaid_api_client_id = get_option( self::PLAID_API_CLIENT_ID, '' );
		self::$plaid_api_pub_key = get_option( self::PLAID_API_PUB_KEY, '' );
		self::$plaid_api_secret_key = get_option( self::PLAID_API_SECRET_KEY, '' );

		// Remove pages
		add_filter( 'si_checkout_pages', array( $this, 'remove_checkout_pages' ) );

		if (  'true' != self::$disable_stripe_js ) {
			add_filter( 'sa_get_form_field', array( __CLASS__, 'filter_credit_form' ), 10, 4 );
			add_filter( 'si_valid_process_payment_page_fields', '__return_false' );
			add_action( 'si_credit_card_payment_fields', array( __CLASS__, 'modify_credit_form' ) );
		}
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
					self::MODAL_JS_OPTION => array(
						'label' => __( 'Use Stripe Checkout' , 'sprout-invoices' ),
						'option' => array(
							'label' => __( 'Stripe Checkout uses a slick modal pop-up to handle the credit card payment.' , 'sprout-invoices' ),
							'type' => 'checkbox',
							'default' => ( 'true' == self::$payment_modal ) ? 1 : 0,
							'value' => '1',
							'description' => __( 'Disable if you rather use the credit card form that is integrated with Sprout Invoices.' , 'sprout-invoices' ),
							),
						),
					self::DISABLE_JS_OPTION => array(
						'label' => __( 'Disable Stripe JS' , 'sprout-invoices' ),
						'option' => array(
							'label' => __( 'Disable Stripe.js' , 'sprout-invoices' ),
							'type' => 'checkbox',
							'value' => '1',
							'default' => ( 'true' == self::$disable_stripe_js ) ? 1 : 0,
							'description' => __( 'Only recommended if you\'re running a site with SSL already. Do not disable Stripe.js if you\'re using Stripe Checkout.' , 'sprout-invoices' ),
							),
						),
					),
				),
			);
		return $settings;
	}

	///////////////////
	// Payment Modal //
	///////////////////

	public static function payment_button( $invoice_id = 0 ) {
		if ( ! $invoice_id ) {
			$invoice_id = get_the_id();
		}
		$invoice = SI_Invoice::get_instance( $invoice_id );

		$user = si_who_is_paying( $invoice );
		$user_email = ( $user ) ? $user->user_email : '' ;

		$key = ( self::$api_mode === self::MODE_TEST ) ? self::$api_pub_key_test : self::$api_pub_key ;
		$payment_amount = ( si_has_invoice_deposit( $invoice->get_id() ) ) ? $invoice->get_deposit() : $invoice->get_balance();

		$data_attributes = array(
				'key' => $key,
				'name' => get_bloginfo( 'name' ),
				'email' => $user_email,
				//'image' => ( get_theme_mod( 'si_logo' ) ) ? esc_url( get_theme_mod( 'si_logo', si_doc_header_logo_url() ) ) : si_doc_header_logo_url(),
				'description' => $invoice->get_title(),
				'currency' => self::get_currency_code( $invoice_id ),
				'amount' => self::convert_money_to_cents( $payment_amount, $invoice ),
				'allow-remember-me' => 'false',
			);
		$data_attributes = apply_filters( 'si_stripe_js_data_attributes', $data_attributes, $invoice_id );
		?>
			<form action="<?php echo add_query_arg( array( SI_Checkouts::CHECKOUT_ACTION => SI_Checkouts::PAYMENT_PAGE ), si_get_credit_card_checkout_form_action() ) ?>" method="POST" class="button" id="stripe_pop_form">
				<input type="hidden" name="<?php echo SI_Checkouts::CHECKOUT_ACTION ?>" value="<?php echo SI_Checkouts::PAYMENT_PAGE ?>" />
				<script
					src="https://checkout.stripe.com/checkout.js" class="stripe-button"
					<?php foreach ( $data_attributes as $attribute => $value ) : ?>
						data-<?php echo esc_js( $attribute ) ?>="<?php echo esc_js( $value ) ?>"
					<?php endforeach ?>
				></script>
			</form>
			<style type="text/css">
				#payment_selection.dropdown {
					  z-index: 9999;
				}
				#stripe_pop_form.button {
					float: right;
					padding: 0;
					margin-top: -6px;
					margin-left: 10px;
					background-color: transparent;
				}
				#payment_selection.dropdown #stripe_pop_form.button {
					float: none;
					padding: inherit;
					margin-top: 0px;
					margin-right: 15px;
					margin-bottom: 15px;
					text-align: right;
				}
			</style>
		<?php
	}

	///////////
	// Plaid //
	///////////

	public static function add_plaid_option( $enabled_processors, $return ) {
		if ( 'options' !== $return ) {
			return $enabled_processors;
		}
		if ( ! self::$plaid_api_pub_key || '' === self::$plaid_api_pub_key ) {
			return $enabled_processors;
		}
		$enabled_processors['plaid'] = array(
			'label' => __( 'Bank Account' , 'sprout-invoices' ),
			'purchase_button_callback' => array( __CLASS__, 'add_plaid_button' ),
			);
		return $enabled_processors;
	}

	public static function add_plaid_button() {
		if ( '' !== self::$plaid_api_pub_key ) {
			?>
				<?php printf( '<button id="plaid_auth" class="button"><span>%s</span></button>', __( 'Bank Transfer' , 'sprout-invoices' ) ) ?>
				<script src="https://cdn.plaid.com/link/v2/stable/link-initialize.js"></script>
				<style type="text/css">
					#payment_selection.dropdown #plaid.payment_option {
						display: block;
						clear: both;
						min-height: 45px;
					}
					#plaid_auth.button {
					    float: right;
    					margin-right: 15px;
					}
				</style>
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

		$client_id = self::get_client_id( $_REQUEST['invoice_id'] );
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


	public static function si_add_stylesheet() {
		echo '<script type="text/javascript" src="https://js.stripe.com/v1/"></script>';
		echo '<script type="text/javascript" src="' . SA_ADDON_STRIPE_URL . '/resources/si-stripe.jquery.js"></script>';

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
			);
		} ?>

			<script type="text/javascript">
				/* <![CDATA[ */
				var si_stripe_js_object = <?php echo wp_json_encode( $si_js_object ); ?>;
				/* ]]> */
			</script>
		<?php
	}

	/**
	 * Add the scripts and localize the pub key
	 * @return
	 */
	public static function enqueue() {
		if ( is_single() && ( get_post_type( get_the_ID() ) === SI_Invoice::POST_TYPE ) ) {
			// enqueue
			wp_enqueue_script( 'stripe-js', 'https://js.stripe.com/v1/', array( 'jquery' ) );
			wp_enqueue_script( 'si-stripe-js', SA_ADDON_STRIPE_URL . '/resources/si-stripe.jquery.js', array( 'jquery', 'stripe-js' ), SA_ADDON_STRIPE_VERSION );

			// Localize the pub key
			$pub_key = ( get_option( self::API_MODE_OPTION, self::MODE_TEST ) === self::MODE_TEST ) ? get_option( self::API_PUB_KEY_TEST_OPTION, '' ) : get_option( self::API_PUB_KEY_OPTION, '' );
			$si_js_object = array(
				'pub_key' => $pub_key,
				'token_input' => self::TOKEN_INPUT_NAME,
			);

			// Enqueue scripts
			wp_localize_script( 'si-stripe-js', 'si_stripe_js_object', apply_filters( 'si_stripe_js_object_localization', $si_js_object ) );
		}
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
	 * Remove the input name attributes when using stripe.js to be as secure as possible.
	 *
	 * @param string $key      Form field key
	 * @param array $data      Array of data to build form field
	 * @param string $category group the form field belongs to
	 * @return string           form field input, select, radio, etc.
	 */
	public static function filter_credit_form( $form_field, $key, $data, $category ) {
		if ( is_single() && ( get_post_type( get_the_ID() ) === SI_Invoice::POST_TYPE ) ) {
			if ( 'credit' === $category  ) {
				$form_field = preg_replace( '/name=".*?"/', '', $form_field );
			}
		}
		return $form_field;
	}

	/**
	 * Process a payment
	 *
	 * @param SI_Checkouts $checkout
	 * @param SI_Invoice $invoice
	 * @return SI_Payment|bool FALSE if the payment failed, otherwise a Payment object
	 */
	public function process_payment( SI_Checkouts $checkout, SI_Invoice $invoice ) {

		// Recurring
		if ( si_is_invoice_recurring( $invoice ) ) {
			$this->create_recurring_payment_plan( $checkout, $invoice );
			$charge_reciept = $this->add_customer_to_plan( $checkout, $invoice );
		} else { // default
			$charge_reciept = $this->charge_stripe( $checkout, $invoice );
		}

		do_action( 'si_log', __CLASS__ . '::' . __FUNCTION__ . ' - Stripe Response', $charge_reciept );
		if ( ! $charge_reciept ) {
			return false;
		}

		$payment_id = SI_Payment::new_payment( array(
			'payment_method' => self::PAYMENT_METHOD,
			'invoice' => $invoice->get_id(),
			'amount' => self::convert_cents_to_money( $charge_reciept['amount'], $invoice ),
			'data' => array(
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

	/**
	 * Charge via Stripe API using a token or the full credit card data.
	 *
	 * @param SI_Checkouts $checkout
	 * @param SI_Invoice $purchase
	 * @return array
	 */
	private function charge_stripe( SI_Checkouts $checkout, SI_Invoice $invoice ) {
		$invoice = $checkout->get_invoice();
		$client = $invoice->get_client();

		$user_id = si_whos_user_id_is_paying( $invoice );

		self::setup_stripe();

		try {

			// Create the payment data for the customer
			$purchase_data = $this->purchase_data( $checkout, $invoice );
			if ( ! $purchase_data ) {
				return false;
			}

			// Create the customer
			$customer_id = $this->get_customer( $user_id, $purchase_data );
			if ( ! $customer_id ) {
				self::set_error_messages( 'ERROR: No customer id was created.' );
				return false;
			}
			$payment_amount = ( si_has_invoice_deposit( $invoice->get_id() ) ) ? $invoice->get_deposit() : $invoice->get_balance();

			// Charge the card!
			$charge = \Stripe\Charge::create(
				array(
					'amount' => self::convert_money_to_cents( sprintf( '%0.2f', $payment_amount ), $invoice ),
					'currency' => self::get_currency_code( $invoice->get_id() ),
					'customer' => $customer_id,
					'description' => get_the_title( $invoice->get_id() ),
				)
			);
			$response = array(
					'id' => $charge->id,
					'amount' => $charge->amount,
					'customer' => $charge->customer,
					'card' => ( isset( $charge->card->id ) ) ? $charge->card->id : '',
					'bank' => ( isset( $charge->source->bank_name ) ) ? $charge->source->bank_name : '',
				);
			// Return something for the response
			return $response;
		} catch ( Exception $e ) {
			self::set_error_messages( $e->getMessage() );
			return false;
		}
	}


	/**
	 * Build purchase data array from submission or token
	 *
	 * @param SI_Checkouts $checkout
	 * @param SI_Invoice $purchase
	 * @return array
	 */
	public function purchase_data( SI_Checkouts $checkout, SI_Invoice $invoice ) {

		if ( isset( $_REQUEST['stripeToken'] ) && '' !== $_POST['stripeToken'] ) {
			$card_data = $_REQUEST['stripeToken'];
		} elseif ( isset( $_POST[ self::TOKEN_INPUT_NAME ] ) && $_POST[ self::TOKEN_INPUT_NAME ] !== '' ) {
			$card_data = $_POST[ self::TOKEN_INPUT_NAME ];
		} else {
			// check for fallback mode
			if ( 'true' != self::$disable_stripe_js ) {
				self::set_error_messages( 'Missing Stripe token. Please contact support.' );
				return false;
			} else {
				$card_data = array(
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

			}
		}
		return apply_filters( 'si_stripe_purchase_data', $card_data, $checkout, $invoice );
	}

	/**
	 * Get the stripe customer or create a new one.
	 * @param  int $user_id
	 * @param  array  $card_data
	 * @return
	 */
	public function get_customer( $user_id = 0, $card_data = array() ) {
		$customer_exists = false;
		$customer_id = 0;

		// Find the stored customer id.
		if ( $user_id ) {
			$customer_id = get_user_meta( $user_id, self::STRIPE_CUSTOMER_KEY_USER_META, true );
			if ( $customer_id ) {
				$customer_exists = true;

				// Update the customer to ensure their card data is up to date
				$stripe_customer = \Stripe\Customer::retrieve( $customer_id );

				if ( isset( $stripe_customer->deleted ) && $stripe_customer->deleted ) {

					// This customer was deleted
					$customer_exists = false;

				} else {

					$stripe_customer->card = $card_data;
					$stripe_customer->save();

				}
			}
		}

		// If no customer exists create one.
		if ( $user_id && ! $customer_exists ) {
			$user = get_userdata( $user_id );
			$email = $user->user_email;

			// Create a customer first so we can retrieve them later for future payments
			$customer = \Stripe\Customer::create( array(
				'description' => $email,
				'email'       => $email,
				'card'        => $card_data,
				)
			);

			$customer_id = is_array( $customer ) ? $customer['id'] : $customer->id;
		}

		if ( $user_id ) {
			update_user_meta( $user_id, self::STRIPE_CUSTOMER_KEY_USER_META, $customer_id );
		}

		return $customer_id;
	}

	private static function get_currency_code( $invoice_id ) {
		return apply_filters( 'si_currency_code', self::$currency_code, $invoice_id, self::PAYMENT_METHOD );
	}

	////////////////
	// Recurring //
	////////////////

	/**
	 * Create the recurring payment profile.
	 */
	private function create_recurring_payment_plan( SI_Checkouts $checkout, SI_Invoice $invoice ) {

		self::setup_stripe();

		try {
			$invoice_id = $invoice->get_id();
			$term = SI_Subscription_Payments::get_term( $invoice_id ); // day, week, month, or year
			$duration = (int) SI_Subscription_Payments::get_duration( $invoice_id );
			$price = SI_Subscription_Payments::get_renew_price( $invoice_id );

			$name = get_the_title( $invoice_id );
			// Recurring Plan the customer will be changed to.
			$plan = \Stripe\Plan::create(
				array(
					'product' => array( 'name' => $name, 'id' => $invoice_id ),
					'nickname' => sprintf( __( 'Invoice: %s', 'sprout-invoices' ), $name ),
					'amount' => self::convert_money_to_cents( sprintf( '%0.2f', $price ), $invoice ),
					'currency' => self::get_currency_code( $invoice_id ),
					'interval' => $term,
					'id' => $invoice_id.self::convert_money_to_cents( sprintf( '%0.2f', $price ), $invoice ),
				)
			);

			do_action( 'si_log', __CLASS__ . '::' . __FUNCTION__ . ' - Stripe PLAN Response', $plan );

			do_action( 'si_stripe_recurring_payment_plan_created', $plan );
			// Return something for the response
			return true;
		} catch ( Exception $e ) {
			self::set_error_messages( $e->getMessage() );
			return false;
		}
	}

	/**
	 * Create the recurring payment profile.
	 */
	private function add_customer_to_plan( SI_Checkouts $checkout, SI_Invoice $invoice ) {

		$invoice_id = $invoice->get_id();

		self::setup_stripe();

		try {

			$user = si_who_is_paying( $invoice );

			$purchase_data = $this->purchase_data( $checkout, $invoice );
			if ( ! $purchase_data ) {
				return false;
			}

			$price = SI_Subscription_Payments::get_renew_price( $invoice_id );
			$amount_in_cents = self::convert_money_to_cents( sprintf( '%0.2f', $price ), $invoice );
			$balance = ( si_has_invoice_deposit( $invoice_id ) ) ? $invoice->get_deposit() : $invoice->get_balance();

			$subscribe = \Stripe\Customer::create( array(
				'card' => $purchase_data,
				'plan' => $invoice_id.$amount_in_cents,
				'email' => $user->user_email,
				'account_balance' => self::convert_money_to_cents( sprintf( '%0.2f', $balance - $price ), $invoice ),
				) // A positive amount as a customer balance increases the amount of the next invoice. A negative amount becomes a credit that decreases the amount of the next invoice.
			);

			$subscribe = array(
					'id' => $subscribe->id,
					'subscription_id' => $subscribe->subscriptions->data[0]->id,
					'amount' => $amount_in_cents,
					'plan' => $invoice_id.$amount_in_cents,
					'card' => $purchase_data,
					'email' => $user->user_email,
				);

			// Payment
			$payment_id = SI_Payment::new_payment( array(
				'payment_method' => self::PAYMENT_METHOD,
				'invoice' => $invoice_id,
				'amount' => $price,
				'data' => array(
				'live' => ( self::MODE_LIVE === self::$api_mode ),
				'api_response' => $subscribe,
				),
			), SI_Payment::STATUS_RECURRING );

			do_action( 'si_stripe_recurring_payment_profile_created', $payment_id );

			// Passed back to create the initial payment
			$response = $subscribe;
			$response['amount'] = $amount_in_cents;
			$response['plan'] = $invoice_id.$amount_in_cents;

			return $response;
		} catch ( Exception $e ) {
			self::set_error_messages( $e->getMessage() );
			return false;
		}
	}

	public function verify_recurring_payment( SI_Payment $payment ) {
		$invoice_id = $payment->get_invoice_id();
		if ( ! $invoice_id ) {
			return;
		}
		$invoice = SI_Invoice::get_instance( $invoice_id );

		$data = $payment->get_data();
		if ( ! isset( $data['api_response']['subscription_id'] ) || ! isset( $data['api_response']['id'] ) ) {
			return false;
		}
		$status = self::get_subscription_status( $data['api_response']['id'], $data['api_response']['subscription_id'] );
		do_action( 'si_verify_recurring_payment_status', $status, $payment );
		if ( $status != 'active' ) {
			$payment->set_status( SI_Payment::STATUS_CANCELLED );
		}
		return $status;
	}

	public function cancel_recurring_payment( SI_Invoice $invoice ) {
		$payment = self::get_recurring_payment( $invoice );
		if ( ! $payment ) {
			return;
		}
		$data = $payment->get_data();
		if ( ! isset( $data['api_response']['subscription_id'] ) || ! isset( $data['api_response']['id'] ) ) {
			return false;
		}
		try {
			self::setup_stripe();
			$customer = \Stripe\Customer::retrieve( $data['api_response']['id'] );
			$subscription = $customer->subscriptions->retrieve( $data['api_response']['subscription_id'] );
			$subscription->cancel();
		} catch ( Exception $e ) {
			self::set_error_messages( $e->getMessage() );
			$status = false;
		}
		do_action( 'si_cancelled_recurring_payment', $subscription, $invoice );
	}

	public static function get_subscription_status( $customer_id, $subscription_id ) {
		try {
			self::setup_stripe();
			$customer = \Stripe\Customer::retrieve( $customer_id );
			$subscription = $customer->subscriptions->retrieve( $subscription_id );

			$status = $subscription->status;

		} catch ( Exception $e ) {
			self::set_error_messages( $e->getMessage() );
			$status = false;
		}
		return $status;
	}

	public static function stripe_profile_link( $payment ) {
		if ( $payment->get_payment_method() !== self::PAYMENT_METHOD ) {
			return;
		}
		$data = $payment->get_data();

		if ( isset( $data['api_response']['subscription_id'] ) && isset( $data['api_response']['id'] ) ) {

			$status = self::get_subscription_status( $data['api_response']['id'], $data['api_response']['subscription_id'] );

			printf( __( '<b>Current Payment Status:</b> <code>%s</code>' , 'sprout-invoices' ), $status );
			echo ' &mdash; ';
			_e( 'Stripe Subscription ID: ' , 'sprout-invoices' );
			if ( isset( $data['live'] ) && ! $data['live'] ) {
				printf( '<a class="payment_profile_link" href="https://dashboard.stripe.com/test/customers/%s" target="_blank">%s</a>', $data['api_response']['id'], $data['api_response']['subscription_id'] );
			} else {
				printf( '<a class="payment_profile_link" href="https://dashboard.stripe.com/customers/%s" target="_blank">%s</a>', $data['api_response']['id'], $data['api_response']['subscription_id'] );
			}
		}
	}


	//////////////
	// Utility //
	//////////////

	private static function setup_stripe() {
		if ( ! class_exists( 'Stripe' ) ) {
			require_once 'inc/stripe-php-6.13.0/init.php';
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

	private static function convert_money_to_cents( $value, $invoice ) {
		if ( ! apply_filters( 'si_convert_money_to_cents_for_stripe', true, $invoice ) ) {
			return (float) $value;
		}
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

	private static function convert_cents_to_money( $value, $invoice ) {
		if ( ! apply_filters( 'si_convert_money_to_cents_for_stripe', true, $invoice ) ) {
			return (float) $value;
		}
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
			do_action( 'si_error', __CLASS__ . '::' . __FUNCTION__ . ' - error message from stripe', $message );
		}
	}



	public static function get_client_id( $invoice_id = 0, $logged_in = 1 ) {
		$client_id = 0;

		// a bit hacky so that an invoice id doesn't have to be passed if the client id is given instead.
		if ( get_post_type( $invoice_id ) === SI_Client::POST_TYPE ) {
			return $invoice_id;
		}
		if ( ! $invoice_id && is_single() && SI_Invoice::POST_TYPE === get_post_type( get_the_ID() ) ) {
			$invoice_id = get_the_ID();
		}
		if ( $invoice_id ) {
			$invoice = SI_Invoice::get_instance( $invoice_id );
			if ( is_a( $invoice, 'SI_Invoice' ) ) {
				$client_id = $invoice->get_client_id();
			}
		}
		if ( ! $client_id && $logged_in ) {
			$user_id = get_current_user_id();
			$client_ids = SI_Client::get_clients_by_user( $user_id );
			if ( ! empty( $client_ids ) ) {
				$client_id = array_pop( $client_ids );
			}
		}
		return $client_id;
	}
}
SA_Stripe::register();
