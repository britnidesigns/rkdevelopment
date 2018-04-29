<?php

/**
 * Sprout_PDFs_Settings Controller
 *
 * @package Sprout_Invoice
 * @subpackage Sprout_PDFs_Settings
 */
class SI_Sprout_PDFs_Attachments extends SI_Sprout_PDFs_Controller {

	public static function init() {
		add_filter( 'si_notification_attachments', array( __CLASS__, 'add_pdf_to_notifications' ), 10, 6 );
	}


	public static function add_pdf_to_notifications( $attachments = array(), $notification_name, $data, $from_email, $from_name, $html ) {
		$notifications_to_attach = apply_filters( 'si_pdf_notifications_to_attach', array( 'send_invoice', 'send_estimate', 'deposit_payment', 'final_payment', 'reminder_payment' ) );
		if ( ! in_array( $notification_name, $notifications_to_attach ) ) {
			return;
		}

		$doc_id = 0;
		if ( isset( $data['invoice'] ) && is_a( $data['invoice'], 'SI_Invoice' ) ) {
			$doc_id = $data['invoice']->get_ID();
		}
		if ( in_array( $notification_name, array( 'send_estimate' ) ) ) {
			if ( isset( $data['estimate'] ) && is_a( $data['estimate'], 'SI_Estimate' ) ) {
				$doc_id = $data['estimate']->get_ID();
			}
		}

		if ( $doc_id ) {

			$file = SI_Sprout_PDFs_API::get_pdf( $doc_id );
			$attachments[] = $file;

		}
		return $attachments;
	}
}
