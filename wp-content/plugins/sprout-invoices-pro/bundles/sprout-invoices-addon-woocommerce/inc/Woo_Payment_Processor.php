<?php

class SI_Woo_Payment_Processor extends SI_Offsite_Processors {
	const PAYMENT_METHOD = 'WooCommerce Checkout';
	const PAYMENT_SLUG = 'woocheckout';
	const WOO_PRODUCT_ID_OPTION = 'woocheckout_product';

	protected static $instance;

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

		if ( ! function_exists( 'wc_attribute_taxonomy_name' ) ) {
			return;
		}

		self::add_payment_processor( __CLASS__, __( 'WooCommerce Checkout' , 'sprout-invoices' ) );

		if ( self::is_processor_enabled( __CLASS__ ) ) {
			add_action( 'admin_init', array( __CLASS__, 'maybe_create_woo_product' ) );
		}

		if ( self::is_processor_enabled( __CLASS__ ) ) {
			add_action( 'woocommerce_order_status_completed', array( __CLASS__, 'maybe_create_payment_for_woo_payment_completed' ) );
			add_action( 'woocommerce_thankyou', array( __CLASS__, 'redirect_to_invoice_after_payment' ) );
		}
	}

	public static function public_name() {
		return __( 'Checkout' , 'sprout-invoices' );
	}

	public static function checkout_options() {
		$option = array(
			'icons' => array(
				SI_URL . '/resources/front-end/img/paypal.png',
				SI_URL . '/resources/front-end/img/2checkout.png',
				SI_URL . '/resources/front-end/img/amazon.png',
				SI_URL . '/resources/front-end/img/google-checkout.png',
				SI_URL . '/resources/front-end/img/western-union.png',
				),
			'label' => __( 'Checkout' , 'sprout-invoices' ),
			'accepted_cards' => array(),
			);
		return apply_filters( 'si_woo_checkout_options', $option );
	}

	protected function __construct() {
		parent::__construct();

		// Remove pages
		add_filter( 'si_checkout_pages', array( $this, 'remove_checkout_pages' ) );

		add_action( 'si_checkout_action_'.SI_Checkouts::PAYMENT_PAGE, array( $this, 'send_woo_checkout' ), 0, 1 );

	}

	public static function maybe_create_woo_product() {
		$product_id = self::get_woo_product_id( false, true );

		if ( ! function_exists( 'wc_attribute_taxonomy_name' ) ) {
			return;
		}

		if ( ! taxonomy_exists( wc_attribute_taxonomy_name( 'si-invoice-id' ) ) ) {
			global $wpdb;

			$attribute_name 	= sanitize_title( esc_attr( 'si-invoice-id' ) );
			$attribute_type 	= esc_attr( 'text' );
			$attribute_label 	= esc_attr( 'Invoice ID' );

			$wpdb->insert(
				$wpdb->prefix . 'woocommerce_attribute_taxonomies',
				array(
					'attribute_name' 	=> $attribute_name,
					'attribute_label' 	=> $attribute_label,
					'attribute_type' 	=> $attribute_type,
				)
			);

			delete_transient( 'wc_attribute_taxonomies' );
		}

		return $product_id;
	}

	public static function get_woo_product_id( $deep_search = false, $create = false ) {
		$_product_id = get_option( self::WOO_PRODUCT_ID_OPTION, false );

		if ( 'product' === get_post_type( $_product_id ) ) {
			return $_product_id;
		}
		$product_id = 0;
		if ( ! $product_id && $deep_search ) {
			// query woo products
			$args = array(
				'post_type' => 'product',
				'post_status' => 'publish',
				'posts_per_page' => -1,
				'fields' => 'ids',
				'meta_query' => array(
				'relation' => 'AND',
				array(
					'key' => self::WOO_PRODUCT_ID_OPTION,
					'compare' => 'EXISTS',
					)
				),
			);
			$products = get_posts( apply_filters( 'si_get_woo_product_id', $args ) );
			if ( ! empty( $products ) ) {
				$product_id = array_pop( $products );
			}
		}
		if ( ! $product_id && $create ) {
			$product_id = self::create_woo_product_for_checkout();
		}
		update_option( self::WOO_PRODUCT_ID_OPTION, $product_id );
		return $product_id;
	}

	public static function create_woo_product_for_checkout() {
		$post = array(
			'post_author'  => 1,
			'post_content' => __( 'Invoice Payment', 'sprout-invoices' ),
			'post_status'  => 'publish',
			'post_title'   => __( 'Invoice Payment', 'sprout-invoices' ),
			'post_parent'  => '',
			'post_type'    => 'product',
		);

		$post_id = wp_insert_post( $post );

		if ( ! $post_id ) {
			return false;
		}

		if ( taxonomy_exists( 'si-invoice-id' ) ) {
			$attribute = array(
				'attribute_name' => 'si-invoice-id',
				'attribute_label' => 'Invoice ID',
				'attribute_type' => 'text',
				'attribute_orderby' => 'menu_order',
				'attribute_public' => false,
			);

			global $wpdb;
			$wpdb->insert( $wpdb->prefix . 'woocommerce_attribute_taxonomies', $attribute );
		}

		update_post_meta( $post_id, '_regular_price', 1.00 );

		update_post_meta( $post_id,'_visibility', 'hidden' ); // hidden product

		wp_set_object_terms( $post_id, 'variable', 'product_type' );

		// add meta for query
		add_post_meta( $post_id, self::WOO_PRODUCT_ID_OPTION, time() );

		return $post_id;
	}

	public static function add_product_variation_for_invoice_payment( SI_Invoice $invoice ) {

		$product_id = self::get_woo_product_id();
		$invoice_id = $invoice->get_id();
		$payment_amount = ( si_has_invoice_deposit( $invoice_id ) ) ? $invoice->get_deposit() : $invoice->get_balance();
		$attribute_name = 'pa_si-invoice-id';

		// update original product
		wp_set_object_terms( $product_id, array( $invoice_id ), $attribute_name );

		$product_attributes_data = array();
		$product_attributes_data[ $attribute_name ] = array(
			'name'         => $attribute_name,
			'value'        => '',
			'is_visible'   => '1',
			'is_variation' => '1',
			'is_taxonomy'  => '1',
		);

		update_post_meta( $product_id, '_product_attributes', $product_attributes_data ); // Attach the above array to the new posts meta data key '_product_attributes'

		$description = sprintf( 'Invoice <a href="%s">#%s</a>', get_permalink( $invoice_id ), $invoice_id );

		// create variation
		$variation_post = array( // Setup the post data for the variation

			'post_title'  => 'Invoice #'.$invoice_id,
			'post_name'   => 'product-'.$product_id.'-variation-'.$invoice_id,
			'post_content' => $description,
			'post_status' => 'publish',
			'post_parent' => $product_id,
			'post_type'   => 'product_variation',
			'guid'        => home_url() . '/?product_variation=product-' . $product_id . '-variation-' . $invoice_id,
		);

		$varient_id = wp_insert_post( $variation_post );

		// $attribute_term = get_term_by( 'name', $invoice_id, $attribute_name );
		// update_post_meta( $varient_id, 'attribute_'.$attribute_name, $attribute_term->slug );

		update_post_meta( $varient_id, '_description', $description ); // added variable description

		update_post_meta( $varient_id, '_price', (float) $payment_amount );
		update_post_meta( $varient_id, '_regular_price', (float) $payment_amount );
		update_post_meta( $varient_id, '_tax_class', apply_filters( 'si_woocommerce_varient_tax_rate', 'zero-rate' ) );
		update_post_meta( $varient_id, '_virtual', 'yes' );
		update_post_meta( $varient_id, '_invoice_id', $invoice_id );

		do_action( 'si_woo_varient_created', $varient_id, $invoice );
		return $varient_id;

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

	public static function maybe_adjust_price( $price, WC_Product $product ) {
		$checkout_product_id = (int) self::get_woo_product_id();
		if ( $checkout_product_id !== (int) $product->id ) {
			return $price;
		}
		return 0.01;
	}

	/**
	 * Instead of redirecting to the SIcheckout page,
	 * set up the Express Checkout transaction and redirect there
	 *
	 * @param SI_Carts $cart
	 * @return void
	 */
	public function send_woo_checkout( SI_Checkouts $checkout ) {

		if ( ! is_a( $checkout->get_processor(), __CLASS__ ) ) {
			return;
		}

		$invoice = $checkout->get_invoice();
		$invoice_id = $invoice->get_id();

		// Add product to cart
		$in_cart = false;
		if ( sizeof( WC()->cart->get_cart() ) > 0 ) {
			foreach ( WC()->cart->get_cart() as $cart_item_key => $values ) {
				if ( isset( $values['variation_id'] ) ) {
					$assoc_invoice_id = get_post_meta( $values['variation_id'], '_invoice_id', true );
					if ( (int) $assoc_invoice_id === (int) $invoice_id ) {
						$in_cart = true;
					}
				}
			}
		}

		$varient_id = self::add_product_variation_for_invoice_payment( $checkout->get_invoice() );
		$checkout_product_id = self::get_woo_product_id();

		if ( ! $in_cart ) {
			WC()->cart->add_to_cart( $checkout_product_id, 1, $varient_id );
		}

		// get the checkout url
		$checkout_url = wc_get_checkout_url();

		wp_redirect( $checkout_url );
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

	public static function maybe_create_payment_for_woo_payment_completed( $order_id ) {

		$order = wc_get_order( $order_id );

		$varient_id = 0;
		foreach ( $order->get_items() as $item ) {
			if ( isset( $item['variation_id'] ) ) {
				$varient_id = $item['variation_id'];
			}
		}

		if ( ! $varient_id ) {
			return;
		}

		$invoice_id = get_post_meta( $varient_id, '_invoice_id', true );
		$invoice = SI_Invoice::get_instance( $invoice_id );

		if ( ! is_a( $invoice, 'SI_Invoice' ) ) {
			return;
		}

		// create new payment
		$payment_id = SI_Payment::new_payment( array(
			'payment_method' => self::PAYMENT_METHOD,
			'invoice' => $invoice->get_id(),
			'amount' => $order->get_total(),
			'transaction_id' => $order_id,
			'data' => array(
			'amount' => $order->get_total(),
			'woo_order_id' => '<a href="'.get_edit_post_link( $order_id ).'">'.$order_id.'</a>',
			),
		), SI_Payment::STATUS_COMPLETE );
		if ( ! $payment_id ) {
			return false;
		}
		$payment = SI_Payment::get_instance( $payment_id );
		do_action( 'payment_authorized', $payment );
		$payment->set_status( SI_Payment::STATUS_COMPLETE );
		do_action( 'payment_complete', $payment );
		return $payment;
	}

	public static function redirect_to_invoice_after_payment( $order_id = 0 ) {
		$order = wc_get_order( $order_id );

		$varient_id = 0;
		foreach ( $order->get_items() as $item ) {
			if ( isset( $item['variation_id'] ) ) {
				$varient_id = $item['variation_id'];
			}
		}

		if ( ! $varient_id ) {
			return;
		}

		$invoice_id = get_post_meta( $varient_id, '_invoice_id', true );
		$invoice = SI_Invoice::get_instance( $invoice_id );

		if ( ! is_a( $invoice, 'SI_Invoice' ) ) {
			return;
		}

		$confirmation_url = esc_url_raw( add_query_arg( array( 'invoice_payment' => self::PAYMENT_SLUG, 'si_payment_action' => 'confirmation' ), get_permalink( $invoice_id ) ) );

		if ( $invoice->get_balance() < 0.01 ) {
			self::set_message( __( 'Payment Received & Invoice Paid!', 'sprout-invoices' ), self::MESSAGE_STATUS_INFO );
		} elseif ( $invoice->get_payments_total( false ) < 0.01 ) {
			self::set_message( sprintf( __( 'Payment Pending. Current Balance: %s', 'sprout-invoices' ), sa_get_formatted_money( $invoice->get_balance() ) ), self::MESSAGE_STATUS_INFO );
		} else {
			self::set_message( sprintf( __( 'Partial Payment Received. Current Balance: %s', 'sprout-invoices' ), sa_get_formatted_money( $invoice->get_balance() ) ), self::MESSAGE_STATUS_INFO );
		}

		if ( ! apply_filters( 'si_woopay_redirect_back_to_invoice', true ) ) {
			return;
		}

		if ( ! headers_sent() ) {
			wp_redirect( $confirmation_url );
			exit();
		} else {
			echo "<script type='text/javascript'>window.location = '" . $confirmation_url . "'</script>";
		}
	}
}
SI_Woo_Payment_Processor::register();
