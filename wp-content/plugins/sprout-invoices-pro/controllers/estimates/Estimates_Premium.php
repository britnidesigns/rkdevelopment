<?php

/**
 * Estimates Controller
 *
 *
 * @package Sprout_Invoice
 * @subpackage Estimates
 */
class SI_Estimates_Premium extends SI_Estimates {

	public static function init() {

		if ( is_admin() ) {
			// Meta boxes
			add_action( 'admin_init', array( __CLASS__, 'register_meta_boxes' ) );
		}

		// Unique urls
		remove_filter( 'wp_unique_post_slug', array( 'SI_Estimates', 'post_slug' ), 10, 4 );
		add_filter( 'wp_unique_post_slug', array( __CLASS__, 'private_unique_post_slug' ), 10, 4 );

		// Upgrade messaging
		add_filter( 'show_upgrade_messaging', '__return_false' );
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
			'si_estimate_history' => array(
				'title' => __( 'Estimate History', 'sprout-invoices' ),
				'show_callback' => array( __CLASS__, 'show_submission_history_view' ),
				'save_callback' => array( __CLASS__, '_save_null' ),
				'context' => 'normal',
				'priority' => 'low',
			),
		);
		do_action( 'sprout_meta_box', $args, SI_Estimate::POST_TYPE );
	}

	/**
	 * Show the estimate history, including the submission fields
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
		$estimate = SI_Estimate::get_instance( $post->ID );
		self::load_view( 'admin/meta-boxes/estimates/history-premium', array(
				'id' => $post->ID,
				'post' => $post,
				'estimate' => $estimate,
				'history' => si_doc_history_records( $post->ID, false ),
				'submission_fields' => $estimate->get_submission_fields(),
		), false );
	}

	////////////
	// Misc. //
	////////////

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
		if ( $post_type == SI_Estimate::POST_TYPE && false !== strpos( $slug, '-2' ) ) {
			return $hashed_post_slug; // add microtime to be unique
		}
		// Change every post that has auto-draft
		if ( false !== strpos( $slug, __( 'auto-draft' ) ) ) {
			return $hashed_post_slug; // add microtime to be unique
		}
		 // don't change on front-end edits.
		if ( in_array( $post_status, array( SI_Estimate::STATUS_PENDING, SI_Estimate::STATUS_APPROVED, SI_Estimate::STATUS_DECLINED ) ) || apply_filters( 'si_is_estimate_currently_custom_status', $post_ID ) ) {
			return $slug;
		}
		// make sure it's a new post
		if ( ( ! isset( $_POST['post_name'] ) || $_POST['post_name'] == '' ) && $post_type == SI_Estimate::POST_TYPE ) {
			return $hashed_post_slug; // add microtime to be unique
		}
		return $slug;
	}
}
