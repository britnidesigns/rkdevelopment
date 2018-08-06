<?php

class SI_Mercadopago extends SI_Offsite_Processors {
	// GBS Settings
	const API_ID_OPTION = 'si_mercadopago_client_id';
	const API_SECRET_OPTION = 'si_mercadopago_client_secret';
	const SITE_ID_OPTION = 'si_mercadopago_site_id';
	const CURRENCY_CODE_OPTION = 'si_mercadopago_currency';
	const TOKEN_KEY = 'si_mp_token_key'; // Combine with $blog_id to get the actual meta key
	const PAYMENT_METHOD = 'Mercadopago';
	const PAYMENT_SLUG = 'mercadopago';

	protected static $instance;
	private static $client_id;
	private static $client_secret;
	private static $site_id;
	private static $currency_code;

	public static $accesstoken;
	protected static $date;
	protected static $expired;

	protected static $mp;
	const SANDBOX = true;

	public static function get_instance() {
		if ( ! ( isset( self::$instance ) && is_a( self::$instance, __CLASS__ ) ) ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	public static function get_mp() {
		if ( ! ( isset( self::$mp ) && is_a( self::$mp, 'MP' ) ) ) {
			require_once 'lib/mp_sdk.php';
			self::$mp = new MP( get_option( self::API_ID_OPTION ), get_option( self::API_SECRET_OPTION ) );
		}
		if ( self::SANDBOX ) {
			self::$mp->sandbox_mode( true );
		}
		return self::$mp;
	}

	public function get_payment_method() {
		return self::PAYMENT_METHOD;
	}

	public function get_slug() {
		return self::PAYMENT_SLUG;
	}

	public static function returned_from_offsite() {
		return isset( $_GET['mp_payment'] ) && $_GET['mp_payment'] == 1;
	}

	public static function register() {
		self::add_payment_processor( __CLASS__, __( 'Mercadopago' , 'sprout-invoices' ) );
	}

	public static function public_name() {
		return __( 'Mercadopago' , 'sprout-invoices' );
	}

	public static function checkout_options() {
		$option = array(
			'icon' => 'https://s3.amazonaws.com/checkout_images/466be15d-fdb2-4d70-9717-b2b267f296cc.png',
			'label' => __( 'Mercadopago' , 'sprout-invoices' ),
			'cc' => array(),
			'purchase_button_callback' => array( __CLASS__, 'payment_button' ),
			);
		return apply_filters( 'si_mercadopago_checkout_options', $option );
	}

	protected function __construct() {
		parent::__construct();
		self::$client_id = get_option( self::API_ID_OPTION, '' );
		self::$client_secret = get_option( self::API_SECRET_OPTION, '' );
		self::$site_id = get_option( self::SITE_ID_OPTION, 'MLA' );
		self::$currency_code = get_option( self::CURRENCY_CODE_OPTION, 'BRL' );

		add_action( 'si_checkout_action_'.SI_Checkouts::REVIEW_PAGE, array( $this, 'back_from_mercadopago' ), 0, 1 );

		add_action( 'checkout_completed', array( $this, 'post_checkout_redirect' ), 10, 2 );

		add_action( 'processed_payment', array( $this, 'capture_payment_after_auth' ), 10 );
		add_action( 'si_manually_capture_purchase', array( $this, 'manually_capture_purchase' ), 10 );

	}

	/**
	 * Hooked on init add the settings page and options.
	 *
	 */
	public static function register_settings( $settings = array() ) {
		// Settings
		$settings['payments'] = array(
			'si_mercadopago_settings' => array(
				'title' => __( 'Mercadopago' , 'sprout-invoices' ),
				'weight' => 200,
				'settings' => array(
					self::API_ID_OPTION => array(
						'label' => __( 'Client Id' , 'sprout-invoices' ),
						'option' => array(
							'type' => 'text',
							'default' => self::$client_id,
							),
						),
					self::API_SECRET_OPTION => array(
						'label' => __( 'Client Secret' , 'sprout-invoices' ),
						'option' => array(
							'type' => 'text',
							'default' => self::$client_secret,
							),
						),
					self::SITE_ID_OPTION => array(
						'label' => __( 'Site id' , 'sprout-invoices' ),
						'option' => array(
							'type' => 'text',
							'default' => self::$site_id,
							'description' => 'Argentina: MLA; Brasil: MLB',
							),
						),
					self::CURRENCY_CODE_OPTION => array(
						'label' => __( 'Currency Code' , 'sprout-invoices' ),
						'option' => array(
							'type' => 'select',
							'options' => array( 'BRL' => 'Real', 'USD' => 'Dollar', 'ARS' => 'Pesos Argentinos', 'MXN' => 'Peso mexicano', 'VEB' => 'Peso venezuelano' ),
							'default' => self::$currency_code,
							'attributes' => array( 'class' => 'small-text' ),
							),
						),
					),
				),
			);
		return $settings;
	}

	public static function payment_button( $invoice_id = 0 ) {
		if ( ! $invoice_id ) {
			$invoice_id = get_the_id();
		}
		$link = self::get_mp_link( $invoice_id );
		?>
		<a href="<?php echo $link ?>" name="MP-payButton" class="payment_option" id="btnPagar">
			<img src="http://imgmp.mlstatic.com/org-img/banners/ar/medios/468X60.jpg" alt="MercadoPago" title="MercadoPago" width="330" />
		</a>
		<script type="text/javascript" src="https://www.mercadopago.com/org-img/jsapi/mptools/buttons/render.js"></script>
		<style type="text/css">
			#btnPagar {
				float: right;
				margin-top: -11px;
				margin-left: 10px;
			}
			ul #btnPagar {
				float: none;
				margin-top: 0px;
				margin-left: 0px;
			}
		</style>
		<?php
	}

