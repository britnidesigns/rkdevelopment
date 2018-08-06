<?php

/**
 * Authorize.net onsite credit card payment processor.
 *
 * @package SI
 * @subpackage Payment SI_Offsite_Processors
 */
class SA_eWAY extends SI_Offsite_Processors {
	const MODE_TEST = 'sandbox';
	const MODE_LIVE = 'live';
	const API_API_KEY_OPTION = 'si_eway_api_key';
	const API_USERNAME_OPTION = 'si_eway_username';
	const API_PASSWORD_OPTION = 'si_eway_password';

	const API_MODE_OPTION = 'si_eway_mode';
	const PAYMENT_METHOD = 'Credit (eWay)';
	const PAYMENT_SLUG = 'eway';

	protected static $instance;
	private static $client_sdk;
	private static $api_mode = self::MODE_TEST;
	private static $public_api_key = '';
	private static $api_key = '';
	private static $api_password = '';
	private static $currency_code = 'AUD';

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

	public static function returned_from_offsite() {
		return isset( $_GET['eway_payment'] ) && $_GET['eway_payment'] == 1;
	}

	public static function register() {
		self::add_payment_processor( __CLASS__, __( 'eWay' , 'sprout-invoices' ) );
	}


	public static function public_name() {
		return __( 'Credit Card' , 'sprout-invoices' );
	}

	public static function checkout_options() {
		$option = array(
			'label' => __( 'eWay' , 'sprout-invoices' ),
			'cc' => array(),
			'purchase_button_callback' => array( __CLASS__, 'payment_button' ),
			);
		return apply_filters( 'si_eway_checkout_options', $option );
	}

	protected function __construct() {
		parent::__construct();
		self::$public_api_key = get_option( self::API_API_KEY_OPTION, '' );
		self::$api_key = get_option( self::API_USERNAME_OPTION, '' );
		self::$api_password = get_option( self::API_PASSWORD_OPTION, '' );
		self::$api_mode = get_option( self::API_MODE_OPTION, self::MODE_TEST );

		add_action( 'si_checkout_action_'.SI_Checkouts::REVIEW_PAGE, array( $this, 'back_from_eway' ), 0, 1 );

		add_action( 'checkout_completed', array( $this, 'post_checkout_redirect' ), 10, 2 );
	}

	public static function init_eway_sdk_client() {
		$api_mode_ep = \Eway\Rapid\Client::MODE_SANDBOX;
		if ( self::MODE_LIVE === self::$api_mode ) {
			$api_mode_ep = \Eway\Rapid\Client::MODE_PRODUCTION;
		}
		self::$client_sdk = \Eway\Rapid::createClient( self::$api_key, self::$api_password, $api_mode_ep );
	}

