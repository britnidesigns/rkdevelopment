<?php

/**
 * Doc Comments Notifications Controller
 *
 * @package Sprout_Invoice
 * @subpackage Doc_Comments
 */
class SI_Doc_Comments_Notifications extends SI_Notifications {

	public static function init() {

		// register notifications
		add_filter( 'sprout_notifications', array( __CLASS__, 'register_notifications' ) );

		//Shortcodes
		add_filter( 'sprout_notification_shortcodes', array( __CLASS__, 'add_notification_shortcode' ), 100 );

		// Hook actions that would send a notification
		self::notification_hooks();

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
		add_action( 'si_insert_doc_comment', array( __CLASS__, 'comment_notifications' ), 10, 3 );
	}

	public static function register_notifications( $notifications = array() ) {
		$default_notifications = array(
				// Lead Generation
				'comment_notification_estimate' => array(
					'name' => __( 'Comment Added for Estimate', 'sprout-invoices' ),
					'description' => __( 'Customize the email that is sent to the client when a comment is added.', 'sprout-invoices' ),
					'shortcodes' => array( 'date', 'name', 'username', 'admin_note', 'estimate_subject', 'estimate_id', 'estimate_edit_url', 'estimate_url', 'client_name', 'client_edit_url', 'client_address', 'client_company_website', 'comment' ),
					'default_title' => sprintf( __( '%s: We responded to your Estimate', 'sprout-invoices' ), get_bloginfo( 'name' ) ),
					'default_content' => self::default_comment_notification_estimate(),
				),
				'comment_notification_invoice' => array(
					'name' => __( 'Comment Added for Invoice', 'sprout-invoices' ),
					'description' => __( 'Customize the email that is sent to the client when a comment is added.', 'sprout-invoices' ),
					'shortcodes' => array( 'date', 'name', 'username', 'admin_note', 'invoice_subject', 'invoice_id', 'invoice_edit_url', 'invoice_url', 'client_name', 'client_edit_url', 'client_address', 'client_company_website', 'comment' ),
					'default_title' => sprintf( __( '%s: We responded to your Invoice', 'sprout-invoices' ), get_bloginfo( 'name' ) ),
					'default_content' => self::default_comment_notification_invoice(),
				),
				// Admin Notification
				'admin_comment_notification_estimate' => array(
					'name' => __( 'Admin Notification: Comment Added for Estimate', 'sprout-invoices' ),
					'description' => __( 'Customize the email that is sent to you when a comment is added.', 'sprout-invoices' ),
					'shortcodes' => array( 'date', 'name', 'username', 'admin_note', 'estimate_subject', 'estimate_id', 'estimate_edit_url', 'estimate_url', 'client_name', 'client_edit_url', 'client_address', 'client_company_website', 'comment' ),
					'default_title' => sprintf( __( '%s: Client Responded to an Estimate', 'sprout-invoices' ), get_bloginfo( 'name' ) ),
					'default_content' => self::default_comment_notification_estimate_to_admin(),
				),
				'admin_comment_notification_invoice' => array(
					'name' => __( 'Admin Notification: Comment Added for Invoice', 'sprout-invoices' ),
					'description' => __( 'Customize the email that is sent to you when a comment is added.', 'sprout-invoices' ),
					'shortcodes' => array( 'date', 'name', 'username', 'admin_note', 'invoice_subject', 'invoice_id', 'invoice_edit_url', 'invoice_url', 'client_name', 'client_edit_url', 'client_address', 'client_company_website', 'comment' ),
					'default_title' => sprintf( __( '%s: Client Responded to an Invoice', 'sprout-invoices' ), get_bloginfo( 'name' ) ),
					'default_content' => self::default_comment_notification_invoice_to_admin(),
				),
			);
		return array_merge( $notifications, $default_notifications );
	}

	public static function add_notification_shortcode( $default_shortcodes = array() ) {
		$new_shortcodes = array(
			'comment' => array(
				'description' => __( 'Used to show the comment that was submitted.', 'sprout-invoices' ),
				'callback' => array( __CLASS__, 'shortcode_comment' ),
			),
		);
		return array_merge( $new_shortcodes, $default_shortcodes );
	}

