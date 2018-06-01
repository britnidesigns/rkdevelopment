<?php

/**
 * Wrapper for the PDF Crowd API
 *
 * @package Sprout_Invoice
 * @subpackage Sprout_PDFs_Controller
 */
class SI_Sprout_PDFs_API extends SI_Sprout_PDFs_Controller {

	public static function init() {

	}

	public static function get_pdf( $doc_id = 0 ) {
		if ( ! $doc_id ) {
			$doc_id = get_the_ID();
		}
		$get_stored_pdf_path = self::get_generated_pdf( $doc_id );
		if ( file_exists( $get_stored_pdf_path ) ) {
			return $get_stored_pdf_path;
		}

		if ( doing_action( 'si_pdf_service_get_pdf' ) ) {
			return;
		}

		do_action( 'si_pdf_service_get_pdf', $doc_id );

		$doc_url = add_query_arg( array( self::PDC_VIEW_QUERY_ARG => 1 ), get_permalink( $doc_id ) );
		if ( SI_DEV ) {
			$doc_url = 'http://playground.sproutapps.co/3c9bvye2tgkr2x1/sprout-invoice/363190db43c0bed6ab61132335505c3f/';
		}

		try {

			$api_key = self::get_api_key();

			if ( is_object( $api_key ) && isset( $api_key->error ) ) {
				self::set_message( sprintf( __( '<b>PDF Service Unavailable:</b> %s.', 'sprout-invoices' ), wp_kses_post( $api_key->error ) ), self::MESSAGE_STATUS_ERROR );
				return false;
			}

			$client = new \Pdfcrowd\HtmlToPdfClient( self::get_username(), $api_key );

			$upload_dir = self::get_upload_path();
			$file_name = self::get_file_name( $doc_id );
			$uploaded_file = trailingslashit( $upload_dir ) . $file_name;

			$margin = apply_filters( 'si_pdf_service_margins', '.2in' );
			$client->setPageMargins( $margin, $margin, $margin, $margin );

			$client->convertUrlToFile( $doc_url, $uploaded_file );

			if ( filesize( $uploaded_file ) < 0.01 ) {
				do_action( 'si_error', 'PDF Generation Error - Removing Blank File', $doc_id );
				unlink( $uploaded_file );
			}

			return $uploaded_file;

		} catch (\Pdfcrowd\Error $why) {
			do_action( 'si_error', 'SI PDF Service - Generation Error', $why->getMessage() );

		}
	}
}
