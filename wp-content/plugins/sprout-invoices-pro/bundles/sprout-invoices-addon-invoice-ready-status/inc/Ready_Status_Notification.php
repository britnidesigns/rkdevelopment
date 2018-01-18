<?php

/**
 * SI_Ready_Status_Notification Controller
 *
 * Notifications for the ready status
 *
 * @package SI_Ready_Status_Notification
 * @subpackage SI_Ready_Status
 */
class SI_Ready_Status_Notification extends SI_Ready_Status {

	public static function init() {
		// register notifications
		add_filter( 'sprout_notifications', array( __CLASS__, 'register_notifications' ) );

		// action
		add_action( 'si_invoice_status_updated', array( __CLASS__, 'maybe_send_notification' ), 10, 2 );
	}



	public static function register_notifications( $notifications = array() ) {
		$default_notifications = array(
				// Lead Generation
				'payment_due_reminder' => array(
					'name' => __( 'Payment Due Reminder', 'sprout-invoices' ),
					'description' => __( 'An invoice is ready to be paid.', 'sprout-invoices' ),
					'shortcodes' => array( 'date', 'name', 'username', 'payment_total', 'payment_id', 'line_item_table', 'line_item_list', 'line_item_plain_list', 'invoice_subject', 'invoice_id', 'invoice_edit_url', 'invoice_url', 'invoice_issue_date', 'invoice_due_date', 'invoice_past_due_date', 'invoice_po_number', 'invoice_tax_total', 'invoice_tax', 'invoice_tax2', 'invoice_terms', 'invoice_notes', 'invoice_total', 'invoice_subtotal', 'invoice_calculated_total', 'invoice_total_due', 'invoice_deposit_amount', 'invoice_total_payments', 'client_name', 'client_address', 'client_company_website' ),
					'default_title' => sprintf( __( '%s: Invoice Payment Due', 'sprout-invoices' ),  get_bloginfo( 'name' ) ),
					'default_content' => self::invoice_ready_default_notification(),
				),
			);
		return array_merge( $notifications, $default_notifications );
	}

	public static function invoice_ready_default_notification() {
		$path = 'notifications/';
		if ( class_exists( 'SI_HTML_Notifications' ) ) {
			$path = 'notifications/html/';
		}
		return self::load_addon_view_to_string( $path . 'invoice-ready', array(), true );
	}


	public static function maybe_send_notification( SI_Invoice $invoice, $status = 'temp' ) {
		if ( 'ready' != $status ) {
			return;
		}
		$client = $invoice->get_client();
		if ( ! is_a( $client, 'SI_Client' ) ) {
			return;
		}
		$client_users = SI_Notifications_Control::get_document_recipients( $invoice );
		if ( ! is_array( $client_users ) ) {
			return;
		}
		// send to user
		foreach ( array_unique( $client_users ) as $user_id ) {
			$to = SI_Notifications_Control::get_user_email( $user_id );
			$data = array(
				'invoice' => $invoice,
				'client' => $client,
				'user_id' => $user_id,
				'to' => $to,
			);
			SI_Notifications_Control::send_notification( 'payment_due_reminder', $data, $to );
		}
	}
}
