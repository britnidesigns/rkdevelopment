<?php

/**
 * Time_Tracking Controller
 *
 * @package Sprout_Invoice
 * @subpackage Time_Tracking
 */
class SI_Invoices_Recurring_Admin extends SI_Invoices_Recurring {

	public static function init() {

		if ( is_admin() ) {

			// Invoice admin table
			add_action( 'restrict_manage_posts', array( __CLASS__, 'show_recurring_invoices' ) );
			add_action( 'query_vars', array( __CLASS__, 'filter_admin_table_register_qvs' ) );
			add_action( 'pre_get_posts', array( __CLASS__, 'filter_admin_table_results' ), 100 );

			// admin column info
			add_action( 'si_status_change_drop_outside', array( __CLASS__, 'add_recurring_info' ) );

		}

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

	///////////////////
	// Admin Columns //
	///////////////////

	public static function add_recurring_info( $doc_id = 0 ) {
		if ( ! isset( $_GET['post_type'] ) ) {
			return;
		}
		$invoice = SI_Invoice::get_instance( $doc_id );

		$cloned_id = self::get_cloned( $doc_id );
		if ( $cloned_id ) {

			// Compat with subscription payments
			$cloned_invoice = SI_Invoice::get_instance( $cloned_id );
			$cloned_recurring_payment = SI_Payment_Processors::get_recurring_payment( $cloned_invoice );
			if ( $cloned_recurring_payment ) {
				printf( __( '<div class="si_recurring_status">%s&nbsp;<a href="%s">%s</a></div>', 'sprout-invoices' ), '<span class="dashicons dashicons-backup"></span>', get_edit_post_link( $cloned_id ), get_the_title( $cloned_id ) );
			} else {
				printf( __( '<div class="si_recurring_status">%s&nbsp;<a href="%s">%s</a></div>', 'sprout-invoices' ), '<span class="dashicons dashicons-clock"></span>', get_edit_post_link( $cloned_id ), get_the_title( $cloned_id ) );
			}
		}
	}

	public static function show_recurring_invoices() {
		if ( SI_Invoice::POST_TYPE !== get_post_type() ) {
			return;
		}
		printf( '<span class="si_admin_show_recurring"><input type="checkbox" name="%1$s" id="%1$s" value="%2$s" %3$s /> <label for="%1$s" >%4$s</label></span>', 'filter_recurring', true, checked( true, isset( $_GET['filter_recurring'] ), false ), __( 'Show Recurring', 'sprout-invoices' ) );
	}

	public static function filter_admin_table_register_qvs( $query_vars ) {
		$query_vars[] = 'filter_recurring';
		return $query_vars;
	}

	public static function filter_admin_table_results( $query ) {
		if ( defined( 'DOING_AJAX' ) && DOING_AJAX ) {
			return;
		}
		if ( is_admin() && SI_Invoice::POST_TYPE !== $query->query['post_type'] ) {
			return;
		}

		$filter_recurring = $query->get( 'filter_recurring' );
		if ( ! $filter_recurring ) {
			return;
		}

		global $wpdb;
		// Make sure to accommodate the other post__in queries along with.
		$posts_in = $query->get( 'post__in' );
		$and_posts_in = '';
		if ( ! empty( $posts_in ) ) {
			$and_posts_in = sprintf( "AND $wpdb->posts.ID IN ( %s )", implode( ',', array_map( 'absint', $posts_in ) ) );
		}
		// get all the post ids that are auto billable.
		$ids = $wpdb->get_col( $wpdb->prepare( "
			SELECT ID
			FROM $wpdb->posts
			INNER JOIN $wpdb->postmeta ON ( $wpdb->posts.ID = $wpdb->postmeta.post_id ) WHERE 1=1 
			AND ( ( $wpdb->postmeta.meta_key = '%s' AND CAST( $wpdb->postmeta.meta_value AS CHAR ) = %s ) )
			AND $wpdb->posts.post_type = %s
			$and_posts_in
			ORDER BY $wpdb->postmeta.meta_value DESC
		", self::$meta_keys['is_recurring'], 1, SI_Invoice::POST_TYPE ) );

		// If there are no results don't pass an empty array, otherwise WP will return all.
		if ( empty( $ids ) ) {
			$ids = array( 0 );
		}
		// Set to certain posts
		$query->set( 'post__in', $ids );
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
