<?php

/**
 * Authorize.net onsite credit card payment processor.
 *
 * @package SI
 * @subpackage Payment SI_Offsite_Processors
 */
class SA_2Checkout extends SI_Offsite_Processors {
	const API_ENDPOINT_SANDBOX = 'https://sandbox.2checkout.com/checkout/purchase';
	const API_ENDPOINT_LIVE = 'https://www.2checkout.com/checkout/purchase';
	const MODE_TEST = 'sandbox';
	const MODE_LIVE = 'live';
	const API_API_KEY_OPTION = 'si_twoco_sid';
	const API_ID_OPTION = 'si_twoco_username';
	const API_SECRET_WORD_OPTION = 'si_twoco_password';

	const API_MODE_OPTION = 'si_twoco_mode';
	const PAYMENT_METHOD = 'Credit (2CO)';
	const PAYMENT_SLUG = 'twoco';

	protected static $instance;
	private static $client_sdk;
	private static $api_mode = self::MODE_TEST;
	private static $public_sid = '';
	private static $sid = '';
	private static $secret_word = '';
	private static $currency_code = 'USD';

	public static function get_instance() {
		if ( ! ( isset( self::$instance ) && is_a( self::$instance, __CLASS__ ) ) ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private static function get_api_url() {
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

	public static function returned_from_offsite() {
		return isset( $_GET['twoco_payment'] ) && $_GET['twoco_payment'] == 1;
	}

	public static function register() {
		self::add_payment_processor( __CLASS__, __( '2CO' , 'sprout-invoices' ) );
	}


	public static function public_name() {
		return __( 'Credit Card' , 'sprout-invoices' );
	}

	public static function checkout_options() {
		$option = array(
			'label' => __( '2CO' , 'sprout-invoices' ),
			'cc' => array(),
			'purchase_button_callback' => array( __CLASS__, 'payment_button' ),
			);
		return apply_filters( 'si_twoco_checkout_options', $option );
	}

	protected function __construct() {
		parent::__construct();
		self::$public_sid = get_option( self::API_API_KEY_OPTION, '' );
		self::$sid = get_option( self::API_ID_OPTION, '' );
		self::$secret_word = get_option( self::API_SECRET_WORD_OPTION, '' );
		self::$api_mode = get_option( self::API_MODE_OPTION, self::MODE_TEST );

		if ( is_admin() ) {
			add_action( 'init', array( get_class(), 'register_options' ) );
		}

		add_action( 'si_checkout_action_'.SI_Checkouts::REVIEW_PAGE, array( $this, 'back_from_twoco' ), 0, 1 );

		add_action( 'checkout_completed', array( $this, 'post_checkout_redirect' ), 10, 2 );
	}

	/**
	 * Hooked on init add the settings page and options.
	 *
	 */
	public static function register_options() {
		// Settings
		$settings = array(
			'si_twoco_settings' => array(
				'title' => __( '2Checkout' , 'sprout-invoices' ),
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
					self::API_ID_OPTION => array(
						'label' => __( '2Checkout Account Number' , 'sprout-invoices' ),
						'option' => array(
							'type' => 'text',
							'default' => get_option( self::API_ID_OPTION, '' ),
							),
						),
					self::API_SECRET_WORD_OPTION => array(
						'label' => __( 'Secret Word' , 'sprout-invoices' ),
						'option' => array(
							'type' => 'text',
							'default' => get_option( self::API_SECRET_WORD_OPTION, '' ),
							'description' => __( 'The secret word is found on the Site Management page', 'sprout-invoices' ),
							),
						),
					),
				),
			);
		do_action( 'sprout_settings', $settings, self::SETTINGS_PAGE );
	}

	public static function payment_button( $invoice_id = 0 ) {
		if ( ! $invoice_id ) {
			$invoice_id = get_the_id();
		}
		$invoice = SI_Invoice::get_instance( $invoice_id );
		$payment_amount = ( si_has_invoice_deposit( $invoice->get_id() ) ) ? (float) $invoice->get_deposit() : (float) $invoice->get_balance();
		$user = si_who_is_paying( $invoice );
		$user_email = ( $user ) ? $user->user_email : '' ;

		$checkout = SI_Checkouts::get_instance();
		$checkout_complete_url = $checkout->checkout_complete_url( self::PAYMENT_SLUG )
		?>
			 <style type="text/css">
				.twoco_button {
					background: url('https://www.2checkout.com/upload/images/paymentlogoshorizontal.png') no-repeat;
					cursor: pointer;
					border: none;
					width: 360px;
					height: 55px;
					text-indent: -460px;
					overflow: hidden;
				}
			</style>
			<script src="https://www.2checkout.com/static/checkout/javascript/direct.min.js"></script>
			<form action='<?php echo self::get_api_url() ?>' method='post'>
				<input type='hidden' name='sid' value='<?php echo self::$sid ?>' />
				<input type='hidden' name='mode' value='2CO' />
				<input type='hidden' name='currency_code' value='<?php echo self::get_currency_code( $invoice->get_id() ) ?>' />
				<input type='hidden' name='li_0_type' value='product' />
				<input type='hidden' name='li_0_name' value='<?php echo $invoice->get_invoice_id() ?>' />
				<input type='hidden' name='li_0_price' value='<?php echo round( $payment_amount, 2 ) ?>' />
				<input type='hidden' name='li_0_description' value='<?php echo $invoice->get_title() ?>' />
				<input type='hidden' name='email' value='<?php echo $user_email ?>' />
				<input type='hidden' name='x_receipt_link_url' value='<?php echo add_query_arg( array( 'twoco_payment' => 1 ), $checkout_complete_url ) ?>' />
				<button class="twoco_button"><?php _e( 'Checkout', 'sprout-invoices' ) ?></button>
			</form>
		<?php
	}

	public function back_from_twoco( SI_Checkouts $checkout ) {
		// Check to see if the payment processor being used is for this payment processor
		if ( ! is_a( $checkout->get_processor(), __CLASS__ ) ) { // FUTURE have parent class handle this smarter'r
			return;
		}
		if ( self::returned_from_offsite() ) {
			$invoice = $checkout->get_invoice();
			$payment_amount = ( si_has_invoice_deposit( $invoice->get_id() ) ) ? (float) $invoice->get_deposit() : (float) $invoice->get_balance();

			if ( ! isset( $_REQUEST['key'] ) && '' !== $_REQUEST['key'] ) {
				self::set_error_messages( 'Your Payment has been cancelled, refunded or rejected. Code: Key Missing', true );
			}

			if ( ! isset( $_REQUEST['order_number'] ) && '' !== $_REQUEST['order_number'] ) {
				self::set_error_messages( 'Your Payment has been cancelled, refunded or rejected. Code: Ordernumber missing', true );
			}

			self::complete_checkout_pages( $checkout );
		}
	}

	public function post_checkout_redirect( SI_Checkouts $checkout, SI_Payment $payment ) {
		if ( ! is_a( $checkout->get_processor(), __CLASS__ ) ) {
			return;
		}
		$access_code = ( isset( $_REQUEST['key'] ) ) ? $_REQUEST['key'] : '' ;

		wp_redirect( add_query_arg( array( 'key' => $access_code ), $checkout->checkout_confirmation_url( self::PAYMENT_SLUG ) ) );
		exit();
	}


	public static function complete_checkout_pages( SI_Checkouts $checkout ) {
		// Payment is complete
		$checkout->mark_page_complete( SI_Checkouts::PAYMENT_PAGE );
		// Skip the review page since that's already done at paypal.
		$checkout->mark_page_complete( SI_Checkouts::REVIEW_PAGE );
	}

	/**
	 * Process a payment
	 *
	 * @param SI_Checkouts $checkout
	 * @param SI_Invoice $invoice
	 * @return SI_Payment|bool FALSE if the payment failed, otherwise a Payment object
	 */
	public function process_payment( SI_Checkouts $checkout, SI_Invoice $invoice ) {

		$payment_amount = ( si_has_invoice_deposit( $invoice->get_id() ) ) ? $invoice->get_deposit() : $invoice->get_balance();
		$payment_id = SI_Payment::new_payment( array(
			'payment_method' => $this->get_payment_method(),
			'invoice' => $invoice->get_id(),
			'amount' => $payment_amount,
			'data' => array(
			'live' => ( self::$api_mode == self::MODE_LIVE ),
			'key' => ( isset( $_GET['key'] ) ) ? $_GET['key'] : '',
			),
		), SI_Payment::STATUS_AUTHORIZED );
		if ( ! $payment_id ) {
			return false;
		}

		// Go through the routine and do the authorized actions and then complete.
		$payment = SI_Payment::get_instance( $payment_id );
		do_action( 'payment_authorized', $payment );

		if ( self::$api_mode == self::MODE_LIVE ) {
			$strtohash = strtoupper( md5( self::$secret_word . self::$sid . $_REQUEST['order_number'] . $_REQUEST['total'] ) );
		} else {
			$strtohash = strtoupper( md5( self::$secret_word . self::$sid . 1 . round( $payment_amount, 2 ) ) );
		}

		if ( $strtohash !== $_REQUEST['key'] ) {
			self::set_error_messages( 'Your Payment is Pending.', true );
			$payment->set_status( SI_Payment::STATUS_PENDING );
			do_action( 'payment_pending', $payment );
		} else {
			$payment->set_status( SI_Payment::STATUS_COMPLETE );
			do_action( 'payment_complete', $payment );
		}

		return $payment;
	}


	//////////////
	// Utility //
	//////////////



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
}
SA_2Checkout::register();