	public static function get_mp_link( $invoice_id = 0 ) {
		$checkout = SI_Checkouts::get_instance();
		$invoice = SI_Invoice::get_instance( $invoice_id );
		$client = $invoice->get_client();

		$user = si_who_is_paying( $invoice );
		$user_email = ( $user ) ? $user->user_email : '' ;

		$ext_reference = $invoice->get_id();

		$checkout_complete_url = $checkout->checkout_complete_url( self::PAYMENT_SLUG );

		$payment_amount = ( si_has_invoice_deposit( $invoice->get_id() ) ) ? $invoice->get_deposit() : $invoice->get_balance();

		$data = array(
			'external_reference' => $ext_reference,
			'items' => array(
				array(
					'id' => $invoice->get_id(),
					'title' => substr( get_bloginfo( 'name' ), 0, 90 ),
					'description' => $invoice->get_title(),
					'quantity' => (int) 1,
					'unit_price' => (float) si_get_number_format( $payment_amount ),
					'currency_id' => self::get_currency_code( $invoice->get_id() ),
				),
			),
			'payer' => array(
				'name' => '',
				'surname' => '',
				'email' => $user_email,
			),
			'back_urls' => array(
				'pending' => add_query_arg( array( 'mp_payment' => 1 ), $checkout_complete_url ),
				'success' => add_query_arg( array( 'mp_payment' => 1 ), $checkout_complete_url ),
				'cancel' => get_permalink( $invoice->get_id() ),
				'error' => get_permalink( $invoice->get_id() ),

			),
		);

		if ( self::DEBUG ) {
			self::set_error_messages( 'mp link data: ' . print_r( $data, true ), false );
		}

		$mp = self::get_mp();
		try {
			$preference_result = $mp->create_preference( $data );
		} catch (Exception $e) {
			// self::set_error_messages( 'fail exception: '.print_r( $e, true ), false );
			return false;
		}

		if ( self::DEBUG ) {
			self::set_error_messages( 'create preference response: ' . print_r( $preference_result, true ), false );
		}

		if ( ! isset( $preference_result['response']['id'] ) ) {
			return false;
		}

		// Set the token/id for the user.
		self::set_token( $preference_result['response']['id'] );

		if ( self::SANDBOX ) {
			return $preference_result['response']['sandbox_init_point'];
		}
		return $preference_result['response']['init_point'];
	}

