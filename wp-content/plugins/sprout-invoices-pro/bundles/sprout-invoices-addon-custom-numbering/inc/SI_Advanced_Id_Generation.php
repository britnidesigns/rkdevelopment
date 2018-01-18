<?php

/**
 * Controller
 * Adds meta boxes to client admin.
 */
class SI_Advanced_Id_Generation extends SI_Controller {
	const INV_FORMAT = 'si_invoice_ids_format';
	const EST_FORMAT = 'si_estimate_ids_format';
	const INV_START = 'si_ids_sequential_invoices_start';
	const EST_START = 'si_ids_sequential_estimates_start';
	const INV_PADD = 'si_ids_sequential_invoices_padd';
	const EST_PADD = 'si_ids_sequential_estimates_padd';
	private static $invoices_format;
	private static $estimates_format;
	private static $invoices_start;
	private static $estimates_start;
	private static $invoices_padd;
	private static $estimates_padd;

	public static function init() {

		self::$invoices_format = self::invoices_format();
		self::$estimates_format = self::estimates_format();
		self::$invoices_start = self::invoices_sequence_start();
		self::$estimates_start = self::estimates_sequence_start();
		self::$invoices_padd = self::invoices_sequence_padd();
		self::$estimates_padd = self::estimates_sequence_padd();

		// Add options
		self::register_settings();

		// Filter id in admin
		add_filter( 'load_view_args_admin/meta-boxes/invoices/information.php', array( __CLASS__, '_si_information_meta_box_args' ) );
		add_filter( 'load_view_args_admin/meta-boxes/estimates/information.php', array( __CLASS__, '_si_information_meta_box_args' ) );

		add_filter( 'si_default_invoice_title', array( __CLASS__, 'change_default_title_id' ), 100, 2 );
		add_filter( 'si_default_estimate_title', array( __CLASS__, 'change_default_title_id' ), 100, 2 );

		add_filter( 'si_adjust_cloned_invoice_id', array( __CLASS__, 'change_invoice_id_when_created_from_estimate' ), 10, 2 );

		add_action( 'si_cloned_post',  array( __CLASS__, 'change_id_when_cloned' ), 10, 3 );

		// WooCommerce integration
		add_action( 'si_woocommerce_payment',  array( __CLASS__, 'change_id_when_created_via_wc' ), 10, 2 );

		// Estimate submissions
		add_filter( 'estimate_submitted_from_adv_form', array( __CLASS__, 'change_estimate_id_when_created_from_inv_submission' ), 10, 2 );

		// Invoice submissions
		add_filter( 'invoice_submitted_from_adv_form', array( __CLASS__, 'change_invoice_id_when_created_from_inv_submission' ), 10, 2 );

	}

	public static function invoices_format() {
		$format = get_option( self::INV_FORMAT, '{id}' );
		return $format;
	}

	public static function estimates_format() {
		$format = get_option( self::EST_FORMAT, '{id}' );
		return $format;
	}

	public static function invoices_sequence_start() {
		$start = (int) get_option( self::INV_START, 0 );
		$padd = self::invoices_sequence_padd();
		if ( $padd > 0 ) {
			$start = str_pad( $start, $padd, '0', STR_PAD_LEFT );
		}
		return $start;
	}

	public static function estimates_sequence_start() {
		$start = (int) get_option( self::EST_START, 0 );
		$padd = self::estimates_sequence_padd();
		if ( $padd > 0 ) {
			$start = str_pad( $start, $padd, '0', STR_PAD_LEFT );
		}
		return $start;
	}

	public static function invoices_sequence_padd() {
		$padd = (int) get_option( self::INV_PADD, 0 );
		return $padd;
	}

	public static function estimates_sequence_padd() {
		$padd = (int) get_option( self::EST_PADD, 0 );
		return $padd;
	}


	////////////
	// admin //
	////////////

