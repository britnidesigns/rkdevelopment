<?php


/**
 * Clients Controller
 *
 *
 * @package Sprout_Invoice
 * @subpackage Clients
 */
class SI_Invoices_Premium extends SI_Invoices {

	public static function init() {

		if ( is_admin() ) {
			// Meta boxes
			add_action( 'admin_init', array( __CLASS__, 'register_meta_boxes' ) );
			add_action( 'do_meta_boxes', array( 'SI_Invoices_Edit', 'modify_meta_boxes' ) );

			// Deposits
			add_action( 'invoice_meta_saved', array( __CLASS__, 'save_deposits' ), 10, 1 );
		}

		// Unique urls
		remove_filter( 'wp_unique_post_slug', array( 'SI_Invoices', 'post_slug' ), 10, 4 );
		add_filter( 'wp_unique_post_slug', array( __CLASS__, 'private_unique_post_slug' ), 10, 4 );

		// Mark invoice viewed
		add_action( 'invoice_viewed',  array( __CLASS__, 'maybe_log_invoice_view' ) );

		// Upgrade messaging
		add_filter( 'show_upgrade_messaging', '__return_false' );
	}


	/////////////////
	// Meta boxes //
	/////////////////

	/**
	 * Regsiter meta boxes for invoice editing.
	 *
	 * @return
	 */
	public static function register_meta_boxes() {
		// invoice specific
		$args = array(
			'si_invoice_history' => array(
				'title' => __( 'Invoice History', 'sprout-invoices' ),
				'show_callback' => array( __CLASS__, 'show_submission_history_view' ),
				'save_callback' => array( __CLASS__, '_save_null' ),
				'context' => 'normal',
				'priority' => 'low',
			),
		);
		do_action( 'sprout_meta_box', $args, SI_Invoice::POST_TYPE );
	}

	/**
	 * Show the invoice history, including the submission fields
	 *
	 * @param WP_Post $post
	 * @param array   $metabox
	 * @return
	 */
	public static function show_submission_history_view( $post, $metabox ) {
		if ( $post->post_status == 'auto-draft' ) {
			printf( '<p>%s</p>', __( 'No history available.', 'sprout-invoices' ) );
			return;
		}
		$invoice = SI_Invoice::get_instance( $post->ID );
		self::load_view( 'admin/meta-boxes/invoices/history-premium', array(
				'id' => $post->ID,
				'post' => $post,
				'invoice' => $invoice,
				'history' => si_doc_history_records( $post->ID, false ),
				'submission_fields' => $invoice->get_submission_fields(),
		), false );
	}


	/////////////////////
	// Record Keeping //
	/////////////////////


	public static function maybe_log_invoice_view() {
		global $post;

		if ( ! is_single() ) {
			return; }

		// Make sure this is an estimate we're viewing
		if ( $post->post_type != SI_Invoice::POST_TYPE ) {
			return; }

		// Don't log the authors views
		if ( $post->post_author == get_current_user_id() ) {
			return; }

		if ( is_user_logged_in() ) {
			$user = get_userdata( get_current_user_id() );
			$name = $user->first_name . ' ' . $user->last_name;
			$whom = $name . ' (' . $user->user_login. ')';
		} else {
			$whom = self::get_user_ip();
		}
		if ( in_array( $whom, array( '127.0.0.1', '::1' ) ) ) {
			return;
		}

		$invoice = SI_Invoice::get_instance( $post->ID );
		$title = sprintf( __( 'Invoice viewed by %s for the first time.', 'sprout-invoices' ), esc_html( $whom ) );

		$found = false;
		$view_logs = SI_Record::get_records_by_type_and_association( $invoice->get_id(), self::VIEWED_STATUS_UPDATE );
		foreach ( $view_logs as $record_id ) {
			if ( ! $found && $title == get_the_title( $record_id ) ) {
				$found = true;
			}
		}
		// Record exists
		if ( $found ) {
			return;
		}

		do_action( 'si_new_record',
			$_SERVER,
			self::VIEWED_STATUS_UPDATE,
			$invoice->get_id(),
		$title );
	}

	////////////
	// Misc. //
	////////////

	public static function save_deposits( $invoice ) {
		if ( ! is_a( $invoice, 'SI_Invoice' ) ) {
			return;
		}
		$deposit = ( isset( $_POST['deposit'] ) && '' !== $_POST['deposit'] ) ? $_POST['deposit'] : '' ;
		$invoice->set_deposit( $deposit );
	}

	/**
	 * Filter the unique post slug.
	 *
	 * @param string $slug          The post slug.
	 * @param int    $post_ID       Post ID.
	 * @param string $post_status   The post status.
	 * @param string $post_type     Post type.
	 * @param int    $post_parent   Post parent ID
	 * @param string $original_slug The original post slug.
	 */
	public static function private_unique_post_slug( $slug, $post_ID, $post_status, $post_type ) {
		$hashed_post_slug = wp_hash( $slug . microtime() );
		// possibly cloned
		if ( $post_type == SI_Invoice::POST_TYPE && false !== strpos( $slug, '-2' ) ) {
			return $hashed_post_slug; // add microtime to be unique
		}
		// Change every post that has auto-draft
		if ( false !== strpos( $slug, __( 'auto-draft' ) ) ) {
			return $hashed_post_slug; // add microtime to be unique
		}
		 // don't change on front-end edits.
		if ( in_array( $post_status, array( SI_Invoice::STATUS_PENDING, SI_Invoice::STATUS_PARTIAL, SI_Invoice::STATUS_PAID, SI_Invoice::STATUS_WO ) ) || apply_filters( 'si_is_invoice_currently_custom_status', $post_ID ) ) {
			return $slug;
		}
		// make sure it's a new post
		if ( ( ! isset( $_POST['post_name'] ) || $_POST['post_name'] == '' ) && $post_type == SI_Invoice::POST_TYPE ) {
			return $hashed_post_slug; // add microtime to be unique
		}
		return $slug;
	}
}
