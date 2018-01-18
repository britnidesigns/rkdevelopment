<?php

/**
 * Auto Billing Client Controller
 *
 *
 * @package SI_Sprout_Billings_Shortcodes
 */
class SI_Sprout_Billings_Shortcodes extends SI_Sprout_Billings {
	const SHORTCODE = 'sprout_invoices_payments_dashboard';
	const SHORTCODE_BALANCE = 'current_client_has_balance';
	const SHORTCODE_NO_BALANCE = 'current_client_has_zero_balance';

	public static function init() {
		do_action( 'sprout_shortcode', self::SHORTCODE, array( __CLASS__, 'dashboard' ) );

		do_action( 'sprout_shortcode', self::SHORTCODE_BALANCE, array( __CLASS__, 'has_balance' ) );
		do_action( 'sprout_shortcode', self::SHORTCODE_NO_BALANCE, array( __CLASS__, 'no_balance' ) );

		if ( ! is_admin() ) {
			// Enqueue
			add_action( 'wp_enqueue_scripts', array( __CLASS__, 'register_resources' ) );
		}

		// Client Dashboard
		add_action( 'si_client_dashboard_payment_column_after', array( __CLASS__, 'add_one_click_payment' ) );

		add_action( 'wp_ajax_si_ap_payment_option_save', array( __CLASS__, 'manage_payment_options' ) );
		add_action( 'wp_ajax_nopriv_si_ap_payment_option_save', array( __CLASS__, 'manage_payment_options' ) );
	}

	public static function dashboard( $atts = array() ) {
		do_action( 'sprout_invoices_payments_dashboard' );

		$user_id = 0;
		if ( class_exists( 'SI_Client_Dashboard' ) ) {
			$valid_client_ids = SI_Client_Dashboard::validate_token();
			if ( isset( $_GET[ SI_Client_Dashboard::USER_QUERY_ARG ] ) && $valid_client_ids ) {
				$user_id = (int) $_GET[ SI_Client_Dashboard::USER_QUERY_ARG ];
				$client_ids = $valid_client_ids;
			}
		}
		if ( ! $user_id && is_user_logged_in() ) {
			$user_id = get_current_user_id();
		}

		if ( $user_id ) {
			if ( empty( $client_ids ) ) {
				$client_ids = SI_Client::get_clients_by_user( $user_id );
			}
			if ( ! empty( $client_ids ) ) {
				$view = '';
				// show a dashboard for each client associated.
				foreach ( $client_ids as $client_id ) {
					$view .= self::dashboard_view( $client_id );
				}
				return $view;
			}
		}
		// no client associated
		do_action( 'sprout_invoices_payments_dashboard_not_client' );
		return self::blank_dashboard_view();
	}

	/**
	 * return content if there's a balance, otherwise nothing.
	 */
	public static function has_balance( $atts = array(), $content = '' ) {
		$client_id = self::get_client_id();
		if ( ! $client_id ) {
			return $content;
		}
		$has_balance = si_ab_get_clients_outstanding_balance( $client_id );
		if ( $has_balance ) {
			return $content;
		}
		return '';
	}

	/**
	 * return content if there's no balance, otherwise nothing.
	 */
	public static function no_balance( $atts = array(), $content = '' ) {
		$client_id = self::get_client_id();
		if ( $client_id ) {
			return '';
		}
		$has_balance = si_ab_get_clients_outstanding_balance( $client_id );
		if ( $has_balance ) {
			return '';
		}
		return $content;
	}

	public static function dashboard_view( $client_id ) {
		$client = SI_Client::get_instance( $client_id );
		if ( ! is_a( $client, 'SI_Client' ) ) {
			return;
		}

		self::frontend_enqueue();

		$payment_profiles = Sprout_Billings_Profiles::get_client_payment_profiles( $client_id );
		$view = self::load_addon_view_to_string( 'shortcodes/payments-dashboard', array(
			'client_id' => $client_id,
			'payment_profiles' => $payment_profiles,
			'default_payment_profile_id' => self::get_client_autopay_profile_id( $client_id ),
		), true );
		return $view;
	}

	public static function blank_dashboard_view() {
		return self::load_addon_view_to_string( 'shortcodes/payments-dashboard-blank', array(), true );
	}

	public static function add_one_click_payment( $invoice ) {
		$invoice_id = $invoice->get_id();
		$client_id = $invoice->get_client_id();
		$can_charge = SI_Sprout_Billings::can_autocharge_client( $client_id );
		if ( ! $can_charge ) {
			return;
		}
		if ( $invoice->get_balance() > 0.01 ) {
			$payment_amount = ( si_has_invoice_deposit( $invoice->get_id() ) ) ? $invoice->get_deposit() : $invoice->get_balance();
			echo '<br/>';
			printf( '<button class="one_click_payment" data-client_id="%3$s" data-invoice_id="%2$s">%1$s</button>', sprintf( __( 'One Click Payment &mdash; %s' , 'sprout-invoices' ), sa_get_formatted_money( $payment_amount ) ), $invoice_id, $client_id );
		}
	}

