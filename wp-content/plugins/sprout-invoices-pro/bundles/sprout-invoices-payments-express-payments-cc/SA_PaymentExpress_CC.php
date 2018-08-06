<?php

/**
 * PaymentExpressCC onsite credit card payment processor.
 *
 * @package SI
 * @subpackage Payment SI_Credit_Card_Processors
 */
class SA_PaymentExpressCC extends SI_Credit_Card_Processors {
	const API_ENDPOINT_SANDBOX = 'https://sec.paymentexpress.com/pxpost.aspx';
	const API_ENDPOINT_LIVE = 'https://sec.paymentexpress.com/pxpost.aspx'; // Same url as sandbox at present
	const MODE_TEST = 'sandbox';
	const MODE_LIVE = 'live';
	const API_USERNAME_OPTION = 'si_px_post_username';
	const API_PASSWORD_OPTION = 'si_px_post_password';

	const TXN_TYPE_PURCHASE = 'Purchase';
	const TXN_TYPE_STATUS = 'Status';
	const TXN_PARENT_TAG = 'Txn';

	const API_MODE_OPTION = 'si_px_post_mode';
	const PAYMENT_METHOD = 'Credit (PaymentExpress CC)';
	const PAYMENT_SLUG = 'px_post';

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
		self::add_payment_processor( __CLASS__, __( 'PaymentExpress CC' , 'sprout-invoices' ) );
	}

	public static function public_name() {
		return __( 'Credit Card' , 'sprout-invoices' );
	}

	public static function checkout_options() {
		$option = array(
			'icons' => array(
				SI_URL . '/resources/front-end/img/visa.png',
				SI_URL . '/resources/front-end/img/mastercard.png',
				//SI_URL . '/resources/front-end/img/amex.png',
				// SI_URL . '/resources/front-end/img/discover.png' ),
			'label' => __( 'Credit Card' , 'sprout-invoices' ),
			'accepted_cards' => array(
				'visa',
				'mastercard',
				//'amex',
				// 'diners',
				// 'discover',
				// 'jcb',
				// 'maestro'
				),
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

		add_action( 'processed_payment', array( $this, 'capture_payment_after_auth' ), 10 );
		add_action( 'si_manually_capture_purchase', array( $this, 'manually_capture_purchase' ), 10 );
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
			'si_px_post_settings' => array(
				'title' => __( 'PaymentExpress CC' , 'sprout-invoices' ),
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

		$post_data = $this->px_request_data( $checkout, $invoice );
		$post_string = $this->array_to_xml( $post_data, self::TXN_PARENT_TAG );

		$response = wp_remote_post( $this->get_api_url(), array(
				'method' => 'POST',
			'body' => $post_string,
			'timeout' => apply_filters( 'http_request_timeout', 15 ),
			'sslverify' => false,
		));
		$response_body = wp_remote_retrieve_body( $response );

		$xml_response = ( isset( $response_body ) ) ? new SimpleXMLElement( $response_body ) : '';

		// PX Post requires a 'status' check where the result of the transaction could not be determined
		if ( is_wp_error( $response ) || ( $xml_response->Transaction->StatusRequired ) || ( $xml_response->Transaction->Retry ) ) {

			$status_post_string = $this->array_to_xml( $this->px_status_data( $post_data ), self::TXN_PARENT_TAG );

			$status_response = wp_remote_post( $this->get_api_url(), array(
	 			'method' => 'POST',
				'body' => $status_post_string,
				'timeout' => apply_filters( 'http_request_timeout', 15 ),
				'sslverify' => false,
			));
			if ( is_wp_error( $status_response ) ) {
				return false;
			} else {
				$xml_response = new SimpleXMLElement( $status_response['body'] );
			}
		}

		$response_code = $xml_response->Transaction->Authorized; // The response we want to validate on

		if ( $response_code != 1 ) {
			// Give useful error if possible, or general error if it's likely to be a site-wide config issue.
			$error_message = ( get_class( $xml_response->Transaction->CardHolderHelpText ) ) ? __( 'Payment failed - contact site owner' , 'sprout-invoices' ) : $xml_response->Transaction->CardHolderHelpText;
			$this->set_error_messages( $error_message );
			return false;
		}

		// Success

		$payment_id = SI_Payment::new_payment( array(
			'payment_method' => $this->get_payment_method(),
			'invoice' => $invoice->get_id(),
			'amount' => $post_data['Amount'],
			'data' => array(
			'live' => ( self::$api_mode == self::MODE_LIVE ),
			'api_response' => print_r( $xml_response, true ),
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
	 * Build the NVP data array for submitting the current checkout to PX as an Authorization request
	 */
	private function px_request_data( SI_Checkouts $checkout, SI_Invoice $invoice ) {
		$PXdata = array();
		$PXdata ['PostUsername'] = self::$api_username;
		$PXdata ['PostPassword'] = self::$api_password;
		$PXdata ['InputCurrency'] = strtoupper( self::get_currency_code() );
		$PXdata ['Amount'] = sprintf( '%0.2f', $invoice->get_balance() );
		$PXdata ['CardHolderName'] = $this->cc_cache['cc_name'];
		$PXdata ['CardNumber'] = $this->cc_cache['cc_number'];
		$PXdata ['DateExpiry'] = sprintf( '%02d', $this->cc_cache['cc_expiration_month'] ) . substr( $this->cc_cache['cc_expiration_year'], 2 );
		$PXdata ['Cvc2'] = $this->cc_cache['cc_cvv'];
		$PXdata ['TxnType'] = self::TXN_TYPE_PURCHASE;
		$PXdata ['TxnId'] = $invoice->get_id( ) . date( 'His' );
		$PXdata ['MerchantReference'] = $invoice->get_id( );
		$PXdata = apply_filters( 'si_pxpost_data', $PXdata );

		return $PXdata;
	}

	/**
	 * Build the data array for submitting a transaction status request to PX
	 * @param Array $post_data (must contain 'TxnId' used in original transaction)
	 * @return array
	 */
	private function px_status_data( $post_data ) {
		$PXdata = array();
		$PXdata ['PostUsername'] = self::$api_username;
		$PXdata ['PostPassword'] = self::$api_password;
		$PXdata ['TxnType'] = self::TXN_TYPE_STATUS;
		$PXdata ['TxnId'] = $post_data['TxnId'];
		$PXdata = apply_filters( 'si_pxpost_data', $PXdata );
		return $PXdata;
	}


	//////////////
	// Utility //
	//////////////



	/**
	 * Convert an array to xml.  One dimensional arrays only at this stage.  Key names are converted to tag names.
	 *
	 * NOTE: This is a general use function and should be moved out of this class.
	 *
	 * @param array $xml_array
	 * @param string $parent_tag_name (Elements in array will be wrapped by this tag)
	 * @return void
	 */
	public function array_to_xml( $elements_array, $parent_tag_name ) {
		if ( ! empty( $parent_tag_name ) && is_array( $elements_array ) ) {
			// Convert to well formed XML, array keys are the tag names
			foreach ( $elements_array as $key => $value ) {
				$post_string .= "<{$key}>" . htmlentities( $value, ENT_QUOTES, 'UTF-8' ) . "</{$key}>";
			}
			return "<{$parent_tag_name}>{$post_string}</{$parent_tag_name}>";
		}
		return false;
	}

	private function get_currency_code( $invoice_id ) {
		return apply_filters( 'si_currency_code', self::$currency_code, $invoice_id, self::PAYMENT_METHOD );
	}

	/**
	 * Grabs error messages from a PaymentExpressCC response and displays them to the user
	 *
	 * @param array   $response
	 * @param bool    $display
	 * @return void
	 */
	private function set_error_messages( $response, $display = true ) {
		if ( $display ) {
			self::set_message( $response, self::MESSAGE_STATUS_ERROR );
		} else {
			do_action( 'si_error', __CLASS__ . '::' . __FUNCTION__ . ' - PaymentExpress Error Response', $response );
		}
	}
}
SA_PaymentExpressCC::register();