	public static function comment_notifications( $comment_id = 0, $doc, $comment_data = array() ) {
		$user_id = $comment_data['user_id'];
		if ( ! is_numeric( $user_id ) ) {
			return;
		}
		// comment was not from an admin so send a notification to admin
		if ( ! current_user_can( 'edit_post', $doc->get_ID() ) ) { // TODO
			self::send_admin_notification( $comment_id, $doc, $comment_data );
		} else {
			self::send_client_notification( $comment_id, $doc, $comment_data );
		}
		return; // notifications sent
	}

	public static function send_client_notification( $comment_id = 0, $doc, $comment_data = array() ) {
		$user_id = $comment_data['user_id'];
		$client = $doc->get_client();
		if ( is_a( $client, 'SI_Client' ) ) {
			// Send notification to all client users
			$client_users = SI_Notifications_Control::get_document_recipients( $doc );
			foreach ( array_unique( $client_users ) as $user_id ) {
				if ( ! is_wp_error( $user_id ) ) {
					$to = self::get_user_email( $user_id );
					$data = array(
						'user_id' => $user_id,
						'client' => $client,
						'comment_data' => $comment_data,
						'to' => $to,
					);
					if ( is_a( $doc, 'SI_Estimate' ) ) {
						$data['estimate'] = $doc;
						self::send_notification( 'comment_notification_estimate', $data, $to );
					} elseif ( is_a( $doc, 'SI_Invoice' ) ) {
						$data['invoice'] = $doc;
						self::send_notification( 'comment_notification_invoice', $data, $to );
					}
				}
			}
		}
	}

	public static function send_admin_notification( $comment_id = 0, $doc, $comment_data = array() ) {
		$client = $doc->get_client();
		$data = array(
			'user_id' => 1,
			'client' => $client,
			'comment_data' => $comment_data,
		);
		$admin_to = self::admin_email( $data );
		if ( is_a( $doc, 'SI_Estimate' ) ) {
			$data['estimate'] = $doc;
			self::send_notification( 'admin_comment_notification_estimate', $data, $admin_to );
		} elseif ( is_a( $doc, 'SI_Invoice' ) ) {
			$data['invoice'] = $doc;
			self::send_notification( 'admin_comment_notification_invoice', $data, $admin_to );
		}
	}

	public static function shortcode_comment( $atts, $content, $code, $data ) {
		if ( ! isset( $data['comment_data']['comment_content'] ) ) {
			return __( 'N/A', 'sprout-invoices' );
		}
		return stripslashes( $data['comment_data']['comment_content'] );
	}

	public static function default_comment_notification_estimate() {
		$path = 'notifications/';
		if ( class_exists( 'SI_HTML_Notifications' ) ) {
			$path = 'notifications/html/';
		}
		return SI_Doc_Comments::load_addon_view_to_string( $path . 'comment-notification-estimate', array(), true );
	}

	public static function default_comment_notification_invoice() {
		$path = 'notifications/';
		if ( class_exists( 'SI_HTML_Notifications' ) ) {
			$path = 'notifications/html/';
		}
		return SI_Doc_Comments::load_addon_view_to_string( $path . 'comment-notification-invoice', array(), true );
	}

	public static function default_comment_notification_estimate_to_admin() {
		$path = 'notifications/';
		if ( class_exists( 'SI_HTML_Notifications' ) ) {
			$path = 'notifications/html/';
		}
		return SI_Doc_Comments::load_addon_view_to_string( $path . 'admin-notification-comment-estimate', array(), true );
	}

	public static function default_comment_notification_invoice_to_admin() {
		$path = 'notifications/';
		if ( class_exists( 'SI_HTML_Notifications' ) ) {
			$path = 'notifications/html/';
		}
		return SI_Doc_Comments::load_addon_view_to_string( $path . 'admin-notification-comment-invoice', array(), true );
	}
}
