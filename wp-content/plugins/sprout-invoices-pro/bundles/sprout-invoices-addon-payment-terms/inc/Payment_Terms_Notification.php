<?php

/**
 * SI_Payment_Terms_Fees Controller
 *
 * Handles the any fees notifications
 *
 * @package SI_Payment_Terms
 * @subpackage SI_Payment_Terms_Notifications
 */
class SI_Payment_Terms_Notifications extends SI_Payment_Terms {

	public static function init() {

		// Hook actions that would send a notification
		self::notification_hooks();

		// register notifications
		add_filter( 'sprout_notifications', array( __CLASS__, 'register_notifications' ) );

		//Shortcodes
		add_filter( 'sprout_notification_shortcodes', array( __CLASS__, 'add_notification_shortcode' ), 100 );

	}



	public static function register_notifications( $notifications = array() ) {
		$default_notifications = array(
				// Lead Generation
				'payment_term_reminder' => array(
					'name' => __( 'Payment Terms Reminder', 'sprout-invoices' ),
					'description' => __( 'A payment fee is about to be added to an invoice.', 'sprout-invoices' ),
					'shortcodes' => array( 'date', 'name', 'username', 'admin_note', 'invoice_subject', 'invoice_id', 'invoice_edit_url', 'invoice_url', 'invoice_due_date', 'invoice_total_due', 'client_name', 'client_edit_url', 'client_address', 'client_company_website', 'payment_term_name', 'payment_term_fee', 'payment_term_due' ),
					'default_title' => sprintf( __( '%s: Payment Fee Reminder', 'sprout-invoices' ), get_bloginfo( 'name' ) ),
					'default_content' => self::default_fee_reminder(),
				),
				'payment_term_added' => array(
					'name' => __( 'Payment Terms Fee Added', 'sprout-invoices' ),
					'description' => __( 'A payment fee has been added to an invoice.', 'sprout-invoices' ),
					'shortcodes' => array( 'date', 'name', 'username', 'admin_note', 'invoice_subject', 'invoice_id', 'invoice_edit_url', 'invoice_url', 'invoice_due_date', 'invoice_total_due', 'client_name', 'client_edit_url', 'client_address', 'client_company_website', 'payment_term_name', 'payment_term_fee', 'payment_term_due' ),
					'default_title' => sprintf( __( '%s: Payment Fee Added', 'sprout-invoices' ), get_bloginfo( 'name' ) ),
					'default_content' => self::default_fee_added(),
				),
			);
		return array_merge( $notifications, $default_notifications );
	}

	/**
	 * Hooks for all notifications
	 * @return
	 */
	private static function notification_hooks() {
		// Notifications can be suppressed
		if ( apply_filters( 'suppress_notifications', false ) ) {
			return;
		}

		add_action( self::CRON_HOOK, array( __CLASS__, 'check_for_invoices_with_a_term' ) );
	}


	public static function check_for_invoices_with_a_term() {

		$after = current_time( 'timestamp' );
		$three_days = ( DAY_IN_SECONDS * 3 );
		$before = current_time( 'timestamp' ) + apply_filters( 'si_late_fee_remind_within', $three_days );

		// Get terms that are due today and within the next three days.
		$term_ids = SI_Payment_Term::get_terms_by_date( $after, $before );
		foreach ( $term_ids as $term_id ) {
			self::maybe_send_reminder_or_late_notice( $term_id );
		}
	}

	public static function maybe_send_reminder_or_late_notice( $term_id ) {

		$payment_term = SI_Record::get_instance( $term_id );

		$invoice_id = $payment_term->get_associate_id();
		$invoice = SI_Invoice::get_instance( $invoice_id );
		if ( ! is_a( $invoice, 'SI_Invoice' ) ) {
			return;
		}

		// no client noone to send to.
		$client = $invoice->get_client();
		if ( ! is_a( $client, 'SI_Client' ) ) {
			return;
		}

		$data = $payment_term->get_data();
		$data['term_id'] = $term_id;

		// if the late fee is complete than there's nothing to send.
		if ( 'true' === $data['complete'] ) {
			return;
		}

		$term_time = $data['time'];

		// If the payment term is in the future.
		if ( $term_time > current_time( 'timestamp' ) ) {

			// Check if reminder was already sent
			if ( isset( $data['reminder_sent'] ) && $data['reminder_sent'] ) {
				return;
			}

			// reduce due date by three days
			$before = apply_filters( 'si_late_fee_remind_within', ( DAY_IN_SECONDS * 3 ) );
			$remind_within = apply_filters( 'si_late_fee_remind_within', $term_time - $before ); // in three days

			// if due date is now prior to today than it's within three days.
			if ( $remind_within < current_time( 'timestamp' ) ) {
				self::send_notification_of_almost_late_fee( $invoice, $data );
			}

			// if no reminder is needed.
			return;
		}

		// Check if late notice was already sent
		if ( isset( $data['late_notice_sent'] ) && $data['late_notice_sent'] ) {
			return;
		}

		self::send_notification_of_late_fee( $invoice, $data );
	}

