<?php

/**
 * Controller
 * Adds meta boxes to client admin.
 */
class SI_Client_Categories extends SI_Controller {
	const TAXONOMY = 'si_client_type';

	public static function init() {
		self::register_client_tax();
	}

	public static function register_client_tax() {
		$taxonomy_args = array(
			'labels' => array(
				'name' => __( 'Client Types', 'sprout-invoices' ),
				'singular_name' => __( 'Client Type', 'sprout-invoices' ),
				'search_items' => __( 'Search Client Types', 'sprout-invoices' ),
				'popular_items' => __( 'Popular Client Types', 'sprout-invoices' ),
				'all_items' => __( 'All Client Types', 'sprout-invoices' ),
				'parent_item' => __( 'Parent Client Type', 'sprout-invoices' ),
				'parent_item_colon' => __( 'Parent Client Type:', 'sprout-invoices' ),
				'edit_item' => __( 'Edit Client Type', 'sprout-invoices' ),
				'update_item' => __( 'Update Client Type', 'sprout-invoices' ),
				'add_new_item' => __( 'Add New Client Type', 'sprout-invoices' ),
				'new_item_name' => __( 'New Client Type Name', 'sprout-invoices' ),
				'menu_name' => __( 'Client Types', 'sprout-invoices' ),
			),
			'meta_box_cb' => false,
			'hierarchical' => false,
			'public' => true,
			'rewrite' => false,
		);
		register_taxonomy( self::TAXONOMY, array( SI_Invoice::POST_TYPE, SI_Client::POST_TYPE ), $taxonomy_args );
	}

	public static function set_client_type( $client_id, $type = '' ) {
		$term = get_term_by( 'slug', $type, self::TAXONOMY );
		wp_set_post_terms( $client_id, array( $term->term_id ), self::TAXONOMY );
		return $term;
	}

	public static function get_client_type( $client_id ) {
		$terms = wp_get_object_terms( $client_id, self::TAXONOMY );
		$term = array_shift( $terms );
		if ( ! is_object( $term ) ) {
			return;
		}
		return $term->slug;
	}

	public static function get_client_type_id( $client_id ) {
		$terms = wp_get_object_terms( $client_id, self::TAXONOMY );
		$term = array_shift( $terms );
		if ( ! is_object( $term ) ) {
			return;
		}
		return $term->term_id;
	}

	public static function get_client_types( $add_empty = true ) {
		$types = array();
		if ( $add_empty ) {
			$types[] = '';
		}
		$types_terms = get_terms( array( self::TAXONOMY ), array( 'hide_empty' => false, 'fields' => 'all' ) );
		foreach ( $types_terms as $term ) {
			$types[ $term->slug ] = $term->name;
		}
		return $types;
	}

	public static function add_client_type_tag( $tags = array() ) {
		$tags['client_type'] = __( 'Shows the value of the client type.', 'sprout-invoices' );
		return $tags;
	}

	public static function get_client_type_tag_from_invoice( $invoice_id = 0 ) {
		$client_id = si_get_invoice_client_id( $invoice_id );
		if ( ! $client_id ) {
			return '{client_type}';
		}
		$term_id = self::get_client_type_id( $client_id );
		$type_tag = get_term_meta( $term_id, self::TERM_META, true );
		return $type_tag;
	}

	public static function get_client_type_tag_from_estimate( $estimate_id = 0 ) {
		$client_id = si_get_estimate_client_id( $estimate_id );
		if ( ! $client_id ) {
			return '{client_type}';
		}
		$term_id = self::get_client_type_id( $client_id );
		$type_tag = get_term_meta( $term_id, self::TERM_META, true );
		return $type_tag;
	}

	public static function _si_information_meta_box_args( $args = '' ) {
		if ( 'auto-draft' !== $args['post']->post_status ) { // only adjust drafts
			if ( SI_Invoice::POST_TYPE === $args['post']->post_type ) {
				$args['invoice_id'] = self::filter_invoice_id( $args['invoice_id'], $args['post']->ID );
			}
			if ( SI_Estimate::POST_TYPE === $args['post']->post_type ) {
				$args['estimate_id'] = self::filter_estimate_id( $args['estimate_id'], $args['post']->ID );
			}
		}
		return $args;
	}

	public static function filter_invoice_id( $filtered_id, $invoice_id ) {
		$type = self::get_client_type_tag_from_invoice( $invoice_id );
		$filtered_id = str_replace( '{client_type}', $type, $filtered_id );
		return $filtered_id;
	}

	public static function filter_estimate_id( $filtered_id, $estimate_id ) {
		$type = self::get_client_type_tag_from_estimate( $estimate_id );
		$filtered_id = str_replace( '{client_type}', $type, $filtered_id );
		return $filtered_id;
	}
}
SI_Client_Categories::init();