	/**
	 * Hooked on init add the settings page and options.
	 *
	 */
	public static function register_settings() {

		// Settings
		$settings = array(
			'advanced_numbering' => array(
				'title' => __( 'Advanced Numbering', 'sprout-invoices' ),
				'weight' => 1010, // Add-on settings are 1000 plus
				'tab' => 'settings',
				'callback' => array( __CLASS__, 'display_advanced_numbering_messaging' ),
				'settings' => array(
					self::INV_FORMAT => array(
						'label' => __( 'Invoice Numbering Format', 'sprout-invoices' ),
						'option' => array(
							'type' => 'input',
							'default' => self::invoices_format(),
							'description' => sprintf( __( 'Adjust the format with some dynamic tags. Example: PREFIX-{Y}-{m}-{d}-INV#{count}, would result in %s. %s', 'sprout-invoices' ), sprintf( '<code>PREFIX-%s-%s-%s-INV#142</code>', date_i18n( 'Y' ), date_i18n( 'm' ), date_i18n( 'd' ) ), self::display_dynamic_tags() ),
							),
						),
					self::INV_START => array(
						'label' => __( 'Invoice {seq} Start', 'sprout-invoices' ),
						'option' => array(
							'type' => 'small-input',
							'default' => self::invoices_sequence_start(),
							'description' => __( 'This is the current numeric value for the {seq} tag. If applied the value will increase with each use, i.e. this value will increase automatically.', 'sprout-invoices' ),
							),
						),
					self::INV_PADD => array(
						'label' => __( 'Invoice {seq} Length', 'sprout-invoices' ),
						'option' => array(
							'type' => 'small-input',
							'default' => self::invoices_sequence_padd(),
							'description' => __( 'The sequence length. So that the {seq} could be 00000123 and not lead to 123, this example uses a length of 8.', 'sprout-invoices' ),
							),
						),
					self::EST_FORMAT => array(
						'label' => __( 'Estimate Numbering Format', 'sprout-invoices' ),
						'option' => array(
							'type' => 'input',
							'default' => self::estimates_format(),
							'description' => __( 'Adjust the format with some dynamic tags.', 'sprout-invoices' ) . self::display_dynamic_tags(),
							),
						),
					self::EST_START => array(
						'label' => __( 'Estimate {seq} Start', 'sprout-invoices' ),
						'option' => array(
							'type' => 'small-input',
							'default' => self::estimates_sequence_start(),
							'description' => __( 'This is the current numeric value for the {seq} tag. If applied the value will increase with each use, i.e. this value will increase automatically.', 'sprout-invoices' ),
							),
						),
					self::EST_PADD => array(
						'label' => __( 'Estimate {seq} Length', 'sprout-invoices' ),
						'option' => array(
							'type' => 'small-input',
							'default' => self::estimates_sequence_padd(),
							'description' => __( 'The sequence length. So that the {seq} could be 00000123 and not lead to 123, this example uses a length of 8.', 'sprout-invoices' ),
							),
						),
					),
				),
			);
		do_action( 'sprout_settings', $settings, self::SETTINGS_PAGE );
	}

	public static function display_advanced_numbering_messaging() {
		printf( '<p>%s</p>', __( 'Below are some advanced options to change the numbering format of invoices and estimates. Make sure to read the description of each setting/option before saving.', 'sprout-invoices' ) );
	}

	public static function display_dynamic_tags( $context = '' ) {
		if ( '' === $context ) {
			$context = __( 'Invoice', 'sprout-invoices' );
		}
		$tags = '';
		foreach ( self::get_registered_tags() as $tag => $description ) {
			$tags .= sprintf( '<p><b>{%s}</b>&nbsp;&mdash;&nbsp;%s</p>', $tag, $description );
		}
		return $tags;
	}

	public static function get_registered_tags() {
		$tags = array();
		$tags['id'] = __( 'The default id, e.g. post_id.', 'sprout-invoices' );
		$tags['seq'] = __( 'Uses the sequential number option.', 'sprout-invoices' );
		$tags['count'] = __( 'Current count of invoices/estimates.', 'sprout-invoices' );
		$tags['count_y'] = __( 'Current count of invoices/estimates, for the current year.', 'sprout-invoices' );
		$tags['count_today'] = __( 'Current count of invoices/estimates, for the today.', 'sprout-invoices' );
		$tags['count_m'] = __( 'Current count of invoices/estimates, for the current month.', 'sprout-invoices' );
		$tags['Y'] = __( 'Current year (date_i18n() format).', 'sprout-invoices' );
		$tags['y'] = __( 'Current year (date_i18n() format).', 'sprout-invoices' );
		$tags['m'] = __( 'Current month (date_i18n() format).', 'sprout-invoices' );
		$tags['d'] = __( 'Current day (date_i18n() format).', 'sprout-invoices' );
		return apply_filters( 'si_advanced_numbering_formatting_tags', $tags );
	}

	/////////////////////////
	// Getting things done //
	/////////////////////////