	/**
	 * Hooked on init add the settings page and options.
	 *
	 */
	public static function register_settings( $settings = array() ) {
		// Settings
		$settings['payments'] = array(
			'si_eway_settings' => array(
				'title' => __( 'eWay' , 'sprout-invoices' ),
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
					self::API_API_KEY_OPTION => array(
						'label' => __( 'Pay Now Public API Key' , 'sprout-invoices' ),
						'option' => array(
							'type' => 'text',
							'default' => get_option( self::API_API_KEY_OPTION, 'epk-....' ),
							),
						),
					self::API_USERNAME_OPTION => array(
						'label' => __( 'API Key' , 'sprout-invoices' ),
						'option' => array(
							'type' => 'text',
							'default' => get_option( self::API_USERNAME_OPTION, '' ),
							),
						),
					self::API_PASSWORD_OPTION => array(
						'label' => __( 'API Password' , 'sprout-invoices' ),
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

	public static function payment_button( $invoice_id = 0 ) {
		if ( ! $invoice_id ) {
			$invoice_id = get_the_id();
		}
		$invoice = SI_Invoice::get_instance( $invoice_id );
		$payment_amount = ( si_has_invoice_deposit( $invoice->get_id() ) ) ? $invoice->get_deposit() : $invoice->get_balance();
		$user = si_who_is_paying( $invoice );
		$user_email = ( $user ) ? $user->user_email : '' ;

		$checkout = SI_Checkouts::get_instance();
		$checkout_complete_url = $checkout->checkout_complete_url( self::PAYMENT_SLUG )
		?>
			<style type="text/css">
				li#eway {
				    text-align: right;
				    margin: 10px;
				}

				div#payment_selection {
				    z-index: 9999;
				}
			</style>
			<script src="https://secure.ewaypayments.com/scripts/eCrypt.js"
				class="eway-paynow-button"
				data-publicapikey="<?php echo self::$public_api_key ?>"
				data-amount="<?php echo self::convert_money_to_cents( $payment_amount ) ?>"
				data-currency="<?php echo self::get_currency_code( $invoice->get_id() ) ?>"
				data-invoiceref="<?php echo $invoice->get_invoice_id() ?>"
				data-invoicedescription="<?php echo $invoice->get_title() ?>"
				data-email="<?php echo $user_email ?>"
				data-allowedit="true"
				data-resulturl="<?php echo add_query_arg( array( 'eway_payment' => 1 ), $checkout_complete_url ) ?>" >
			</script>
		<?php
	}

	/**
	 * Not necessary with public button
	 * @param  integer $invoice_id [description]
	 * @return [type]              [description]
	 */
	public static function get_shared_payment_url( $invoice_id = 0 ) {
		$invoice = SI_Invoice::get_instance( $invoice_id );

		$user = si_who_is_paying( $invoice );
		$user_email = ( $user ) ? $user->user_email : '' ;

		$payment_amount = ( si_has_invoice_deposit( $invoice->get_id() ) ) ? $invoice->get_deposit() : $invoice->get_balance();

		$post_data = array(
			'Customer' => array(
				'Reference' => $invoice->get_client_id(),
				'Email' => $user_email,
			),
			'Payment' => array(
				'TotalAmount' => self::convert_money_to_cents( $payment_amount ),
				'InvoiceNumber' => $invoice->get_invoice_id(),
				'InvoiceDescription' => $invoice->get_title(),
				'InvoiceReference' => $invoice_id,
				'CurrencyCode' => self::get_currency_code( $invoice->get_id() ),
			),
			'RedirectUrl' => get_permalink( $invoice_id ),
			'CancelUrl' => get_permalink( $invoice_id ),
			'TransactionType' => 'purchase',
			'Capture' => true,
			'CompleteCheckoutURL' => add_query_arg( array( 'eway_payment' => 1 ), $checkout_complete_url ),
		);

		$params = array(
			'method' => 'POST',
			'headers' => array(
					'Content-type' => 'application/json',
					'Authorization' => 'Basic ' . base64_encode( self::$api_key . ':' . self::$api_password ),
				),
			'body' => wp_json_encode( $post_data ),
			'httpversion' => '1.1',
			'timeout' => apply_filters( 'http_request_timeout', 15 ),
			'sslverify' => false,
		);
		$request_response = wp_remote_request( 'https://api.sandbox.ewaypayments.com/AccessCodesShared/', $params );
		$api_response = json_decode( wp_remote_retrieve_body( $request_response ) );

		if ( ! is_null( $api_response->Errors ) ) {
			$errors = explode( ',',  $api_response->Errors );
			if ( is_array( $errors ) ) {
				foreach ( $errors as $error ) {
					self::set_error_messages( self::api_code_reference( $error ) );
				}
				return;
			}
		}

		$payments_url = $api_response->SharedPaymentUrl;
		return $payments_url;
	}

	public function back_from_eway( SI_Checkouts $checkout ) {
		// Check to see if the payment processor being used is for this payment processor
		if ( ! is_a( $checkout->get_processor(), __CLASS__ ) ) { // FUTURE have parent class handle this smarter'r
			return;
		}
		if ( self::returned_from_offsite() ) {
			$invoice = $checkout->get_invoice();

			if ( ! isset( $_GET['AccessCode'] ) && '' !== $_GET['AccessCode'] ) {
				self::set_error_messages( sprintf( 'Your Payment has been cancelled, refunded or rejected. Code: %s', $order_status['status_detail'] ), true );
			}

			$is_valid = self::validate_access_code( $_GET['AccessCode'] );

			if ( ! $is_valid ) {
				return;
			}

			self::complete_checkout_pages( $checkout );
		}
	}

	public static function validate_access_code( $access_code = '' ) {
		$params = array(
			'method' => 'GET',
			'headers' => array(
					'Content-type' => 'application/json',
					'Authorization' => 'Basic ' . base64_encode( self::$api_key . ':' . self::$api_password ),
				),
			'httpversion' => '1.1',
			'timeout' => apply_filters( 'http_request_timeout', 15 ),
			'sslverify' => false,
		);
		$request_response = wp_remote_request( 'https://api.sandbox.ewaypayments.com/Transaction/' . $access_code, $params );
		$api_response = json_decode( wp_remote_retrieve_body( $request_response ) );

		$response_code = $api_response->Transactions[0]->ResponseMessage;

		if ( in_array( $response_code, array( 'A2000', 'A2008', 'A2010', 'A2011', 'A2016' ) ) ) {
			return true;
		}

		$error_message = self::api_code_reference( $response_code );
		self::set_error_messages( $error_message, true );
		return false;
	}

	public function post_checkout_redirect( SI_Checkouts $checkout, SI_Payment $payment ) {
		if ( ! is_a( $checkout->get_processor(), __CLASS__ ) ) {
			return;
		}
		$access_code = ( isset( $_GET['AccessCode'] ) ) ? $_GET['AccessCode'] : '' ;

		wp_redirect( add_query_arg( array( 'AccessCode' => $access_code ), $checkout->checkout_confirmation_url( self::PAYMENT_SLUG ) ) );
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
			'AccessCode' => ( isset( $_GET['AccessCode'] ) ) ? $_GET['AccessCode'] : '',
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

	public static function api_code_reference( $code = '' ) {
		$codes = array(
			'A2000' => 'Transaction Approved',
			'A2008' => 'Honour With Identification',
			'A2010' => 'Approved For Partial Amount',
			'A2011' => 'Approved, VIP',
			'A2016' => 'Approved, Update Track 3',
			'D4401' => 'Refer to Issuer',
			'D4402' => 'Refer to Issuer, special',
			'D4403' => 'No Merchant',
			'D4404' => 'Pick Up Card',
			'D4405' => 'Do Not Honour',
			'D4406' => 'Error',
			'D4407' => 'Pick Up Card, Special',
			'D4409' => 'Request In Progress',
			'D4412' => 'Invalid Transaction',
			'D4413' => 'Invalid Amount',
			'D4414' => 'Invalid Card Number',
			'D4415' => 'No Issuer',
			'D4419' => 'Re-enter Last Transaction',
			'D4421' => 'No Action Taken',
			'D4422' => 'Suspected Malfunction',
			'D4423' => 'Unacceptable Transaction Fee',
			'D4425' => 'Unable to Locate Record On File',
			'D4430' => 'Format Error',
			'D4431' => 'Bank Not Supported By Switch',
			'D4433' => 'Expired Card, Capture',
			'D4434' => 'Suspected Fraud, Retain Card',
			'D4435' => 'Card Acceptor, Contact Acquirer, Retain Card',
			'D4436' => 'Restricted Card, Retain Card',
			'D4437' => 'Contact Acquirer Security Department, Retain Card',
			'D4438' => 'PIN Tries Exceeded, Capture',
			'D4439' => 'No Credit Account',
			'D4440' => 'Function Not Supported',
			'D4441' => 'Lost Card',
			'D4442' => 'No Universal Account',
			'D4443' => 'Stolen Card',
			'D4444' => 'No Investment Account',
			'D4450' => 'Visa Checkout Transaction Error',
			'D4451' => 'Insufficient Funds',
			'D4452' => 'No Cheque Account',
			'D4453' => 'No Savings Account',
			'D4454' => 'Expired Card',
			'D4455' => 'Incorrect PIN',
			'D4456' => 'No Card Record',
			'D4457' => 'Function Not Permitted to Cardholder',
			'D4458' => 'Function Not Permitted to Terminal',
			'D4459' => 'Suspected Fraud',
			'D4460' => 'Acceptor Contact Acquirer',
			'D4461' => 'Exceeds Withdrawal Limit',
			'D4462' => 'Restricted Card',
			'D4463' => 'Security Violation',
			'D4464' => 'Original Amount Incorrect',
			'D4466' => 'Acceptor Contact Acquirer, Security',
			'D4467' => 'Capture Card',
			'D4475' => 'PIN Tries Exceeded',
			'D4482' => 'CVV Validation Error',
			'D4490' => 'Cut off In Progress',
			'D4491' => 'Card Issuer Unavailable',
			'D4492' => 'Unable To Route Transaction',
			'D4493' => 'Cannot Complete, Violation Of The Law',
			'D4494' => 'Duplicate Transaction',
			'D4495' => 'Amex Declined',
			'D4496' => 'System Error',
			'D4497' => 'MasterPass Error',
			'D4498' => 'PayPal Create Transaction Error',
			'D4499' => 'Invalid Transaction for Auth/Void',
			'F7000' => 'Undefined Fraud Error',
			'F7001' => 'Challenged Fraud',
			'F7002' => 'Country Match Fraud',
			'F7003' => 'High Risk Country Fraud',
			'F7004' => 'Anonymous Proxy Fraud',
			'F7005' => 'Transparent Proxy Fraud',
			'F7006' => 'Free Email Fraud',
			'F7007' => 'International Transaction Fraud',
			'F7008' => 'Risk Score Fraud',
			'F7009' => 'Denied Fraud',
			'F7010' => 'Denied by PayPal Fraud Rules',
			'F9001' => 'Custom Fraud Rule',
			'F9010' => 'High Risk Billing Country',
			'F9011' => 'High Risk Credit Card Country',
			'F9012' => 'High Risk Customer IP Address',
			'F9013' => 'High Risk Email Address',
			'F9014' => 'High Risk Shipping Country',
			'F9015' => 'Multiple card numbers for single email address',
			'F9016' => 'Multiple card numbers for single location',
			'F9017' => 'Multiple email addresses for single card number',
			'F9018' => 'Multiple email addresses for single location',
			'F9019' => 'Multiple locations for single card number',
			'F9020' => 'Multiple locations for single email address',
			'F9021' => 'Suspicious Customer First Name',
			'F9022' => 'Suspicious Customer Last Name',
			'F9023' => 'Transaction Declined',
			'F9024' => 'Multiple transactions for same address with known credit card',
			'F9025' => 'Multiple transactions for same address with new credit card',
			'F9026' => 'Multiple transactions for same email with new credit card',
			'F9027' => 'Multiple transactions for same email with known credit card',
			'F9028' => 'Multiple transactions for new credit card',
			'F9029' => 'Multiple transactions for known credit card',
			'F9030' => 'Multiple transactions for same email address',
			'F9031' => 'Multiple transactions for same credit card',
			'F9032' => 'Invalid Customer Last Name',
			'F9033' => 'Invalid Billing Street',
			'F9034' => 'Invalid Shipping Street',
			'F9037' => 'Suspicious Customer Email Address',
			'F9050' => 'High Risk Email Address and amount',
			'F9113' => 'Card issuing country differs from IP address country',
			'S5000' => 'System Error',
			'S5011' => 'PayPal Connection Error',
			'S5012' => 'PayPal Settings Error',
			'S5085' => 'Started 3dSecure',
			'S5086' => 'Routed 3dSecure',
			'S5087' => 'Completed 3dSecure',
			'S5088' => 'PayPal Transaction Created',
			'S5099' => 'Incomplete (Access Code in progress/incomplete)',
			'S5010' => 'Unknown error returned by gateway',
			'V6000' => 'Validation error',
			'V6001' => 'Invalid CustomerIP',
			'V6002' => 'Invalid DeviceID',
			'V6003' => 'Invalid Request PartnerID',
			'V6004' => 'Invalid Request Method',
			'V6010' => 'Invalid TransactionType, account not certified for eCome only MOTO or Recurring available',
			'V6011' => 'Invalid Payment TotalAmount',
			'V6012' => 'Invalid Payment InvoiceDescription',
			'V6013' => 'Invalid Payment InvoiceNumber',
			'V6014' => 'Invalid Payment InvoiceReference',
			'V6015' => 'Invalid Payment CurrencyCode',
			'V6016' => 'Payment Required',
			'V6017' => 'Payment CurrencyCode Required',
			'V6018' => 'Unknown Payment CurrencyCode',
			'V6021' => 'Cardholder Name Required',
			'V6022' => 'Card Number Required',
			'V6023' => 'Card CVN Required',
			'V6033' => 'Invalid Expiry Date',
			'V6034' => 'Invalid Issue Number',
			'V6035' => 'Invalid Valid From Date',
			'V6040' => 'Invalid TokenCustomerID',
			'V6041' => 'Customer Required',
			'V6042' => 'Customer FirstName Required',
			'V6043' => 'Customer LastName Required',
			'V6044' => 'Customer CountryCode Required',
			'V6045' => 'Customer Title Required',
			'V6046' => 'TokenCustomerID Required',
			'V6047' => 'RedirectURL Required',
			'V6048' => 'CheckoutURL Required when CheckoutPayment specified',
			'V6049' => 'Invalid Checkout URL',
			'V6051' => 'Invalid Customer FirstName',
			'V6052' => 'Invalid Customer LastName',
			'V6053' => 'Invalid Customer CountryCode',
			'V6058' => 'Invalid Customer Title',
			'V6059' => 'Invalid RedirectURL',
			'V6060' => 'Invalid TokenCustomerID',
			'V6061' => 'Invalid Customer Reference',
			'V6062' => 'Invalid Customer CompanyName',
			'V6063' => 'Invalid Customer JobDescription',
			'V6064' => 'Invalid Customer Street1',
			'V6065' => 'Invalid Customer Street2',
			'V6066' => 'Invalid Customer City',
			'V6067' => 'Invalid Customer State',
			'V6068' => 'Invalid Customer PostalCode',
			'V6069' => 'Invalid Customer Email',
			'V6070' => 'Invalid Customer Phone',
			'V6071' => 'Invalid Customer Mobile',
			'V6072' => 'Invalid Customer Comments',
			'V6073' => 'Invalid Customer Fax',
			'V6074' => 'Invalid Customer URL',
			'V6075' => 'Invalid ShippingAddress FirstName',
			'V6076' => 'Invalid ShippingAddress LastName',
			'V6077' => 'Invalid ShippingAddress Street1',
			'V6078' => 'Invalid ShippingAddress Street2',
			'V6079' => 'Invalid ShippingAddress City',
			'V6080' => 'Invalid ShippingAddress State',
			'V6081' => 'Invalid ShippingAddress PostalCode',
			'V6082' => 'Invalid ShippingAddress Email',
			'V6083' => 'Invalid ShippingAddress Phone',
			'V6084' => 'Invalid ShippingAddress Country',
			'V6085' => 'Invalid ShippingAddress ShippingMethod',
			'V6086' => 'Invalid ShippingAddress Fax',
			'V6091' => 'Unknown Customer CountryCode',
			'V6092' => 'Unknown ShippingAddress CountryCode',
			'V6100' => 'Invalid EWAY_CARDNAME',
			'V6101' => 'Invalid EWAY_CARDEXPIRYMONTH',
			'V6102' => 'Invalid EWAY_CARDEXPIRYYEAR',
			'V6103' => 'Invalid EWAY_CARDSTARTMONTH',
			'V6104' => 'Invalid EWAY_CARDSTARTYEAR',
			'V6105' => 'Invalid EWAY_CARDISSUENUMBER',
			'V6106' => 'Invalid EWAY_CARDCVN',
			'V6107' => 'Invalid EWAY_ACCESSCODE',
			'V6108' => 'Invalid CustomerHostAddress',
			'V6109' => 'Invalid UserAgent',
			'V6110' => 'Invalid EWAY_CARDNUMBER',
			'V6111' => 'Unauthorised API Access, Account Not PCI Certified',
			'V6112' => 'Redundant card details other than expiry year and month',
			'V6113' => 'Invalid transaction for refund',
			'V6114' => 'Gateway validation error',
			'V6115' => 'Invalid DirectRefundRequest, Transaction ID',
			'V6116' => 'Invalid card data on original TransactionID',
			'V6117' => 'Invalid CreateAccessCodeSharedRequest, FooterText',
			'V6118' => 'Invalid CreateAccessCodeSharedRequest, HeaderText',
			'V6119' => 'Invalid CreateAccessCodeSharedRequest, Language',
			'V6120' => 'Invalid CreateAccessCodeSharedRequest, LogoUrl',
			'V6121' => 'Invalid TransactionSearch, Filter Match Type',
			'V6122' => 'Invalid TransactionSearch, Non numeric Transaction ID',
			'V6123' => 'Invalid TransactionSearch,no TransactionID or AccessCode specified',
			'V6124' => 'Invalid Line Items. The line items have been provided however the totals do not match the TotalAmount field',
			'V6125' => 'Selected Payment Type not enabled',
			'V6126' => 'Invalid encrypted card number, decryption failed',
			'V6127' => 'Invalid encrypted cvn, decryption failed',
			'V6128' => 'Invalid Method for Payment Type',
			'V6129' => 'Transaction has not been authorised for Capture/Cancellation',
			'V6130' => 'Generic customer information error',
			'V6131' => 'Generic shipping information error',
			'V6132' => 'Transaction has already been completed or voided, operation not permitted',
			'V6133' => 'Checkout not available for Payment Type',
			'V6134' => 'Invalid Auth Transaction ID for Capture/Void',
			'V6135' => 'PayPal Error Processing Refund',
			'V6140' => 'Merchant account is suspended',
			'V6141' => 'Invalid PayPal account details or API signature',
			'V6142' => 'Authorise not available for Bank/Branch',
			'V6150' => 'Invalid Refund Amount',
			'V6151' => 'Refund amount greater than original transaction',
			'V6152' => 'Original transaction already refunded for total amount',
			'V6153' => 'Card type not support by merchant',
			'V6160' => 'Encryption Method Not Supported',
			'V6165' => 'Invalid Visa Checkout data or decryption failed',
			'V6170' => 'Invalid TransactionSearch, Invoice Number is not unique',
			'V6171' => 'Invalid TransactionSearch, Invoice Number not found',
			'S9900' => 'eWAY library has encountered unknown exception',
			'S9901' => 'eWAY library has encountered invalid JSON response from server',
			'S9902' => 'eWAY library has encountered empty response from server',
			'S9903' => 'eWAY library has encountered unexpected method call',
			'S9904' => 'eWAY library has encountered invalid data provided to models',
			'S9990' => 'eWAY library does not have an endpoint initialised, or not initialise to a URL',
			'S9991' => 'eWAY library does not have API Key or password, or are invalid',
			'S9992' => 'eWAY library has encountered a problem connecting to Rapid',
			'S9993' => 'eWAY library has encountered an invalid API key or password',
			'S9995' => 'eWAY library has encountered invalid argument in method call',
			'S9996' => 'eWAY library has encountered an Rapid server error',
			);
		return $codes[ $code ];
	}
}
SA_eWAY::register();
