<?php

/**
 * Time_Tracking Controller
 *
 * @package Sprout_Invoice
 * @subpackage Time_Tracking
 */
class SI_Invoices_Recurring extends SI_Controller {

	protected static $meta_keys = array(
		'is_recurring' => '_is_recurring_invoice', // bool
		'start_time' => '_recurring_start_time', // int
		'frequency' => '_recurring_option', // string
		'frequency_custom' => '_recurring_custom_days', // int
		'clone_time' => '_next_clone_time', // int
		'cloned_from' => '_cloned_parent_invoice_id', // int
		'duration' => '_time_before_stop', // int
	);

	public static function init() {
		SI_Invoices_Recurring_Admin::init();
		SI_Invoices_Recurring_Settings::init();
		SI_Invoices_Recurring_Tasks::init();
		SI_Invoices_Recurring_Addons::init();
	}

	///////////
	// Meta //
	///////////

	/**
	 * Is Recurring
	 */
	public static function is_recurring( $doc_id = 0 ) {
		$doc = si_get_doc_object( $doc_id );
		if ( ! is_object( $doc ) ) {
			return 0;
		}
		$bool = $doc->get_post_meta( self::$meta_keys['is_recurring'] );
		if ( $bool != 1 ) {
			$bool = false;
		}
		return $bool;
	}

	public static function set_recurring( $doc_id = 0 ) {
		$doc = si_get_doc_object( $doc_id );
		if ( ! is_object( $doc ) ) {
			return 0;
		}
		$doc->save_post_meta( array(
			self::$meta_keys['is_recurring'] => 1,
		) );
		return 1;
	}

	public static function set_as_not_recurring( $doc_id = 0 ) {
		$doc = si_get_doc_object( $doc_id );
		if ( ! is_object( $doc ) ) {
			return 0;
		}
		$doc->save_post_meta( array(
			self::$meta_keys['is_recurring'] => 0,
		) );
		return 1;
	}

	/**
	 * Issue date
	 */
	public static function get_start_time( $doc_id = 0 ) {
		$doc = si_get_doc_object( $doc_id );
		if ( ! is_object( $doc ) ) {
			return 0;
		}
		$time = (int) $doc->get_post_meta( self::$meta_keys['start_time'] );
		return $time;
	}

	public static function set_start_time( $doc_id = 0, $start_time = 0 ) {
		$doc = si_get_doc_object( $doc_id );
		if ( ! is_object( $doc ) ) {
			return 0;
		}
		$doc->save_post_meta( array(
			self::$meta_keys['start_time'] => $start_time,
		) );

		// force the reset of the clone time
		self::set_clone_time( $doc_id );

		return $start_time;
	}

	/**
	 * Start Time
	 */
	public static function get_frequency( $doc_id = 0 ) {
		$doc = si_get_doc_object( $doc_id );
		if ( ! is_object( $doc ) ) {
			return 0;
		}
		$freq = $doc->get_post_meta( self::$meta_keys['frequency'] );
		return $freq;
	}

	public static function set_frequency( $doc_id = 0, $frequency = '' ) {
		$doc = si_get_doc_object( $doc_id );
		if ( ! is_object( $doc ) ) {
			return 0;
		}
		$doc->save_post_meta( array(
			self::$meta_keys['frequency'] => $frequency,
		) );
		return $frequency;
	}

	/**
	 * Frequency in Days
	 */
	public static function get_frequency_custom( $doc_id = 0 ) {
		$doc = si_get_doc_object( $doc_id );
		if ( ! is_object( $doc ) ) {
			return 0;
		}
		$days = (int) $doc->get_post_meta( self::$meta_keys['frequency_custom'] );
		return $days;
	}

	public static function set_frequency_custom( $doc_id = 0, $days = 0 ) {
		$doc = si_get_doc_object( $doc_id );
		if ( ! is_object( $doc ) ) {
			return 0;
		}
		$doc->save_post_meta( array(
			self::$meta_keys['frequency_custom'] => $days,
		) );
		return $days;
	}

	/**
	 * Total times
	 */
	public static function get_duration( $doc_id = 0 ) {
		$doc = si_get_doc_object( $doc_id );
		if ( ! is_object( $doc ) ) {
			return 0;
		}
		$duration = (int) $doc->get_post_meta( self::$meta_keys['duration'] );
		return $duration;
	}