	public static function send_notification_of_almost_late_fee( SI_Invoice $invoice, $term_data = array() ) {
		$client = $invoice->get_client();
		if ( ! is_a( $client, 'SI_Client' ) ) {
			return;
		}

		$data = array(
			'invoice' => $invoice,
			'client' => $client,
			'term_data' => $term_data,
		);

		$client_users = SI_Notifications_Control::get_document_recipients( $invoice );
		foreach ( array_unique( $client_users ) as $user_id ) {
			if ( ! is_wp_error( $user_id ) ) {
				$to = SI_Notifications_Control::get_user_email( $user_id );
				$data['to'] = $to;
				SI_Notifications_Control::send_notification( 'payment_term_reminder', $data, $to );
				self::mark_reminder_sent( $term_data['term_id'] );
			}
		}
	}


	public static function send_notification_of_late_fee( SI_Invoice $invoice, $term_data = array() ) {
		$client = $invoice->get_client();
		if ( ! is_a( $client, 'SI_Client' ) ) {
			return;
		}

		$data = array(
			'invoice' => $invoice,
			'client' => $client,
			'term_data' => $term_data,
		);

		$client_users = SI_Notifications_Control::get_document_recipients( $invoice );
		foreach ( array_unique( $client_users ) as $user_id ) {
			if ( ! is_wp_error( $user_id ) ) {
				$to = SI_Notifications_Control::get_user_email( $user_id );
				$data['to'] = $to;
				SI_Notifications_Control::send_notification( 'payment_term_added', $data, $to );
				self::mark_late_notice_sent( $term_data['term_id'] );
			}
		}
	}


	////////////////
	// Shortcodes //
	////////////////

	public static function add_notification_shortcode( $default_shortcodes = array() ) {
		$new_shortcodes = array(
			'payment_term_name' => array(
				'description' => __( 'Used to show the payment term name.', 'sprout-invoices' ),
				'callback' => array( __CLASS__, 'shortcode_name' ),
			),
			'payment_term_fee' => array(
				'description' => __( 'Used to show the payment term fee.', 'sprout-invoices' ),
				'callback' => array( __CLASS__, 'shortcode_fee' ),
			),
			'payment_term_due' => array(
				'description' => __( 'Used to show the payment term due date.', 'sprout-invoices' ),
				'callback' => array( __CLASS__, 'shortcode_due_date' ),
			),
		);
		return array_merge( $new_shortcodes, $default_shortcodes );
	}

	public static function shortcode_name( $atts, $content, $code, $data ) {
		if ( ! isset( $data['term_data']['title'] ) ) {
			return __( 'N/A', 'sprout-invoices' );
		}
		return $data['term_data']['title'];
	}

	public static function shortcode_fee( $atts, $content, $code, $data ) {
		if ( ! isset( $data['term_data']['amount'] ) ) {
			return __( 'N/A', 'sprout-invoices' );
		}
		$invoice_id = 0;
		if ( isset( $data['invoice_id'] ) ) {
			$invoice_id = $data['invoice_id'];
		}
		return sa_get_formatted_money( $data['term_data']['amount'], $invoice_id );
	}

	public static function shortcode_due_date( $atts, $content, $code, $data ) {
		if ( ! isset( $data['term_data']['due_date'] ) ) {
			return __( 'N/A', 'sprout-invoices' );
		}
		return date_i18n( get_option( 'date_format' ), $data['term_data']['due_date'] );
	}

	///////////////////
	// Notifications //
	///////////////////

	public static function default_fee_reminder() {
		$path = 'notifications/';
		if ( class_exists( 'SI_HTML_Notifications' ) ) {
			$path = 'notifications/html/';
		}
		return self::load_addon_view_to_string( $path . 'payment-term-reminder', array(), true );
	}

	public static function default_fee_added() {
		$path = 'notifications/';
		if ( class_exists( 'SI_HTML_Notifications' ) ) {
			$path = 'notifications/html/';
		}
		return self::load_addon_view_to_string( $path . 'payment-term-added', array(), true );
	}

	/////////////
	// Utility //
	/////////////

	public static function mark_reminder_sent( $payment_term_id = 0 ) {
		$record = SI_Record::get_instance( $payment_term_id );
		if ( ! is_a( $record, 'SI_Record' ) ) {
			return 0;
		}
		$data = $record->get_data();
		$data['reminder_sent'] = true;
		$record->set_data( $data );
		return $record;
	}

	public static function mark_late_notice_sent( $payment_term_id = 0 ) {
		$record = SI_Record::get_instance( $payment_term_id );
		if ( ! is_a( $record, 'SI_Record' ) ) {
			return 0;
		}
		$data = $record->get_data();
		$data['late_notice_sent'] = true;
		$record->set_data( $data );
		return $record;
	}
}
