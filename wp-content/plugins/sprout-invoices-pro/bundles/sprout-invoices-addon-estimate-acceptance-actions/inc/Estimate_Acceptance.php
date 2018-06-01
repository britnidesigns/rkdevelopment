<?php

/**
 * @package Sprout_Invoices
 * @subpackage Admin_Filtering
 */
class SI_Estimate_Acceptance extends SI_Controller {

	private static $meta_keys = array(
		'disable_invoice_creation' => '_disable_invoice_creation', // bool
		'will_redirect' => '_will_redirect_to_estimate', // bool
	);

	public static function init() {
		if ( is_admin() ) {
			// Meta boxes
			add_action( 'admin_init', array( __CLASS__, 'register_meta_boxes' ), 100 );
		}

		add_filter( 'si_disable_create_invoice_on_est_acceptance', array( __CLASS__, 'maybe_disable_invoice_creation' ), 20, 2 );

		add_action( 'si_footer', array( __CLASS__, 'si_redirect_after_estimate_approval' ) );
		add_action( 'pre_si_estimate_view', array( __CLASS__, 'si_maybe_redirect_after_estimate_approval' ) );
	}


	/////////////////
	// Meta boxes //
	/////////////////

	/**
	 * Regsiter meta boxes for estimate editing.
	 *
	 * @return
	 */
	public static function register_meta_boxes() {
		// estimate specific
		$args = array(
			'si_estimate_acceptance' => array(
				'title' => __( 'Estimate Acceptance Actions', 'sprout-invoices' ),
				'show_callback' => array( __CLASS__, 'show_acceptance_options' ),
				'save_callback' => array( __CLASS__, 'save_acceptance_options' ),
				'context' => 'normal',
				'priority' => 'low',
				'weight' => 50,
			),
		);
		do_action( 'sprout_meta_box', $args, SI_Estimate::POST_TYPE );
	}

	/**
	 * Show time tracking metabox.
	 * @param  WP_Post $post
	 * @param  array $metabox
	 * @return
	 */
	public static function show_acceptance_options( $post, $metabox ) {
		$estimate_id = $post->ID;
		$estimate = SI_Estimate::get_instance( $estimate_id );

		self::load_addon_view( 'admin/meta-boxes/estimates/acceptance', array(
			'fields' => self::meta_box_fields( $estimate_id ),
			'estimate_id' => $estimate_id,
		), false );

	}

	public static function meta_box_fields( $estimate_id ) {

		$dont_create_invoice = self::disabled_invoice_creation( $estimate_id );
		$fields['dont_create_invoice'] = array(
			'weight' => 10,
			'label' => __( 'Disable Invoice Creation', 'sprout-invoices' ),
			'type' => 'checkbox',
			'default' => $dont_create_invoice,
			'value' => '1',
			'description' => __( 'After an estimate is accepted an invoice is created. Checking this option will disable that action.', 'sprout-invoices' ),
		);

		$do_redirection = self::will_redirect( $estimate_id );
		$fields['will_redirect'] = array(
			'weight' => 20,
			'label' => __( 'Redirect to Invoice', 'sprout-invoices' ),
			'type' => 'checkbox',
			'default' => $do_redirection,
			'value' => '1',
			'description' => __( 'After an estimate is accepted an invoice is created. This will redirect the client to the new invoice.', 'sprout-invoices' ),
		);

		$deposit = self::get_deposit( $estimate_id );
		$fields['deposit'] = array(
			'weight' => 30,
			'label' => __( 'Invoice Deposit', 'sprout-invoices' ),
			'type' => 'input',
			'default' => $deposit,
			'placeholder' => 0,
			'attributes' => array( 'class' => 'small-input' ),
			'description' => __( 'This will be used when the invoice is created to set the deposit for the initial payment.', 'sprout-invoices' ),
		);

		$fields = apply_filters( 'si_estimate_acceptance_fields', $fields );
		uasort( $fields, array( __CLASS__, 'sort_by_weight' ) );
		return $fields;
	}


	public static function save_acceptance_options( $post_id, $post, $callback_args, $invoice_id = null ) {

		$deposit = ( isset( $_POST['sa_estimate_acceptance_deposit'] ) ) ? $_POST['sa_estimate_acceptance_deposit'] : '' ;
		self::set_deposit( $post_id, $deposit );

		$enabled_invoice_creation = true;
		self::enable_invoice_creation( $post_id );
		if ( isset( $_POST['sa_estimate_acceptance_dont_create_invoice'] ) && $_POST['sa_estimate_acceptance_dont_create_invoice'] ) {
			self::disable_invoice_creation( $post_id );
			$enabled_invoice_creation = false;
		}

		self::set_to_not_redirect( $post_id );
		if ( $enabled_invoice_creation ) {
			if ( isset( $_POST['sa_estimate_acceptance_will_redirect'] ) && $_POST['sa_estimate_acceptance_will_redirect'] ) {
				self::set_redirect( $post_id );
			}
		}

		do_action( 'save_estimate_acceptance_meta', $post_id );
	}

	/////////////
	// options //
	/////////////

