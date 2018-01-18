<?php

class SA_PagSeguro extends SI_Offsite_Processors {
	const MODE_TEST = 'test';
	const MODE_LIVE = 'live';

	const API_EMAIL_OPTION = 'gb_pagseguro_email';
	const API_TOKEN_OPTION = 'gb_pagseguro_token';

	const API_MODE_OPTION = 'si_pagseguro_mode';
	const PAYMENT_METHOD = 'PagSeguro';
	const PAYMENT_SLUG = 'pagseguro';

	const PAYMENT_HANDLER_QUERY_ARG = 'pg_ipn_notification';

	protected static $instance;
	protected static $api_mode;
	private static $api_email;
	private static $api_token;

	// PG Specific
	const POST_TYPE = 'CP';
	const POST_CURRENCY = 'BRL';
	const POST_ENCODING = 'UTF-8'; //ISO-8859-1
	const DEBUG = true;
	var $_items = array();

	public static function get_instance() {
		if ( ! ( isset( self::$instance ) && is_a( self::$instance, __CLASS__ ) ) ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private static function is_test() {
		return get_option( self::API_MODE_OPTION, self::MODE_TEST ) == self::MODE_TEST;
	}

	public function get_payment_method() {
		return self::PAYMENT_METHOD;
	}

	public function get_slug() {
		return self::PAYMENT_SLUG;
	}

	public static function register() {
		// Register processor
		self::add_payment_processor( __CLASS__, __( 'PagSeguro' , 'sprout-invoices' ) );

		if ( ! self::is_active() ) {
			return;
		}

		add_action( 'si_head', array( __CLASS__, 'si_add_stylesheet' ), 100 );

		self::register_query_var( self::PAYMENT_HANDLER_QUERY_ARG, array( __CLASS__, 'handle_ipn' ) );

	}

	public static function public_name() {
		return __( 'PagSeguro' , 'sprout-invoices' );
	}

	public static function checkout_options() {
		$option = array(
			'icons' => array( SA_ADDON_PAGSEGURO_URL. '/resources/logo.png' ),
			'label' => __( 'PagSeguro' , 'sprout-invoices' ),
			'cc' => array(),
			);
		return $option;
	}

	protected function __construct() {
		parent::__construct();
		self::$api_mode = get_option( self::API_MODE_OPTION, self::MODE_TEST );

		self::$api_email = get_option( self::API_EMAIL_OPTION, 'vendedor@lojamodelo.com.br' );
		self::$api_token = get_option( self::API_TOKEN_OPTION, 'E231B2C9BCC8474DA2E260B6C8CF60D3' ); // E231B2C9BC

		if ( is_admin() ) {
			add_action( 'init', array( get_class(), 'register_options' ) );
		}

		add_action( 'si_checkout_action_'.SI_Checkouts::REVIEW_PAGE, array( $this, 'back_from_pg' ), 0, 1 );
		add_action( 'checkout_completed', array( $this, 'post_checkout_redirect' ), 10, 2 );
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
			'si_pagseguro_settings' => array(
				'title' => __( 'PagSeguro Settings' , 'sprout-invoices' ),
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
					self::API_EMAIL_OPTION => array(
						'label' => __( 'API Email' , 'sprout-invoices' ),
						'option' => array(
							'type' => 'text',
							'default' => self::$api_email,
							),
						),
					self::API_TOKEN_OPTION => array(
						'label' => __( 'API Token' , 'sprout-invoices' ),
						'option' => array(
							'type' => 'text',
							'default' => self::$api_token,
							),
						),
					'ipn' => array(
						'label' => __( 'Notificação de transação' , 'sprout-invoices' ),
						'option' => array(
							'type' => 'bypass',
							'output' => self::handler_url(),
							'description' => __( 'At this time payments are not validated via the IPN notification.' , 'sprout-invoices' ),
							),
						),
					),
				),
			);
		do_action( 'sprout_settings', $settings, self::SETTINGS_PAGE );
	}

