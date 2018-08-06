<?php

/**
 *
 * @package SI
 * @subpackage Payment SI_Offsite_Processors
 */
class SA_Payment_Redirect extends SI_Offsite_Processors {
	const MODE_LIVE = 'production';
	const REDIRECTTAGOPTION = 'si_redirect_url';
	const PAYMENT_METHOD = 'Redirect Payment';
	const PAYMENT_SLUG = 'redirect';

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


	//////////
	// Meta //
	//////////

	public static function get_doc_redirect_url( $doc_id = 0 ) {
		if ( ! $doc_id ) {
			$doc_id = get_the_ID();
		}
		return get_post_meta( $doc_id, self::REDIRECTTAGOPTION, true );
	}

	public static function set_doc_redirect_url( $doc_id = 0, $redirect_url = '' ) {
		if ( ! $doc_id ) {
			$doc_id = get_the_ID();
		}
		return update_post_meta( $doc_id, self::REDIRECTTAGOPTION, $redirect_url );
	}


	public static function register() {
		self::add_payment_processor( __CLASS__, __( 'Custom Redirect URL' , 'sprout-invoices' ) );

		if ( ! self::is_active() ) {
			return;
		}

		// meta boxes
		add_action( 'doc_information_meta_box_client_row_last', array( __CLASS__, 'meta_add_redirect_url' ) );
		add_action( 'si_save_line_items_meta_box', array( __CLASS__, 'save_redirect_url' ) );
	}


	/**
	 * Hooked on init add the settings page and options.
	 *
	 */
	public static function register_settings( $settings = array() ) {
		// Settings
		$settings['payments'] = array(
			'si_custom_url_settings' => array(
				'title' => __( 'Custom Payment URLs' , 'sprout-invoices' ),
				'description' => __( 'There are no settings for Custom Payment URLs here. Instead after activating you will enter the payment url on the invoice admin (within the information section).' , 'sprout-invoices' ),
				'weight' => 200,
			),
		);
		return $settings;
	}

	public static function meta_add_redirect_url( $doc ) {
		$fields = self::meta_box_fields( $doc );
		?>
			<div class="misc-pub-section" data-edit-id="redirect_url" data-edit-type="select">
				<span id="redirect_url" class="wp-media-buttons-icon"><b><?php _e( 'Payment Redirect', 'sprout-invoices' ) ?></b> <span title="<?php _e( 'Redirect client to this payment url.', 'sprout-invoices' ) ?>" class="helptip"></span></span>

					<a href="#edit_redirect_url" class="edit-redirect_url hide-if-no-js edit_control" >
						<span aria-hidden="true"><?php _e( 'Edit', 'sprout-invoices' ) ?></span> <span class="screen-reader-text"><?php _e( 'Redirect client to this payment url.', 'sprout-invoices' ) ?></span>
					</a>

					<div id="redirect_url_div" class="control_wrap hide-if-js">
						<div class="redirect_url-wrap">
							<?php sa_admin_fields( $fields, 'redirect_url' ); ?>
				 		</div>
				 	</div>
			</div>

		<?php
	}


	public static function meta_box_fields( $doc ) {
		$fields = array();
		$fields['payment_redirect'] = array(
			'weight' => 10,
			'placeholder' => __( 'Payment Redirect', 'sprout-invoices' ),
			'type' => 'input',
			'default' => self::get_doc_redirect_url( $doc->get_id() ),
			'attributes' => array( 'class' => 'small-input' ),
		);
		$fields = apply_filters( 'si_redirect_url_meta_fields', $fields );
		uasort( $fields, array( __CLASS__, 'sort_by_weight' ) );
		return $fields;
	}

	public static function save_redirect_url( $post_id = 0 ) {
		$doc_redirect_url = ( isset( $_POST['sa_redirect_url_payment_redirect'] ) ) ? sanitize_text_field( $_POST['sa_redirect_url_payment_redirect'] ) : '' ;
		self::set_doc_redirect_url( $post_id, $doc_redirect_url );
	}


	public static function public_name() {
		return __( 'Payment' , 'sprout-invoices' );
	}

	public static function checkout_options() {
		$option = array(
			'icons' => array(
				SI_URL . '/resources/front-end/img/dollar.png',
				//SI_URL . '/resources/front-end/img/visa-debit.png',
				//SI_URL . '/resources/front-end/img/mastercard.png',
				),
			'label' => __( 'Payment' , 'sprout-invoices' ),
			'accepted_cards' => array(
				'visa',
				'mastercard',
				'amex',
				// 'diners',
				//'discover',
				// 'jcb',
				// 'maestro'
				),
			);
		return apply_filters( 'si_payredirect_checkout_options', $option );
	}

	protected function __construct() {
		parent::__construct();
		self::$cashtag = get_option( self::REDIRECTTAGOPTION, '' );

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

		$redirect_url = self::get_doc_redirect_url( $invoice->get_id() );
		if ( ! wp_http_validate_url( $redirect_url ) ) {
			self::set_error_messages( _e( 'Not a valid payment url.', 'sprout-invoices' ) );
			return;
		}

		$invoice = $checkout->get_invoice();

		// create new payment
		$payment_id = SI_Payment::new_payment( array(
			'payment_method' => self::get_payment_method(),
			'invoice' => $invoice->get_id(),
			'amount' => $invoice->get_balance(),
			'data' => array(
			'redirect_url' => $redirect_url,
			),
		), SI_Payment::STATUS_PENDING );
		if ( ! $payment_id ) {
			return false;
		}

		wp_redirect( $redirect_url );
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
SA_Payment_Redirect::register();
