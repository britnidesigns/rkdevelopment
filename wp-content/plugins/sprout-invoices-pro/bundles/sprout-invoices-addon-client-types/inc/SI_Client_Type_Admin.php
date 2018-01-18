<?php

/**
 * Controller
 * Adds meta boxes to client admin.
 */
class SI_Client_Type_Admin extends SI_Client_Categories {
	const TAXONOMY = 'si_client_type';
	const TERM_META = 'si_numbering_tag_value';

	public static function init() {
		// Invoice admin table
		add_action( 'restrict_manage_posts', array( __CLASS__, 'show_client_types' ) );
		add_action( 'pre_get_posts', array( __CLASS__, 'filter_admin_table_results' ), 100 );
	}

	public static function show_client_types() {
		global $typenow;
		if ( SI_Client::POST_TYPE !== $typenow ) {
			return;
		}
		$selected = isset( $_GET[ self::TAXONOMY ] ) ? esc_attr( $_GET[ self::TAXONOMY ] ) : '';
		$info_taxonomy = get_taxonomy( self::TAXONOMY );
		wp_dropdown_categories(array(
			'show_option_all' => __( 'Show All Client Types', 'sprout-invoices' ),
			'taxonomy' => self::TAXONOMY,
			'name' => self::TAXONOMY,
			'orderby' => 'name',
			'selected' => $selected,
			'show_count' => true,
			'hide_empty' => true,
		));

	}

	public static function filter_admin_table_results( $query ) {
		if ( defined( 'DOING_AJAX' ) && DOING_AJAX ) {
			return;
		}
		if ( is_admin() && SI_Client::POST_TYPE !== $query->query['post_type'] ) {
			return;
		}
		$term_id_selected = isset( $_GET[ self::TAXONOMY ] ) ? esc_attr( $_GET[ self::TAXONOMY ] ) : 0;
		if ( ! $term_id_selected ) {
			return;
		}
		$term = get_term_by( 'id', $term_id_selected, self::TAXONOMY );
		$query->set( 'taxonomy', self::TAXONOMY );
		$query->set( 'term', $term->slug );
	}
}
SI_Client_Type_Admin::init();
