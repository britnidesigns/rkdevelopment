<?php

/**
 * Dashboard Notifications Controller
 *
 * @package Sprout_Invoice
 * @subpackage Doc_Comments
 */
class Client_Dashboard_Notifications extends SI_Notifications {


	public static function init() {

		add_filter( 'sprout_notifications', array( __CLASS__, 'add_notification_shortcode_compatibility' ), 100 );
		add_filter( 'sprout_notification_shortcodes', array( __CLASS__, 'add_notification_shortcode' ), 100 );

		// Hook actions that would send a notification
		self::notification_hooks();

		// register notifications
		add_filter( 'sprout_notifications', array( __CLASS__, 'register_notifications' ), 100 );

	}


	////////////////////
	// Notifications //
	////////////////////


	/**
	 * Hooks for all notifications
	 * @return
	 */
	private static function notification_hooks() {
		// Notifications can be suppressed
		if ( apply_filters( 'suppress_notifications', false ) ) {
			return;
		}

		// Admin
		add_action( 'si_user_created', array( __CLASS__, 'user_creation_notification' ), 10, 2 );
	}

	public static function register_notifications( $notifications = array() ) {
		$default_notifications = array(
				// Lead Generation
				'user_notification' => array(
					'name' => __( 'User Created', 'sprout-invoices' ),
					'description' => __( 'Customize the email that is sent to a client after their user account is created.', 'sprout-invoices' ),
					'shortcodes' => array( 'date', 'name', 'username', 'password', 'dashboard_link' ),
					'default_title' => sprintf( __( '%s: Client Account Created', 'sprout-invoices' ), get_bloginfo( 'name' ) ),
					'default_content' => self::default_user_notification(),
					'default_disabled' => true,
				),
			);
		return array_merge( $notifications, $default_notifications );
	}

	public static function user_creation_notification( $user_id, $args = array() ) {
		$client_ids = SI_Client::get_clients_by_user( $user_id );
		$client = ( ! empty( $client_ids ) ) ? SI_Client::get_instance( $client_ids[0] ) : 0 ;
		$to = self::get_user_email( $user_id );
		$data = array(
			'user_id' => $user_id,
			'client' => $client,
			'user_args' => $args,
			'to' => $to,
		);
		self::send_notification( 'user_notification', $data, $to );
	}

	public static function add_notification_shortcode_compatibility( $notifications = array() ) {
		foreach ( $notifications as $key => $data ) {
			if ( ! in_array( $key, array( 'accepted_estimate', 'declined_estimate', 'payment_notification' ) ) ) { // don't show for admin notifications
				$notifications[ $key ]['shortcodes'] = array_merge( $notifications[ $key ]['shortcodes'], array( 'dashboard_link' ) );
			}
		}
		return $notifications;
	}

	public static function add_notification_shortcode( $default_shortcodes = array() ) {
		$new_shortcodes = array(
			'dashboard_link' => array(
				'description' => __( 'Used to provide the client with a private url for them to view their private dashboard without logging in. Without this private link the user/client will need to login with a password.', 'sprout-invoices' ),
				'callback' => array( __CLASS__, 'shortcode_dashboard_link' ),
			),
			'password' => array(
				'description' => __( 'Used to provide the client with a their login. This is only used on the User Created notification which is disabled by default since the client should simply receive the [dashboard_link] in all notifications instead. Sending passwords is a potential security risk for your users.', 'sprout-invoices' ),
				'callback' => array( __CLASS__, 'shortcode_password' ),
			),
		);
		return array_merge( $new_shortcodes, $default_shortcodes );
	}

	public static function shortcode_dashboard_link( $atts, $content, $code, $data ) {
		$client_id = 0;
		$doc_id = 0;
		if ( isset( $data['client'] ) && is_a( $data['client'], 'SI_Client' ) ) {
			$client_id = $data['client']->get_id();
		}
		if ( ! $client_id && isset( $data['invoice'] ) && is_a( $data['invoice'], 'SI_Invoice' ) ) {
			$doc_id = $data['invoice']->get_ID();
			$client_id = $data['invoice']->get_client_id();
		}
		if ( ! $client_id && isset( $data['estimate'] ) && is_a( $data['estimate'], 'SI_Estimate' ) ) {
			$doc_id = $data['estimate']->get_ID();
			$client_id = $data['estimate']->get_client_id();
		}

		$user_id = 0;
		$user = get_user_by( 'email', $data['to'] );
		if ( ! is_wp_error( $user ) ) {
			// Add user id as query arg to reference
			$user_id = $user->ID;
		}

		$url = SI_Client_Dashboard::private_dashboard_url( $client_id, $user_id, $doc_id );
		return esc_url_raw( $url );
	}

	public static function shortcode_password( $atts, $content, $code, $data ) {
		$password = __( 'N/A', 'sprout-invoices' );
		if ( isset( $data['user_args']['user_pass'] ) ) {
			$password = $data['user_args']['user_pass'];
		} elseif ( isset( $data['user_args']['password'] ) ) {
			$password = $data['user_args']['password'];
		} elseif ( isset( $data['password'] ) ) {
			$password = $data['password'];
		}

		return $password;
	}

	public static function default_user_notification() {
		if ( class_exists( 'SI_HTML_Notifications' ) ) {
			return SI_Client_Dashboard::load_addon_view_to_string( 'notifications/user-created-html', array(), true );
		} else {
			return SI_Client_Dashboard::load_addon_view_to_string( 'notifications/user-created', array(), true );
		}
	}
}