	public static function disabled_invoice_creation( $estimate_id = 0 ) {
		$estimate = SI_Estimate::get_instance( $estimate_id );
		if ( ! is_a( $estimate, 'SI_Estimate' ) ) {
			return 0;
		}
		$bool = $estimate->get_post_meta( self::$meta_keys['disable_invoice_creation'] );
		if ( $bool != 1 ) {
			$bool = false;
		}
		return $bool;
	}

	public static function enable_invoice_creation( $estimate_id = 0 ) {
		$estimate = SI_Estimate::get_instance( $estimate_id );
		if ( ! is_a( $estimate, 'SI_Estimate' ) ) {
			return 0;
		}
		$estimate->save_post_meta( array(
			self::$meta_keys['disable_invoice_creation'] => 0,
		) );
		return 1;
	}

	public static function disable_invoice_creation( $estimate_id = 0 ) {
		$estimate = SI_Estimate::get_instance( $estimate_id );
		if ( ! is_a( $estimate, 'SI_Estimate' ) ) {
			return 0;
		}
		$estimate->save_post_meta( array(
			self::$meta_keys['disable_invoice_creation'] => 1,
		) );
		return 1;
	}

	public static function will_redirect( $estimate_id = 0 ) {
		$estimate = SI_Estimate::get_instance( $estimate_id );
		if ( ! is_a( $estimate, 'SI_Estimate' ) ) {
			return 0;
		}
		$bool = $estimate->get_post_meta( self::$meta_keys['will_redirect'] );
		if ( $bool != 1 ) {
			$bool = apply_filters( 'si_estimate_acceptance_default_redirect', false );
		}
		return $bool;
	}

	public static function set_redirect( $estimate_id = 0 ) {
		$estimate = SI_Estimate::get_instance( $estimate_id );
		if ( ! is_a( $estimate, 'SI_Estimate' ) ) {
			return 0;
		}
		$estimate->save_post_meta( array(
			self::$meta_keys['will_redirect'] => 1,
		) );
		return 1;
	}

	public static function set_to_not_redirect( $estimate_id = 0 ) {
		$estimate = SI_Estimate::get_instance( $estimate_id );
		if ( ! is_a( $estimate, 'SI_Estimate' ) ) {
			return 0;
		}
		$estimate->save_post_meta( array(
			self::$meta_keys['will_redirect'] => 0,
		) );
		return 1;
	}

	public static function set_deposit( $estimate_id = 0, $deposit = 0 ) {
		update_post_meta( $estimate_id, '_deposit', $deposit );
	}

	public static function get_deposit( $estimate_id = 0 ) {
		$meta = get_post_meta( $estimate_id, '_deposit', true );
		return $meta;
	}

	//////////////////////////////
	// Disable invoice creation //
	//////////////////////////////

	public static function maybe_disable_invoice_creation( $bool, SI_Estimate $estimate ) {
		if ( self::disabled_invoice_creation( $estimate->get_id() ) ) {
			$bool = true;
		}
		return $bool;
	}

	/////////////////
	// redirection //
	/////////////////

	public static function si_redirect_after_estimate_approval() {
		if ( ! self::will_redirect( get_the_id() ) ) {
			return;
		}
		if ( 'estimate' !== si_get_doc_context() ) {
				return;
		}
		?>
			<script type="text/javascript">
				jQuery(document).on('status_updated', function(e) {
					window.location = window.location.pathname + '?redirect_after_status=1';
				});
			</script>
		<?php
	}

	public static function si_maybe_redirect_after_estimate_approval() {
		if ( ! self::will_redirect( get_the_id() ) ) {
			return;
		}
		if ( 'estimate' !== si_get_doc_context() ) {
			return;
		}
		if ( ! isset( $_GET['redirect_after_status'] ) && ! isset( $_GET['signed'] ) ) {
			return;
		}
		$estimate = si_get_doc_object();
		$status = $estimate->get_status();
		// Check if approved
		if ( SI_Estimate::STATUS_APPROVED !== $status ) {
			return;
		}
		$invoice_id = $estimate->get_invoice_id();
		if ( ! $invoice_id ) {
			return;
		}

		// Set to pending payment, otherwise redirect will result in another redirect to the homepage
		$invoice = SI_Invoice::get_instance( $invoice_id );
		$invstatus = $invoice->get_status();
		// Check if approved
		if ( SI_Invoice::STATUS_TEMP === $invstatus ) {
			$invoice->set_pending();
		}

		// redirect
		wp_redirect( get_permalink( $invoice_id ) );
		exit();
	}


	//////////////
	// Utility //
	//////////////

	public static function load_addon_view( $view, $args, $allow_theme_override = true ) {
		add_filter( 'si_views_path', array( __CLASS__, 'addons_view_path' ) );
		$view = self::load_view( $view, $args, $allow_theme_override );
		remove_filter( 'si_views_path', array( __CLASS__, 'addons_view_path' ) );
		return $view;
	}

	public static function addons_view_path() {
		return SA_ADDON_ESTIMATE_ACCEPTANCE_PATH . '/views/';
	}
}