	//////////
	// AJAX //
	//////////



	public static function manage_payment_options() {
		$nonce = $_REQUEST['nonce'];
		if ( empty( $nonce ) ) { // TODO under some circumstances wp_verify_nonce fails, which is absurd
			wp_send_json_error( array( 'message' => __( 'Not going to fall for it!' , 'sprout-invoices' ) ) );
		}

		if ( ! isset( $_REQUEST['submission'] ) ) {
			wp_send_json_error( array( 'message' => __( 'Missing critical info!' , 'sprout-invoices' ) ) );
		}

		$submission = wp_parse_args( $_REQUEST['submission'] );
		if ( ! isset( $submission['client_id'] ) ) {
			wp_send_json_error( array( 'message' => __( 'Missing critical info!' , 'sprout-invoices' ) ) );
		}
		$client_id = $submission['client_id'];
		self::clear_client_payment_profile( $client_id );
		// Selected a preexisting method
		if ( in_array( $submission['sa_credit_payment_method'], array( 'new_credit', 'new_bank' ) ) ) {
			$new_payment_profile_id = '';
			$payment_info = array();
			$profile_id = Sprout_Billings_Profiles::get_customer_profile_id( $client_id );
			$method = $submission['sa_credit_payment_method'];

			$payment_info['billing']['first_name'] = $submission['sa_billing_first_name'];
			$payment_info['billing']['last_name'] = $submission['sa_billing_last_name'];
			$payment_info['billing']['street'] = $submission['sa_billing_street'];
			$payment_info['billing']['city'] = $submission['sa_billing_city'];
			$payment_info['billing']['zone'] = $submission['sa_billing_zone'];
			$payment_info['billing']['postal_code'] = $submission['sa_billing_postal_code'];
			$payment_info['billing']['country'] = $submission['sa_billing_country'];

			if ( 'new_credit' === $method ) {
				$payment_info['cc_number'] = $submission['sa_credit_cc_number'];
				$payment_info['cc_expiration_year'] = $submission['sa_credit_cc_expiration_year'];
				$payment_info['cc_expiration_month'] = $submission['sa_credit_cc_expiration_month'];
				$payment_info['cc_cvv'] = $submission['sa_credit_cc_cvv'];
			} elseif ( 'new_bank' === $method ) {
				$payment_info['bank_routing'] = $submission['sa_credit_bank_routing'];
				$payment_info['bank_account'] = $submission['sa_credit_bank_account'];
			}

			if ( ! empty( $payment_info ) ) {

				$new_payment_profile_id = Sprout_Billings_Profiles::create_new_payment_profile( $profile_id, $client_id, $payment_info );

				if ( is_array( $new_payment_profile_id ) ) {
					if ( isset( $new_payment_profile_id['message'] ) ) {
						wp_send_json_error( array( 'message' => $new_payment_profile_id['message'] ) );
					}
					if ( isset( $new_payment_profile_id['error'] ) && $new_payment_profile_id['error'] ) {
						return;
					}
				}

				self::save_client_payment_profile( $client_id, $new_payment_profile_id );
				wp_send_json_success( array( 'message' => __( 'New payment method created, saving your selection...' , 'sprout-invoices' ) ) );
			}
		} elseif ( isset( $submission['sa_credit_payment_method'] ) && '' !== $submission['sa_credit_payment_method'] ) {
			self::save_client_payment_profile( $client_id, $submission['sa_credit_payment_method'] );
			wp_send_json_success( array( 'message' => __( 'Saving profile selection...' , 'sprout-invoices' ) ) );
		}
		wp_send_json_success( array( 'message' => __( 'Saving no payment method being selected...' , 'sprout-invoices' ) ) );
	}


	//////////////
	// Enqueue //
	//////////////

	public static function register_resources() {
		wp_register_style( 'si_payment_dashboard', SA_ADDON_AUTO_BILLING_URL . '/resources/front-end/css/si-payment-dashboard.css', array( 'dashicons' ), self::SI_VERSION );
		wp_register_script( 'si_payment_dashboard', SA_ADDON_AUTO_BILLING_URL . '/resources/front-end/js/si-payment-dashboard.js', array( 'jquery' ), self::SI_VERSION );
	}

	public static function frontend_enqueue() {
		wp_enqueue_style( 'si_payment_dashboard' );
		wp_enqueue_script( 'si_payment_dashboard' );

		wp_localize_script( 'si_payment_dashboard', 'si_js_object', self::get_localized_js() );
	}
}
