<?php

/**
 * Dashboard Notifications Controller
 *
 * @package Sprout_Invoice
 * @subpackage Doc_Comments
 */
class SI_Invoices_Recurring_Notifications extends SI_Invoices_Recurring {


	public static function init() {

		add_filter( 'si_invoice_notifications_manually_send', array( __CLASS__, 'add_to_manual_send' ), 100 );

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
		add_action( 'send_recurring_invoice', array( __CLASS__, 'send_recurring_invoice_notification' ), 10, 2 );
	}

	public static function add_to_manual_send( $manual = array() ) {
		$manual['recurring_invoice'] = __( 'Recurring Invoice Available', 'sprout-invoices' );
		return $manual;
	}

	public static function register_notifications( $notifications = array() ) {
		$default_notifications = array(
				// Lead Generation
				'recurring_invoice' => array(
					'name' => __( 'Recurring Invoice Available', 'sprout-invoices' ),
					'description' => __( 'Customize the invoice email that is sent after a recurring invoice is created.', 'sprout-invoices' ),
					'shortcodes' => array( 'date', 'name', 'first_name', 'username', 'admin_note', 'line_item_table', 'line_item_list', 'line_item_plain_list', 'invoice_subject', 'invoice_id', 'invoice_edit_url', 'invoice_url', 'invoice_issue_date', 'invoice_due_date', 'invoice_past_due_date', 'invoice_po_number', 'invoice_tax_total', 'invoice_tax', 'invoice_tax2', 'invoice_terms', 'invoice_notes', 'invoice_total', 'invoice_payments_list', 'invoice_payments_list_html', 'invoice_subtotal', 'invoice_calculated_total', 'invoice_deposit_amount', 'invoice_total_due', 'invoice_total_payments', 'client_name', 'client_address', 'client_company_website' ),
					'default_title' => sprintf( __( '%s: Your Recurring Invoice is Available', 'sprout-invoices' ), get_bloginfo( 'name' ) ),
					'default_content' => self::default_notification(),
					'default_disabled' => true,
				),
			);
		return array_merge( $notifications, $default_notifications );
	}

	public static function send_recurring_invoice_notification( $user_id, $args = array() ) {
		$client_ids = SI_Client::get_clients_by_user( $user_id );
		$client = ( ! empty( $client_ids ) ) ? SI_Client::get_instance( $client_ids[0] ) : 0 ;
		$to = self::get_user_email( $user_id );
		$data = array(
			'user_id' => $user_id,
			'client' => $client,
			'user_args' => $args,
			'to' => $to,
		);
		self::send_notification( 'recurring_invoice', $data, $to );
	}


	public static function default_notification() {
		if ( class_exists( 'SI_HTML_Notifications' ) ) {
			return self::load_addon_view_to_string( 'notifications/recurring_invoice-html', array(), true );
		} else {
			return self::load_addon_view_to_string( 'notifications/recurring_invoice', array(), true );
		}
	}
}