	public static function filter_invoice_id( $invoice_id = 0 ) {
		$invoice = SI_Invoice::get_instance( $invoice_id );
		$filtered_id = str_replace(
			array(
				'{id}',
				'{count}',
				'{count_y}',
				'{count_today}',
				'{count_m}',
				'{Y}',
				'{y}',
				'{m}',
				'{d}',
			),
			array(
				( is_a( $invoice, 'SI_Invoice') ) ? $invoice->get_id() : 0,
				self::current_invoice_count( $invoice_id ),
				self::current_invoice_count( $invoice_id, 'current_year' ),
				self::current_invoice_count( $invoice_id, 'today' ),
				self::current_invoice_count( $invoice_id, 'current_month' ),
				date_i18n( 'Y' ),
				date_i18n( 'y' ),
				date_i18n( 'm' ),
				date_i18n( 'd' ),
			),
			self::$invoices_format );

		if ( strpos( $filtered_id, '{seq}' ) !== false ) {
			$sequential = self::kick_invoice_sequence( $invoice_id );
			$filtered_id = str_replace( '{seq}', $sequential, $filtered_id );
		}

		return apply_filters( 'si_filtered_invoice_id', $filtered_id, $invoice_id );
	}

	public static function filter_estimate_id( $estimate_id = 0 ) {
		$estimate = SI_Estimate::get_instance( $estimate_id );
		$filtered_id = str_replace(
			array(
				'{id}',
				'{count}',
				'{count_y}',
				'{count_today}',
				'{count_m}',
				'{Y}',
				'{y}',
				'{m}',
				'{d}',
				),
			array(
				$estimate->get_id(),
				self::current_estimate_count( $estimate_id ),
				self::current_estimate_count( $estimate_id, 'current_year' ),
				self::current_estimate_count( $estimate_id, 'today' ),
				self::current_estimate_count( $estimate_id, 'current_month' ),
				date_i18n( 'Y' ),
				date_i18n( 'y' ),
				date_i18n( 'm' ),
				date_i18n( 'd' ),
				),
			self::$estimates_format );

		if ( strpos( $filtered_id, '{seq}' ) !== false ) {
			$sequential = self::kick_estimate_sequence( $estimate_id );
			$filtered_id = str_replace( '{seq}', $sequential, $filtered_id );
		}

		return apply_filters( 'si_filtered_estimate_id', $filtered_id, $estimate_id );
	}

	public static function _si_information_meta_box_args( $args = '' ) {
		if ( 'auto-draft' === $args['post']->post_status ) { // only adjust drafts
			if ( SI_Invoice::POST_TYPE === $args['post']->post_type ) {
				$args['invoice_id'] = self::filter_invoice_id( $args['invoice_id'] );
			}
			if ( SI_Estimate::POST_TYPE === $args['post']->post_type ) {
				$args['estimate_id'] = self::filter_estimate_id( $args['estimate_id'] );
			}
		}
		return $args;
	}

	public static function change_default_title_id( $title = '', $doc = '' ) {
		if ( is_a( $doc, 'SI_Invoice' ) ) {
			$id = self::filter_invoice_id( $doc->get_id() );
		}
		if ( is_a( $doc, 'SI_Estimate' ) ) {
			$id = self::filter_estimate_id( $doc->get_id() );
		}
		return $id;
	}

	public static function change_id_when_cloned( $new_post_id = 0, $cloned_post_id = 0, $new_post_type = '' ) {

		// estimate to invoice clone handled with change_invoice_id_when_created_from_estimate
		if ( get_post_type( $cloned_post_id ) !== $new_post_type ) {
			return;
		}

		$cloned_doc = si_get_doc_object( $cloned_post_id );
		$doc = si_get_doc_object( $new_post_id );

		if ( is_a( $doc, 'SI_Invoice' ) ) {
			$new_id = self::filter_invoice_id( $new_post_id );
			$doc->set_invoice_id( $new_id );

			$clonded_id = $cloned_doc->get_invoice_id();
			$invoice_title = $doc->get_title();
			// Does new invoice title have an old id
			if ( strpos( $invoice_title, $clonded_id ) !== false ) {
				$new_invoice_title = str_replace( $clonded_id, $new_id, $invoice_title );
				if ( $invoice_title !== $new_invoice_title ) {
					$doc->set_title( $new_invoice_title );
				}
			}

		}
		elseif ( is_a( $doc, 'SI_Estimate' ) ) {
			$new_id = self::filter_estimate_id( $new_post_id );
			$doc->set_estimate_id( $new_id );

			$clonded_id = $cloned_doc->get_estimate_id();
			$estimate_title = $doc->get_title();
			// Does new invoice title have an old id
			if ( strpos( $estimate_title, $clonded_id ) !== false ) {
				$new_est_title = str_replace( $clonded_id, $new_id, $estimate_title );
				if ( $estimate_title !== $new_est_title ) {
					$doc->set_title( $new_est_title );
				}
			}

		}

	}