	/**
	 * Loaded via SI_Payment_Processors::show_payments_pane
	 * @param  SI_Checkouts $checkout
	 * @return
	 */
	public function payments_pane( SI_Checkouts $checkout ) {
		$this->purchase_button( $checkout );
	}

	/**
	 * Loaded via SI_Payment_Processors::show_payments_pane
	 * @param  SI_Checkouts $checkout
	 * @return
	 */
	public function invoice_pane( SI_Checkouts $checkout ) {
		$this->purchase_button( $checkout );
	}

	public static function si_add_stylesheet() {
		if ( self::is_test() ) {
			echo '<script type="text/javascript" src="https://stc.sandbox.pagseguro.uol.com.br/pagseguro/api/v2/checkout/pagseguro.lightbox.js"></script>';
		} else {
			echo '<script type="text/javascript" src="https://stc.pagseguro.uol.com.br/pagseguro/api/v2/checkout/pagseguro.lightbox.js"></script>';
		}

	}

	public function purchase_button( SI_Checkouts $checkout ) {
		$lightbox_code = $this->get_lightbox_code( $checkout );
		if ( ! $lightbox_code ) {
			self::set_error_messages( 'Client Required for PagSeguro' );
			return;
		}
		?>
		<script type="text/javascript">
			jQuery(document).ready(function(){
				jQuery("#payment_selection #pagseguro a").on('click', function(e) {
					e.preventDefault();
					PagSeguroLightbox( '<?php echo $lightbox_code ?>' );	
				});
			});
		</script>
		<?php
	}

	public function get_lightbox_code( SI_Checkouts $checkout ) {
		$invoice = $checkout->get_invoice();
		$client = $invoice->get_client();
		if ( ! is_a( $client, 'SI_Client' ) ) {
			return 0;
		}
		$user = si_who_is_paying( $invoice );
		// User email or none
		$user_email = ( $user ) ? $user->user_email : '' ;

		$invoice = $checkout->get_invoice();

		// Instantiate a new payment request
		require_once 'lib/PagSeguroLibrary/PagSeguroLibrary.php';
		$paymentRequest = new PagSeguroPaymentRequest();
		// Set the currency
		$paymentRequest->setCurrency( 'BRL' );

		$paymentRequest->addItem( $invoice->get_id(), get_the_title( $invoice->get_id() ), 1, $invoice->get_balance() );

		$paymentRequest->setReference( $invoice->get_id() );
		$paymentRequest->setRedirectUrl( add_query_arg( array( 'back_from_pg' => 1 ), $checkout->checkout_complete_url( $this->get_slug() ) ) );

		// Set your customer information.
		$paymentRequest->setSender(
			get_the_title( $client->get_id() ),
			$user_email
		);

		try {
			$credentials = new PagSeguroAccountCredentials( self::$api_email, self::$api_token );

			return $paymentRequest->register( $credentials, true );

		} catch ( PagSeguroServiceException $e ) {
			self::set_error_messages( $e->getMessage() );
		}
	}

