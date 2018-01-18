<?php

/**
 * @package Sprout_Invoices
 * @subpackage Payment Term
 */
class SI_Payment_Term extends SI_Payment_Terms {
	const PAYMENT_TERM_RECORD = 'si_payment_term_record';

	////////////
	// Record //
	////////////

	/**
	 * Create a payment_term entry
	 * @param  array $args
	 * @return int
	 */
	public static function new_payment_term( $data ) {
		$id = SI_Internal_Records::new_record(
			$data,
			self::PAYMENT_TERM_RECORD,
			$data['doc_id'],
			$data['title'],
		0 );
		self::delete_terms_cache( $data['doc_id'] );
		return $id;
	}

	public static function delete_payment_term( $payment_term_id = 0 ) {
		$payment_term = SI_Record::get_instance( $payment_term_id );
		if ( ! is_a( $payment_term, 'SI_Record' ) || self::PAYMENT_TERM_RECORD !== $payment_term->get_type() ) {
			return false;
		}
		self::delete_terms_cache( $payment_term->get_associate_id() );
		return wp_delete_post( $payment_term_id, true );
	}

	public static function get_payment_term_records( $invoice_id = 0 ) {
		return SI_Record::get_records_by_type_and_association( $invoice_id, self::PAYMENT_TERM_RECORD );
	}

	public static function get_payment_term( $payment_term_id = 0 ) {
		$record = SI_Record::get_instance( $payment_term_id );
		if ( ! is_a( $record, 'SI_Record' ) ) {
			$record = 0;
		}
		return $record;
	}

	/**
	 * Get terms by date query
	 * @param  integer $after
	 * @param  integer $before
	 * @param  integer $associate_id
	 * @return array
	 */
	public static function get_terms_by_date( $after = 0, $before = 0, $associate_id = 0 ) {

		if ( 0 === $after || '' === $after ) {
			$after = current_time( 'timestamp' );
		}

		if ( 0 === $before ) {
			$before = '';
		}

		$args = array(
			'post_type' => SI_Record::POST_TYPE,
			'post_status' => 'any',
			'posts_per_page' => -1,
			'fields' => 'ids',
			'si_bypass_filter' => true,
			SI_Record::TAXONOMY => self::PAYMENT_TERM_RECORD,
		);

		if ( '' !== $after && '' !== $before ) {
			$args['date_query'] = array(
				array(
					'after'     => date( 'Y-m-d', $after ),
					'before'     => date( 'Y-m-d', $before ),
					'inclusive' => true,
				),
			);
		} else {
			$args['date_query'] = array(
				array(
					'after'     => date( 'Y-m-d', $after ),
					'inclusive' => true,
				),
			);
		}

		if ( $associate_id ) {
			$args['post_parent'] = $associate_id;
		}

		$payment_terms = get_posts( $args );
		// TODO determine why front-end query is returning all records
		foreach ( $payment_terms as $key => $term_id ) {
			$payment_term = SI_Record::get_instance( $term_id );
			if ( ! is_a( $payment_term, 'SI_Record' ) || SI_Payment_Term::PAYMENT_TERM_RECORD !== $payment_term->get_type() ) {
				unset( $payment_terms[ $key ] );
			}
		}
		return $payment_terms;
	}

	///////////
	// Meta //
	///////////

	public static function mark_term_complete( $payment_term_id = 0, $payment_id = 0 ) {
		$record = SI_Record::get_instance( $payment_term_id );
		if ( ! is_a( $record, 'SI_Record' ) ) {
			return 0;
		}
		$data = $record->get_data();
		$data['payment_id'] = $payment_id;
		$data['complete'] = 'true';
		$record->set_data( $data );

		self::delete_terms_cache( $record->get_associate_id() );

		return $record;
	}
}
