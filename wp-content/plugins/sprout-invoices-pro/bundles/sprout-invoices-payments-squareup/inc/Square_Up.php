<?php

class SA_Square extends SI_Credit_Card_Processors {
	const MODE_TEST = 'test';
	const MODE_LIVE = 'live';
	const API_SECRET_KEY_OPTION = 'si_square_access_id';
	const API_SECRET_KEY_TEST_OPTION = 'si_square_access_id_test';
	const API_PUB_KEY_OPTION = 'si_square_app_id';
	const API_PUB_KEY_TEST_OPTION = 'si_square_app_id_test';

	const API_LOCATION_ID_OPTION = 'si_square_location_id';
	const API_LOCATION_ID_TEST_OPTION = 'si_square_location_id_test';

	const TOKEN_INPUT_NAME = 'square_charge_token';

	const API_MODE_OPTION = 'si_square_mode';
	const CURRENCY_CODE_OPTION = 'si_square_currency';
	const PAYMENT_METHOD = 'Credit (Square)';
	const PAYMENT_SLUG = 'square';

	const UPDATE = 'square_version_upgrade_v1';

	protected static $instance;
	protected static $api_mode = self::MODE_TEST;
	private static $api_access_id_test;
	private static $api_app_id_test;
	private static $api_location_id_test;
	private static $api_access_id;
	private static $api_app_id;
	private static $api_location_id;
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
		self::add_payment_processor( __CLASS__, __( 'Square' , 'sprout-invoices' ) );

		if ( ! self::is_active() ) {
			return;
		}

		self::$api_access_id = get_option( self::API_SECRET_KEY_OPTION, '' );
		self::$api_app_id = get_option( self::API_PUB_KEY_OPTION, '' );
		self::$api_location_id = get_option( self::API_LOCATION_ID_OPTION, '' );
		self::$api_access_id_test = get_option( self::API_SECRET_KEY_TEST_OPTION, '' );
		self::$api_app_id_test = get_option( self::API_PUB_KEY_TEST_OPTION, '' );
		self::$api_location_id_test = get_option( self::API_LOCATION_ID_TEST_OPTION, '' );

		add_action( 'si_head', array( __CLASS__, 'si_add_stuff_to_the_header' ), 100 );
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
		self::$api_mode = get_option( self::API_MODE_OPTION, self::MODE_TEST );
		self::$currency_code = get_option( self::CURRENCY_CODE_OPTION, 'USD' );

		// Remove pages
		add_filter( 'si_checkout_pages', array( $this, 'remove_checkout_pages' ) );