	/**
	 * Process a payment
	 *
	 * @param SI_Checkouts $checkout
	 * @param SI_Invoice $invoice
	 * @return SI_Payment|bool FALSE if the payment failed, otherwise a Payment object
	 */
	public function process_payment( SI_Checkouts $checkout, SI_Invoice $invoice ) {

		if ( ! self::returned_from_offsite() ) {
			return;
		}

		// todo Validate purchase?

		$payment_id = SI_Payment::new_payment( array(
			'payment_method' => $this->get_payment_method(),
			'invoice' => $invoice->get_id(),
			'amount' => $invoice->get_balance(), // todo
			'data' => array(
			'live' => ( self::$api_mode == self::MODE_LIVE ),
			'api_response' => $_REQUEST,
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

	public static function returned_from_offsite() {
		return ( isset( $_GET['back_from_pg'] ) && $_GET['back_from_pg'] == 1 );
	}

	/**
	 * We're on the checkout page, just back from PG.
	 * Store the token and payer ID that PayPal gives us
	 *
	 * @return void
	 */
	public function back_from_pg( SI_Checkouts $checkout ) {
		// Check to see if the payment processor being used is for this payment processor
		if ( ! is_a( $checkout->get_processor(), __CLASS__ ) ) { // FUTURE have parent class handle this smarter'r
			return;
		}
		if ( self::returned_from_offsite() ) {
			// Payment is complete
			$checkout->mark_page_complete( SI_Checkouts::PAYMENT_PAGE );
			// Skip the review page since that's already done at paypal.
			$checkout->mark_page_complete( SI_Checkouts::REVIEW_PAGE );
		}
	}

	public function post_checkout_redirect( SI_Checkouts $checkout, SI_Payment $payment ) {
		if ( ! is_a( $checkout->get_processor(), __CLASS__ ) ) {
			return;
		}
		wp_redirect( $checkout->checkout_confirmation_url( self::PAYMENT_SLUG ) );
		exit();
	}


	public function handle_ipn() {
		if ( ! isset( $_POST['StatusTransacao'] ) ) {
			return;
		}
		require_once 'lib/PagSeguroLibrary/PagSeguroLibrary.php';
		$code = (isset( $_POST['notificationCode'] ) && trim( $_POST['notificationCode'] ) !== '' ?
		trim( $_POST['notificationCode'] ) : null);
		$type = (isset( $_POST['notificationType'] ) && trim( $_POST['notificationType'] ) !== '' ?
		trim( $_POST['notificationType'] ) : null);

		if ( $code && $type ) {
			$notificationType = new PagSeguroNotificationType( $type );
			$strType = $notificationType->getTypeFromValue();
			switch ( $strType ) {
				case 'TRANSACTION':
					self::transactionNotification( $code );
					break;
				default:
					LogPagSeguro::error( 'Unknown notification type [' . $notificationType->getValue() . ']' );
			}
			self::printLog( $strType );
		} else {
			LogPagSeguro::error( 'Invalid notification parameters.' );
			self::printLog();
		}
		exit();
	}
	private static function transactionNotification( $notificationCode ) {
		$credentials = new PagSeguroAccountCredentials( get_option( self::API_EMAIL_OPTION ), get_option( self::API_TOKEN_OPTION ) );
		try {
			$transaction = PagSeguroNotificationService::checkTransaction( $credentials, $notificationCode );
			self::authorized_transation();
		} catch (PagSeguroServiceException $e) {
			die( $e->getMessage() );
		}
	}
	private static function printLog( $strType = null ) {
		$count = 4;
		echo '<h2>Receive notifications</h2>';
		if ( $strType ) {
			echo "<h4>notifcationType: $strType</h4>";
		}
		echo "<p>Last <strong>$count</strong> items in <strong>log file:</strong></p><hr>";
		echo LogPagSeguro::getHtml( $count );
	}

	public function authorized_transation() {
		$transactionStatus = $_POST['StatusTransacao'];
		$status_authorized = false;
		switch ( $transactionStatus ) {
			case 'Completo':
				// $result = "Payment complete";
				break;
			case 'Aguardando Pagto':
				// $result = "Waiting payment from the client";
				break;
			case 'Aprovado':
				$status_authorized = true;
				// $result = "Payment approved, waiting for compensation";
				break;
			case 'Em Análise':
				// $result = "Payment approved, under review by PagSeguro";
				break;
			case 'Cancelado':
				// $result = "Payment canceled by PagSeguro";
				break;
			default:
		}
		if ( $status_authorized ) {
			$invoice_id = $post['Referencia'];
			// todo find pending payment with same amount (based on item) and complete it.
		}
	}

	public static function handler_url() {
		return add_query_arg( array( self::PAYMENT_HANDLER_QUERY_ARG => 1 ), site_url() );
	}

	//////////////
	// Utility //
	//////////////

	private function convert_money_to_cents( $value ) {
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
			do_action( 'si_error', __CLASS__ . '::' . __FUNCTION__ . ' - error message from pagseguro', $message );
		}
	}
}
SA_PagSeguro::register();