	public static function change_id_when_created_via_wc( $order_id = 0, $invoice_id = 0 ) {

		$doc = si_get_doc_object( $invoice_id );
		if ( is_a( $doc, 'SI_Invoice' ) ) {
			$new_id = self::filter_invoice_id( $invoice_id );
			$doc->set_invoice_id( $new_id );
		}

	}

	public static function change_invoice_id_when_created_from_estimate( $id = '', $invoice_id = 0 ) {
		$invoice = SI_Invoice::get_instance( $invoice_id );
		if ( ! is_a( $invoice, 'SI_Invoice' ) ) {
			return $id;
		}
		$id = self::filter_invoice_id( $invoice_id );
		return $id;
	}

	public static function change_estimate_id_when_created_from_inv_submission( $estimate, $args = array() ) {
		if ( ! is_a( $estimate, 'SI_Estimate' ) ) {
			return;
		}
		$new_id = self::filter_estimate_id( $estimate->get_id() );
		return $estimate->set_estimate_id( $new_id );
	}

	public static function change_invoice_id_when_created_from_inv_submission( $invoice, $args = array() ) {
		if ( ! is_a( $invoice, 'SI_Invoice' ) ) {
			return;
		}
		$new_id = self::filter_invoice_id( $invoice->get_id() );
		return $invoice->set_invoice_id( $new_id );
	}

	public static function kick_invoice_sequence( $invoice_id = 0 ) {
		$start = self::invoices_sequence_start();

		$log = get_option( self::INV_START . '_log', array() );
		if ( ! in_array( $invoice_id, $log ) ) {
			// kick the sequence
			update_option( self::INV_START, (int) $start+1 );
			// log invoice id to prevent recurring
			$log[] = $invoice_id;
			update_option( self::INV_START . '_log', $log );
		}
		return $start;
	}

	public static function kick_estimate_sequence( $estimate_id = 0 ) {
		$start = self::estimates_sequence_start();
		$log = get_option( self::EST_START . '_log', array() );
		if ( ! in_array( $estimate_id, $log ) ) {
			// kick the sequence
			update_option( self::EST_START, (int) $start+1 );
			// log estimate id to prevent recurring
			$log[] = $estimate_id;
			update_option( self::EST_START . '_log', $log );
		}
		return $start;
	}

	public static function current_invoice_count( $invoice_id = 0, $period = '' ) {
		global $wpdb;
		$query = $wpdb->prepare( "SELECT ID FROM {$wpdb->posts} WHERE post_type = %s AND post_status != %s AND ID != %s", SI_Invoice::POST_TYPE, 'auto-draft', $invoice_id );
		if ( 'current_year' === $period ) {
			$query .= $wpdb->prepare( " AND DATE_FORMAT( post_date, %s ) = %s", '%Y', date( 'Y', time() ) );
		} elseif ( 'current_month' === $period ) {
			$query .= $wpdb->prepare( " AND DATE_FORMAT( post_date, %s ) = %s", '%Y-%m', date( 'Y-m', time() ) );
		} elseif ( 'today' === $period ) {
			$query .= $wpdb->prepare( " AND DATE_FORMAT( post_date, %s ) = %s", '%Y-%m-%d', date( 'Y-m-d', time() ) );
		}
		$invoices = $wpdb->get_col( $query );
		$number_of_invoices = (int) count( $invoices );
		return apply_filters( 'si_adv_id_current_invoice_count', $number_of_invoices, $invoice_id, $period );
	}

	public static function current_estimate_count( $estimate_id = 0, $period = '' ) {
		global $wpdb;
		$query = $wpdb->prepare( "SELECT ID FROM {$wpdb->posts} WHERE post_type = %s AND post_status != %s AND ID != %s", SI_Estimate::POST_TYPE, 'auto-draft', $estimate_id );
		if ( 'current_year' === $period ) {
			$query .= $wpdb->prepare( " AND DATE_FORMAT( post_date, %s ) = %s", '%Y', date( 'Y', time() ) );
		} elseif ( 'current_month' === $period ) {
			$query .= $wpdb->prepare( " AND DATE_FORMAT( post_date, %s ) = %s", '%Y-%m', date( 'Y-m', time() ) );
		} elseif ( 'today' === $period ) {
			$query .= $wpdb->prepare( " AND DATE_FORMAT( post_date, %s ) = %s", '%Y-%m-%d', date( 'Y-m-d', time() ) );
		}
		$estimates = $wpdb->get_col( $query );
		$number_of_estimates = (int) count( $estimates );
		return apply_filters( 'si_adv_id_current_estimate_count', $number_of_estimates, $estimate_id, $period );
		
	}
}
SI_Advanced_Id_Generation::init();