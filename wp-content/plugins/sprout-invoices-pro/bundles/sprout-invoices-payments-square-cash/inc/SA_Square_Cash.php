<?php

/**
 *
 * @package SI
 * @subpackage Payment SI_Offsite_Processors
 */
class SA_Square_Cash extends SI_Offsite_Processors {
	const MODE_LIVE = 'production';
	const CASHTAGOPTION = 'si_sc_cashtag';
	const PAYMENT_METHOD = 'Square Cash';
	const PAYMENT_SLUG = 'sc';

	protected static $instance;
	private static $cashtag = '';

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

	public static function register() {
		self::add_payment_processor( __CLASS__, __( 'Square Cash' , 'sprout-invoices' ) );
	}


	public static function public_name() {
		return __( 'Square Cash' , 'sprout-invoices' );
	}

	public static function checkout_options() {
		$option = array(
			'icons' => array(
				SI_URL . '/resources/front-end/img/dollar.png',
				SI_URL . '/resources/front-end/img/visa-debit.png',
				SI_URL . '/resources/front-end/img/mastercard.png',
				),
			'label' => __( 'Debit Card' , 'sprout-invoices' ),
			'accepted_cards' => array(
				'visa',
				'mastercard',
				//'amex',
				// 'diners',
				//'discover',
				// 'jcb',
				// 'maestro'
				),
			);
		return apply_filters( 'si_sp_checkout_options', $option );
	}

	protected function __construct() {
		parent::__construct();
		self::$cashtag = get_option( self::CASHTAGOPTION, '' );

		add_action( 'si_checkout_action_'.SI_Checkouts::PAYMENT_PAGE, array( $this, 'send_offsite' ), 0, 1 );

		// Remove pages
		add_filter( 'si_checkout_pages', array( $this, 'remove_checkout_pages' ) );
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
			'si_squarecash_settings' => array(
				'title' => __( 'Square Cash' , 'sprout-invoices' ),
				'weight' => 200,
				'settings' => array(

					self::CASHTAGOPTION => array(
						'label' => __( '$CashTag', 'sprout-invoices' ),
						'option' => array(
							'type' => 'text',
							'default' => self::$cashtag,
							'description' => __( 'Tag without the cash.me url. Example: $SproutApps', 'sprout-invoices' ),
							),
						),
					),
				),
			);
		return $settings;
	}

	/**
	 * Instead of redirecting to the SIcheckout page,
	 * set up the Express Checkout transaction and redirect there
	 *
	 * @param SI_Carts $cart
	 * @return void
	 */
	public function send_offsite( SI_Checkouts $checkout ) {
		// Check to see if the payment processor being used is for this payment processor
		if ( ! is_a( $checkout->get_processor(), __CLASS__ ) ) { // FUTURE have parent class handle this smarter'r
			return;
		}
		$invoice = $checkout->get_invoice();
		$payment_amount = ( si_has_invoice_deposit( $invoice->get_id() ) ) ? $invoice->get_deposit() : $invoice->get_balance();
		wp_redirect( 'https://cash.me/' . self::$cashtag . '/' . (float) $payment_amount );
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
		// Nothing, since the user is not returned.
	}
}
SA_Square_Cash::register();