	/**
	 * We're on the checkout page, just back from PayPal.
	 * Store the token and payer ID that PayPal gives us
	 *
	 * @return void
	 */
	public function back_from_mercadopago( SI_Checkouts $checkout ) {
		// Check to see if the payment processor being used is for this payment processor
		if ( ! is_a( $checkout->get_processor(), __CLASS__ ) ) { // FUTURE have parent class handle this smarter'r
			return;
		}
		if ( self::returned_from_offsite() ) {
			$invoice = $checkout->get_invoice();

			if ( ! isset( $_GET['preference_id'] ) ) {
				return false;
			}

			self::set_token( $_GET['preference_id'], $invoice->get_id() );

			$preference_id = self::get_token( $invoice->get_id() );
			if ( $_GET['preference_id'] !== $preference_id ) {
				self::set_error_messages( 'Payment Failure. Token Mismatch.', true );
				return false;
			}

			$order_status = self::get_mp_payment_status( wp_parse_args( $_GET ) );

			if ( ! $order_status ) {
				self::set_error_messages( 'payment status failed: '.print_r( $status, true ), false );
				self::unset_token( $invoice->get_id() );
				wp_redirect( remove_query_arg( 'mp_payment' ) );
				exit();
			}

			// What's the payment status?
			$status = $order_status['status'];

			// Cycle through the statuses and complete the checkout pages if warranted
			switch ( $status ) {
				case 'approved':
					self::complete_checkout_pages( $checkout );
					// complete the purchase
					break;
				case 'pending':
				case 'in_process':
					self::complete_checkout_pages( $checkout );
					self::set_error_messages( 'Your Payment is Currently Pending or In Process', true );
					break;
				case 'rejected':
				case 'refunded':
				case 'cancelled':
				case 'in_metiation':
				default:
					self::set_error_messages( sprintf( 'Your Payment has been cancelled, refunded or rejected. Code: %s', $order_status['status_detail'] ), true );
			}
		}
	}

	public static function complete_checkout_pages( SI_Checkouts $checkout ) {
		// Payment is complete
		$checkout->mark_page_complete( SI_Checkouts::PAYMENT_PAGE );
		// Skip the review page since that's already done at paypal.
		$checkout->mark_page_complete( SI_Checkouts::REVIEW_PAGE );
	}

	public static function set_token( $token, $invoice_id = 0 ) {
		if ( ! $invoice_id ) {
			$invoice_id = get_the_ID();
		}
		update_post_meta( $invoice_id, self::TOKEN_KEY, $token );
		return $token;
	}

	public static function unset_token( $invoice_id = 0 ) {
		if ( ! $invoice_id ) {
			$invoice_id = get_the_ID();
		}
		delete_post_meta( $invoice_id, self::TOKEN_KEY, $token );
	}

	public static function get_token( $invoice_id = 0 ) {
		if ( ! $invoice_id ) {
			$invoice_id = get_the_ID();
		}
		return get_post_meta( $invoice_id, self::TOKEN_KEY, true );
	}

	public function post_checkout_redirect( SI_Checkouts $checkout, SI_Payment $payment ) {
		if ( ! is_a( $checkout->get_processor(), __CLASS__ ) ) {
			return;
		}
		wp_redirect( $checkout->checkout_confirmation_url( self::PAYMENT_SLUG ) );
		exit();
	}

	/**
	 * Process a payment
	 *
	 * @param SI_Checkouts $checkout
	 * @param SI_Invoice $invoice
	 * @return SI_Payment|bool false if the payment failed, otherwise a Payment object
	 */
	public function process_payment( SI_Checkouts $checkout, SI_Invoice $invoice ) {
		$payment_amount = ( si_has_invoice_deposit( $invoice->get_id() ) ) ? $invoice->get_deposit() : $invoice->get_balance();
		// create new payment
		$payment_id = SI_Payment::new_payment( array(
			'payment_method' => self::get_payment_method(),
			'invoice' => $invoice->get_id(),
			'amount' => si_get_number_format( $payment_amount ),
			'data' => array(
			'live' => ( ! self::SANDBOX ),
			'api_response' => wp_parse_args( $_GET ),
			'payment_token' => self::get_token( $invoice->get_id() ),
			),
		), SI_Payment::STATUS_AUTHORIZED );
		if ( ! $payment_id ) {
			return false;
		}
		$payment = SI_Payment::get_instance( $payment_id );
		do_action( 'payment_authorized', $payment );

		self::unset_token( $invoice->get_id() );
		return $payment;
	}