	public static function set_duration( $doc_id = 0, $duration = 0 ) {
		$doc = si_get_doc_object( $doc_id );
		if ( ! is_object( $doc ) ) {
			return 0;
		}
		$doc->save_post_meta( array(
			self::$meta_keys['duration'] => (int) $duration,
		) );
		return $duration;
	}


	/**
	 * Issue date
	 */
	public static function was_cloned( $invoice_id = 0 ) {
		return self::get_cloned( $invoice_id );
	}

	public static function get_cloned( $invoice_id = 0 ) {
		$parent = 0;
		$invoice = SI_Invoice::get_instance( $invoice_id );
		if ( ! is_a( $invoice, 'SI_Invoice' ) ) {
			return 0;
		}
		$cloned_from = $invoice->get_post_meta( self::$meta_keys['cloned_from'] );
		if ( get_post_type( $cloned_from ) === SI_Invoice::POST_TYPE ) {
			$parent = $cloned_from;
		}
		return $parent;
	}

	public static function set_parent( $invoice_id = 0, $parent = 0 ) {
		$invoice = SI_Invoice::get_instance( $invoice_id );
		if ( ! is_a( $invoice, 'SI_Invoice' ) ) {
			return 0;
		}
		$invoice->save_post_meta( array(
			self::$meta_keys['cloned_from'] => $parent,
		) );
		return $parent;
	}

	/**
	 * The next time this invoice will be cloned
	 */
	public static function get_clone_time( $invoice_id = 0 ) {
		$invoice = SI_Invoice::get_instance( $invoice_id );
		if ( ! is_a( $invoice, 'SI_Invoice' ) ) {
			return 0;
		}
		if ( ! self::is_recurring( $invoice_id ) ) {
			return 0;
		}
		$time = (int) $invoice->get_post_meta( self::$meta_keys['clone_time'] );
		return $time;
	}

	public static function set_clone_time( $invoice_id = 0 ) {
		$invoice = SI_Invoice::get_instance( $invoice_id );

		if ( ! is_a( $invoice, 'SI_Invoice' ) ) {
			return 0;
		}

		if ( ! self::is_recurring( $invoice_id ) ) {
			$clone_time = 0;
		} else {
			$clone_time = self::get_next_clone_time( $invoice_id );
		}

		$invoice->save_post_meta( array(
			self::$meta_keys['clone_time'] => $clone_time,
		) );
		return $clone_time;
	}

	public static function get_next_clone_time( $invoice_id = 0 ) {

		$start_time = self::get_start_time( $invoice_id );
		$amt_children = count( self::get_child_clones( $invoice_id ) );

		// if their have been no cloned invoices than use the initial start date.
		if ( 0 === $amt_children ) {
			return $start_time;
		}

		$frequency = self::get_frequency( $invoice_id );

		switch ( $frequency ) {
			case 'weekly':
				$amt_string = '+' . $amt_children . ' Week';
				$clone_time = strtotime( $amt_string, $start_time );
				break;
			case 'monthly':
				$amt_string = '+' . $amt_children . ' Month';
				$clone_time = strtotime( $amt_string, $start_time );
				break;
			case 'quarterly':
				$amt_string = '+' . $amt_children * 3 . ' Month';
				$clone_time = strtotime( $amt_string, $start_time );
				break;
			case 'yearly':
				$amt_string = '+' . $amt_children * 3 . ' Year';
				$clone_time = strtotime( $amt_string, $start_time );
				break;
			case 'custom':
				$amt_string = '+' . self::get_frequency_custom( $invoice_id ) * $amt_children . ' days';
				$clone_time = strtotime( $amt_string, $start_time );
				break;

			default:
				$clone_time = 0;
				break;
		}

		return apply_filters( 'si_recurring_set_clone_time', $clone_time, $frequency, $start_time, $invoice_id );
	}

	//////////////
	// Utility //
	//////////////

	public static function get_child_clones( $invoice_id = 0 ) {
		$invoice_ids = SI_Post_Type::find_by_meta( SI_Invoice::POST_TYPE, array( self::$meta_keys['cloned_from'] => $invoice_id ) );
		return $invoice_ids;
	}

	public static function load_addon_view( $view, $args, $allow_theme_override = true ) {
		add_filter( 'si_views_path', array( __CLASS__, 'addons_view_path' ) );
		$view = self::load_view( $view, $args, $allow_theme_override );
		remove_filter( 'si_views_path', array( __CLASS__, 'addons_view_path' ) );
		return $view;
	}

	public static function addons_view_path() {
		return SA_ADDON_RECURRING_INVOICES_PATH . '/views/';
	}
}
