<?php

/**
 * Department Model
 * 
 *
 * @package Sprout_Invoices
 * @subpackage Department
 */
class SI_Department extends SI_Post_Type {
	const TAXONOMY = 'si_department';

	public static function init() {
		$singular = 'Department';
		$plural = 'Departments';
		$taxonomy_args = array(
			'meta_box_cb' => false,
			'hierarchical' => false
		);
		self::register_taxonomy( self::TAXONOMY, array( SI_Invoice::POST_TYPE ), $singular, $plural, $taxonomy_args );
	}

	///////////
	// Meta //
	///////////

	public static function set_doc_department( $doc_id, $department ) {
		$term = get_term_by( 'slug', $department, self::TAXONOMY );
		wp_set_post_terms( $doc_id, array( $term->term_id ), self::TAXONOMY );
		return $term;
	}


	public static function get_doc_department( $doc_id ) {
		$terms = wp_get_object_terms( $doc_id, self::TAXONOMY );
		$term = array_shift( $terms );
		if ( !is_object( $term ) ) {
			return;
		}
		return $term->slug;
	}

	//////////////
	// utility //
	//////////////

	public static function get_departments() {
		$departments = array();
		$dep_terms = get_terms( array( self::TAXONOMY ), array( 'hide_empty' => false, 'fields' => 'all' ) );
		foreach ( $dep_terms as $term ) {
			$departments[$term->slug] = $term->name;
		}
		return $departments;
	}

}