	/**
	 * Facility to capture a purchase manually.
	 *
	 * @param SI_Payment $payment
	 * @return void
	 */
	public function manually_capture_purchase( SI_Payment $payment ) {
		$this->capture_payment( $payment );
	}

	public function capture_payment_after_auth( SI_Payment $payment ) {
		$this->capture_payment( $payment );
	}

	public function capture_payment( SI_Payment $payment ) {
		// is this the right payment processor? does the payment still need processing?
		if ( $payment->get_payment_method() !== $this->get_payment_method() || $payment->get_status() === SI_Payment::STATUS_COMPLETE ) {
			return;
		}

		$data = $payment->get_data();
		if ( ! isset( $data['api_response'] ) ) {
			return false;
		}

		$payment_data = $data['api_response'];
		// Do we have a transaction ID to use for the capture?
		if ( isset( $payment_data['preference_id'] ) ) {

			$order_status = self::get_mp_payment_status( $payment_data );

			// If approved than complete the payment
			if ( 'approved' !== $order_status['status'] ) {
				self::set_error_messages( 'maybe complete payment id: '.print_r( $order_status, true ), false );
			}

			$this->complete_payment( $payment );

			// Add the status response to the data of the payment
			if ( ! isset( $data['payment_status_response'] ) || is_array( $data['payment_status_response'] ) ) {
				$data['payment_status_response'] = array();
			}
			$data['payment_status_response'] = $order_status;
			$payment->set_data( $data );

		}
	}

	/**
	 * Complete the payment
	 */
	public function complete_payment( SI_Payment $payment ) {
		$payment->set_status( SI_Payment::STATUS_COMPLETE );
		do_action( 'payment_complete', $payment );
	}

	//////////////
	// Utility //
	//////////////

	public static function get_mp_payment_status( $data = array() ) {
		$order_status = false;
		// use id if possible
		if ( isset( $data['collection_id'] ) && '' !== $data['collection_id'] ) {
			$order_status = self::get_mp_payment_by_id( $data['collection_id'] );
		}
		// fallback
		if ( ! $order_status ) {
			$order_status = self::get_status_by_preference_id( $data['preference_id'] );
		}
		if ( self::DEBUG ) {
			self::set_error_messages( 'payment status check: '.print_r( $order_status, true ), false );
		}
		if ( empty( $order_status ) ) {
			return false;
		}
		return $order_status;
	}

	public static function get_status_by_preference_id( $id = 0 ) {
		if ( ! $id ) {
			$id = self::get_token();
		}

		// Sets the filters you want
		$filters = array(
			'site_id' => self::$site_id, // Argentina: MLA; Brasil: MLB
			'preference_id' => $id,
		);

		// Search payment data according to filters
		$mp = self::get_mp();
		$search_result = $mp->search_payment( $filters );
		if ( ! isset( $search_result['response']['results'][0]['collection'] ) ) {
			return false;
		}
		return $search_result['response']['results'][0]['collection'];
	}

	public static function get_mp_payment_by_id( $id = 0 ) {
		if ( ! $id ) {
			$id = self::get_token();
		}

		// Search payment data according to filters
		$mp = self::get_mp();
		$payment_info = $mp->get_payment( $id );
		if ( ! isset( $payment_info['response']['collection'] ) ) {
			return false;
		}
		return $payment_info['response']['collection'];
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
	private static function set_error_messages( $message, $display = true ) {
		if ( $display ) {
			self::set_message( $message, self::MESSAGE_STATUS_ERROR );
		} else {
			do_action( 'si_error', __CLASS__ . '::' . __FUNCTION__ . ' - error message from mercadopago', $message );
		}
	}
}
SI_Mercadopago::register();