		add_filter( 'sa_credit_card_fields', array( __CLASS__, 'modify_credit_form_fields' ) );
		add_filter( 'si_payment_billing_fields', '__return_empty_array' );
		add_filter( 'si_valid_process_payment_page_fields', '__return_false' );
		add_action( 'si_credit_card_payment_fields', array( __CLASS__, 'modify_credit_form' ) );
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
			'si_square_settings' => array(
				'title' => __( 'Square Settings' , 'sprout-invoices' ),
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
					self::API_SECRET_KEY_OPTION => array(
						'label' => __( 'Personal Access Token' , 'sprout-invoices' ),
						'option' => array(
							'type' => 'text',
							'default' => self::$api_access_id,
							),
						),
					self::API_PUB_KEY_OPTION => array(
						'label' => __( 'Application ID' , 'sprout-invoices' ),
						'option' => array(
							'type' => 'text',
							'default' => self::$api_app_id,
							),
						),
					self::API_LOCATION_ID_OPTION => array(
						'label' => __( 'Location ID' , 'sprout-invoices' ),
						'option' => array(
							'type' => 'text',
							'default' => self::$api_location_id,
							'description' => __( 'If unknown one will be found before the first payment.', 'sprout-invoices' ),
							),
						),
					self::API_SECRET_KEY_TEST_OPTION => array(
						'label' => __( 'Sandbox Personal Access Token' , 'sprout-invoices' ),
						'option' => array(
							'type' => 'text',
							'default' => self::$api_access_id_test,
							),
						),
					self::API_PUB_KEY_TEST_OPTION => array(
						'label' => __( 'Sandbox Application ID' , 'sprout-invoices' ),
						'option' => array(
							'type' => 'text',
							'default' => self::$api_app_id_test,
							),
						),
					self::API_LOCATION_ID_TEST_OPTION => array(
						'label' => __( 'Location ID' , 'sprout-invoices' ),
						'option' => array(
							'type' => 'text',
							'default' => self::$api_location_id_test,
							'description' => __( 'If unknown one will be found before the first payment.', 'sprout-invoices' ),
							),
						),
					),
				),
			);
		return $settings;
	}


	public static function si_add_stuff_to_the_header() {
		$app_id = ( get_option( self::API_MODE_OPTION, self::MODE_TEST ) === self::MODE_TEST ) ? self::$api_app_id_test : self::$api_app_id;
		?>
			<script type="text/javascript">
				/* <![CDATA[ */
					
					var squareAppID = '<?php echo esc_js( $app_id ); ?>';

				/* ]]> */
			</script>
			<style type="text/css">
			#billing_fields {
				display: none;
			}
			#credit_card_checkout_wrap .sa-form .label_wrap {
				display: block;
			}
			#credit_card_fields legend {
				display: none;
			}
			#credit_card_checkout_wrap .sa-form-aligned .sa-control-group label {
				text-align: left;
				display: inline-block;
				vertical-align: middle;
				width: auto;
				margin: 0;
			}
			#billing_cc_fields {
				width: 60%;
				margin: 0% 20%;
			}
			#billing_cc_fields .sa-fieldset {
				width: 100%;
			}
			.sq-input {
				width: 100%;
				padding: 0.5em 0.6em;
				display: inline-block;
				border: 1px solid #CCC;
				box-shadow: inset 0 1px 3px #DDD;
				border-radius: 4px;
				font-size: 100%;
    			margin: 0;
			}
			#billing_cc_fields .required {
				color: #B94A48;
			}
			.sq-input--focus {
				border-color: #129FEA;
			}
			.sq-input--error {
				color: #B94A48;
    			border-color: #EE5F5B;
			}
			<?php if ( method_exists( 'SI_Templating_API', 'get_invoice_theme_option' ) ) :  ?>
				<?php if ( 'default' === SI_Templating_API::get_invoice_theme_option() ) :  ?>
					.sq-input {
					    height: 2.8em;
					}
					#credit_card_checkout_wrap .label_wrap {
					    display: block;
					}
				<?php endif ?>
			<?php endif ?>
			</style>
		<?php
		print '<script type="text/javascript" src="https://js.squareup.com/v2/paymentform"></script>';
		echo '<script type="text/javascript" src="' . SA_ADDON_SQUARE_URL . '/resources/square.jquery.js"></script>';
	}

	public static function modify_credit_form_fields( $fields = array() ) {
		unset( $fields['cc_name'] );
		unset( $fields['cc_expiration_month'] );
		unset( $fields['cc_expiration_year'] );
		$fields['cc_month_year'] = array(
			'type' => 'input',
			'weight' => 20,
			'label' => __( 'Expiration Date', 'sprout-invoices' ),
			'attributes' => array(
				//'autocomplete' => 'off',
			),
			'required' => true,
		);
		$fields['cc_zip'] = array(
			'type' => 'input',
			'weight' => 20,
			'label' => __( 'Postal Code', 'sprout-invoices' ),
			'attributes' => array(
				//'autocomplete' => 'off',
			),
			'required' => true,
		);
		return $fields;
	}

	/**
	 * Add the square token input to be passed
	 * @return
	 */
	public static function modify_credit_form() {
		printf( '<div class="security_message clearfix"><span class="icon-vault"></span> %s</div>', __( 'This is a secure SSL encrypted payment.' , 'sprout-invoices' ) );
		printf( '<input type="hidden" name="%1$s" id="%1$s" value="">', self::TOKEN_INPUT_NAME );
		echo '<div id="square_errors" class="sa-message error" style="display:none"></div><!-- #stripe_errors -->';
	}

	/**
	 * Process a payment
	 *
	 * @param SI_Checkouts $checkout
	 * @param SI_Invoice $invoice
	 * @return SI_Payment|bool FALSE if the payment failed, otherwise a Payment object
	 */
	public function process_payment( SI_Checkouts $checkout, SI_Invoice $invoice ) {

		$charge_reciept = $this->charge_square( $checkout, $invoice );
		$charge_reciept = json_decode( json_encode( $charge_reciept ), true );

		do_action( 'si_log', __CLASS__ . '::' . __FUNCTION__ . ' - Square Response', $charge_reciept );
		if ( ! $charge_reciept ) {
			return false;
		}

		$payment_id = SI_Payment::new_payment( array(
			'payment_method' => self::PAYMENT_METHOD,
			'invoice' => $invoice->get_id(),
			'amount' => self::convert_cents_to_money( $charge_reciept['tenders'][0]['amount_money']['amount'] ),
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
	 * Charge via Square API using a token or the full credit card data.
	 *
	 * @param SI_Checkouts $checkout
	 * @param SI_Invoice $purchase
	 * @return array
	 */
	private function charge_square( SI_Checkouts $checkout, SI_Invoice $invoice ) {

		if ( ! isset( $_POST[ self::TOKEN_INPUT_NAME ] ) ) {
			return;
		}

		$invoice = $checkout->get_invoice();
		$client = $invoice->get_client();
		$user = si_who_is_paying( $invoice );
		$user_id = si_whos_user_id_is_paying( $invoice );
		$user_email = ( $user ) ? $user->user_email : '' ;

		$payment_amount = ( si_has_invoice_deposit( $invoice->get_id() ) ) ? $invoice->get_deposit() : $invoice->get_balance();

		$access_id = ( get_option( self::API_MODE_OPTION, self::MODE_TEST ) === self::MODE_TEST ) ? self::$api_access_id_test : self::$api_access_id;
		$card_nonce = $_POST[ self::TOKEN_INPUT_NAME ];

		// Remote API request
		$params = array(
			'method' => 'POST',
			'headers' => array(
					'Authorization' => 'Bearer ' . $access_id,
					'Content-Type' => 'application/json',
				),
			'sslverify' => false,
			'timeout' => 15,
			'body' => json_encode( array(
				'card_nonce' => $card_nonce,
				'amount_money' => array(
				'amount' => self::convert_money_to_cents( $payment_amount ),
				'currency' => self::get_currency_code( $invoice->get_id() ),
				),
				'idempotency_key' => (string) $invoice->get_id() . time(),
				'buyer_email_address' => $user_email,
			) ),
		);
		$params = apply_filters( 'si_square_api_post_params', $params );
		$local_id = ( get_option( self::API_MODE_OPTION, self::MODE_TEST ) === self::MODE_TEST ) ? self::$api_location_id_test : self::$api_location_id;
		if ( '' === $local_id ) {
			$local_id = self::find_a_location_id();
		}

		if ( ! $local_id ) {
			return false;
		}

		$api_url = apply_filters( 'si_square_api_post_api_url', 'https://connect.squareup.com/v2/locations/' . $local_id . '/transactions', $checkout, $invoice );

		$response = wp_remote_post( $api_url, $params );
		$payment_response = json_decode( wp_remote_retrieve_body( $response ) );
		if ( isset( $payment_response->errors ) ) {
			foreach ( $payment_response->errors as $error ) {
				if ( isset( $error->detail ) ) {
					self::set_error_messages( $error->detail );
				}
			}
		}
		if ( ! isset( $payment_response->transaction ) ) {
			self::set_error_messages( 'PAYMENT ERROR: 1242' );
			return false;
		}
		return $payment_response->transaction;
	}

	public static function find_a_location_id() {
		$access_id = ( get_option( self::API_MODE_OPTION, self::MODE_TEST ) === self::MODE_TEST ) ? self::$api_access_id_test : self::$api_access_id;
		if ( '' === $access_id ) {
			return;
		}

		// Remote API request
		$params = array(
			'method' => 'GET',
			'headers' => array(
					'Authorization' => 'Bearer ' . $access_id,
					'Content-Type' => 'application/json',
				),
			'sslverify' => false,
			'timeout' => 15,
			'body' => array(),
		);
		$response = wp_remote_post( 'https://connect.squareup.com/v2/locations', $params );

		if ( is_wp_error( $response ) ) {
			return false;
		}

		$locations_response = json_decode( wp_remote_retrieve_body( $response ) );
		if ( empty( $locations_response ) ) {
			return false;
		}
		foreach ( $locations_response->locations as $key => $location ) {
			if ( isset( $location->id ) ) {
				$option_name = ( get_option( self::API_MODE_OPTION, self::MODE_TEST ) === self::MODE_TEST ) ? self::API_LOCATION_ID_TEST_OPTION : self::API_LOCATION_ID_OPTION;
				update_option( $option_name, $location->id );
				return $location->id;
			}
		}
	}

	private static function get_currency_code( $invoice_id ) {
		return apply_filters( 'si_currency_code', self::$currency_code, $invoice_id, self::PAYMENT_METHOD );
	}

	//////////////
	// Utility //
	//////////////

	private static function convert_money_to_cents( $value ) {
		if ( ! apply_filters( 'si_convert_money_to_cents_for_square', true ) ) {
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

	private static function convert_cents_to_money( $value ) {
		if ( ! apply_filters( 'si_convert_money_to_cents_for_square', true ) ) {
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
			do_action( 'si_error', __CLASS__ . '::' . __FUNCTION__ . ' - error message from square', $message );
		}
	}
}
SA_Square::register();
