<?php

/**
 * Estimates Controller
 *
 *
 * @package Sprout_Invoice
 * @subpackage Estimates
 */
class SI_Projects_Premium extends SI_Projects {

	public static function init() {

		if ( is_admin() ) {
			// Meta boxes
			add_action( 'admin_init', array( __CLASS__, 'register_meta_boxes' ), 20 );
		}

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
			'si_project_history' => array(
				'title' => __( 'History', 'sprout-invoices' ),
				'show_callback' => array( __CLASS__, 'show_project_history_view' ),
				'save_callback' => array( __CLASS__, '_save_null' ),
				'context' => 'normal',
				'priority' => 'low'
			)
		);
		do_action( 'sprout_meta_box', $args, SI_Project::POST_TYPE );
	}

	/**
	 * Show the history
	 *
	 * @param WP_Post $post
	 * @param array   $metabox
	 * @return
	 */
	public static function show_project_history_view( $post, $metabox ) {
		if ( $post->post_status == 'auto-draft' ) {
			printf( '<p>%s</p>', __( 'No history available.', 'sprout-invoices' ) );
			return;
		}
		$project = SI_Project::get_instance( $post->ID );
		self::load_view( 'admin/meta-boxes/projects/history-premium', array(
				'id' => $post->ID,
				'post' => $post,
				'project' => $project,
				'historical_records' => array_reverse( $project->get_history() ),
			), false );
	}

}
