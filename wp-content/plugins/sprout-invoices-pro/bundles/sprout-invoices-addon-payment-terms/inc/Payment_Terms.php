<?php

/**
 * SI_Payment_Terms_Fees Controller
 *
 * Handles payment terms meta
 *
 * @package SI_Payment_Terms
 * @subpackage SI_Payment_Terms_Fees
 */
class SI_Payment_Terms extends SI_Controller {
	const CACHE_KEY = 'si_payment_terms_';

	public static function init() {
		// SI_Payment_Term::init();
		SI_Payment_Terms_Admin::init();
		SI_Payment_Terms_Deposits::init();
		SI_Payment_Terms_Fees::init();
		SI_Payment_Terms_Notifications::init();

		add_filter( 'si_doc_history_records_pre_sort', array( __CLASS__, 'remove_terms_from_history' ), 10, 3 );
	}

	protected static function get_payment_terms( $doc_id ) {
		$payment_terms = SI_Payment_Term::get_payment_term_records( $doc_id );
		// TODO determine why front-end query is returning all records
		foreach ( $payment_terms as $key => $term_id ) {
			$payment_term = SI_Record::get_instance( $term_id );
			if ( ! is_a( $payment_term, 'SI_Record' ) || SI_Payment_Term::PAYMENT_TERM_RECORD !== $payment_term->get_type() ) {
				unset( $payment_terms[ $key ] );
			}
		}
		return $payment_terms;
	}

	public static function get_sorted_payment_terms( $doc_id ) {
		$cached_payment_terms = self::get_terms_cache( $doc_id );
		if ( $cached_payment_terms ) {
			return $cached_payment_terms;
		}

		$payment_terms = self::get_payment_terms( $doc_id );

		$sorted_payment_terms = array();
		foreach ( $payment_terms as $term_id ) {
			$payment_term = SI_Record::get_instance( $term_id );
			if ( ! is_a( $payment_term, 'SI_Record' ) ) {
				continue;
			}
			$data = $payment_term->get_data();
			$data['term_id'] = $term_id;
			$data['title'] = $payment_term->get_title();
			$data['date'] = $data['time'];
			$sorted_payment_terms[] = $data;
		}

		uasort( $sorted_payment_terms, array( 'SI_Controller', 'sort_by_date' ) );

		self::set_terms_cache( $doc_id, $sorted_payment_terms );

		return $sorted_payment_terms;
	}

	public static function get_terms_cache( $doc_id = 0 ) {
		$cache_key = self::CACHE_KEY . $doc_id;
		return get_transient( $cache_key );
	}

	public static function set_terms_cache( $doc_id = 0, $payment_terms = array() ) {
		$cache_key = self::CACHE_KEY . $doc_id;
		set_transient( $cache_key, $payment_terms );
	}

	public static function delete_terms_cache( $doc_id = 0 ) {
		$cache_key = SI_Payment_Terms::CACHE_KEY . $doc_id;
		delete_transient( $cache_key );
	}

	public static function remove_terms_from_history( $history, $doc_id, $filter ) {
		$doc_terms = self::get_payment_terms( $doc_id );
		$filtered = array_diff( $history, $doc_terms );
		return $filtered;
	}

	public static function load_addon_view( $view, $args, $allow_theme_override = true ) {
		add_filter( 'si_views_path', array( __CLASS__, 'addons_view_path' ) );
		$view = self::load_view( $view, $args, $allow_theme_override );
		remove_filter( 'si_views_path', array( __CLASS__, 'addons_view_path' ) );
		return $view;
	}

	protected static function load_addon_view_to_string( $view, $args, $allow_theme_override = true ) {
		ob_start();
		self::load_addon_view( $view, $args, $allow_theme_override );
		return ob_get_clean();
	}

	public static function addons_view_path() {
		return SA_ADDON_PAYMENT_TERMS_PATH . '/views/';
	}
}
