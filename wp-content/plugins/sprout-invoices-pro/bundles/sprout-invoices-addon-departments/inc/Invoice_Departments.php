<?php

/**
 * Invoice_Departments Controller
 *
 * @package Sprout_Invoice
 * @subpackage Invoice_Departments
 */
class Invoice_Departments extends SI_Controller {
	const REPORT_QV = 'dept_filter';

	public static function init() {

		if ( is_admin() ) {

			// meta boxes
			add_action( 'doc_information_meta_box_client_row_last', array( __CLASS__, 'add_invoicing_departments' ) );
			add_action( 'si_save_line_items_meta_box', array( __CLASS__, 'save_department_selection' ) );

			// reporting
			add_filter( 'si_settings_page_sub_heading_sprout-invoices-stats', array( get_class(), 'reports_subtitle' ), 15 );
			add_action( 'pre_get_posts', array( get_class(), 'filter_reports' ) );
			add_filter( 'si_locate_premium_template', array( get_class(), 'use_modified_reports_dashboard' ), 10, 2 );
			add_filter( 'si_disable_reporting_cache', '__return_true' );

			// Enqueue
			add_action( 'admin_enqueue_scripts', array( __CLASS__, 'register_resources' ) );
			add_action( 'admin_enqueue_scripts', array( __CLASS__, 'admin_enqueue' ), 20 );

		}

	}


	//////////////
	// Enqueue //
	//////////////

	public static function register_resources() {
		// admin js
		wp_register_script( 'si_invoicing_departments', SA_ADDON_DEPARTMENTS_URL . '/resources/admin/js/invoicing_departments.js', array( 'jquery' ), self::SI_VERSION );
	}

	public static function admin_enqueue() {
		wp_enqueue_script( 'si_invoicing_departments' );
	}

	/////////////////
	// Meta boxes //
	/////////////////

	public static function add_invoicing_departments( $doc ) {
		$departments = SI_Department::get_departments();
		$department = SI_Department::get_doc_department( $doc->get_id() );
		self::load_addon_view( 'admin/meta-boxes/invoices/select-department.php', array(
				'departments' => $departments,
				'default' => $department,
			), false );
	}

	public static function save_department_selection( $post_id = 0 ) {
		$doc_department = ( isset( $_POST['doc_department'] ) ) ? $_POST['doc_department'] : '' ;
		SI_Department::set_doc_department( $post_id, $doc_department );
	}

	////////////////
	// Reporting //
	////////////////

	public static function use_modified_reports_dashboard( $path = '', $view = '' ) {
		if ( 'admin/reports/premium/dashboard.php' !== $view ) {
			return $path;
		}
		return SA_ADDON_DEPARTMENTS_PATH.'/views/reports/premium/dashboard.php';
	}

	public static function reports_subtitle() {
		$current_report = ( isset( $_GET[ self::REPORT_QV ] ) ) ? $_GET[ self::REPORT_QV ] : '' ;
		$departments = SI_Department::get_departments();
		self::load_addon_view( 'admin/sections/reporting-tabs.php', array(
				'departments' => $departments,
				'current' => $current_report,
				'query_var' => self::REPORT_QV,
			), false );
	}

	public static function filter_reports( $wp_query ) {
		if ( isset( $wp_query->query_vars['bypass_pre_get_posts'] ) ) {
			return $wp_query;
		}
		$term = ( isset( $_GET[ self::REPORT_QV ] ) && $_GET[ self::REPORT_QV ] ) ? $_GET[ self::REPORT_QV ] : false ;
		if ( ! $term ) {
			return $wp_query;
		}

		if ( in_array( $wp_query->query_vars['post_type'], array( 'sa_invoice', 'sa_estimate' ) ) ) {
			$wp_query->query_vars['tax_query'][] = array(
				'taxonomy' => SI_Department::TAXONOMY,
				'field'    => 'slug',
				'terms' => sanitize_key( $term ),
			);
		}
		elseif ( in_array( $wp_query->query_vars['post_type'], array( 'sa_payment' ) ) ) {
			$args = array(
				'post_type' => SI_Invoice::POST_TYPE,
				'posts_per_page' => apply_filters( 'si_reports_show_records', 2500, 'invoices' ),
				'tax_query' => array(
						array(
							'taxonomy' => SI_Department::TAXONOMY,
							'field'    => 'slug',
							'terms' => sanitize_key( $term ),
						),
					),
				'fields' => 'ids',
				'bypass_pre_get_posts' => 1,
			);
			$invoices = new WP_Query( $args );
			$invoice_ids = ( ! empty( $invoices->posts ) ) ? $invoices->posts : array( 0 ) ;
			$wp_query->query_vars['meta_query'][] = array(
				'key' => '_payment_invoice',
				'value' => $invoice_ids,
				'compare' => 'IN',
			);
		}
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
		return SA_ADDON_DEPARTMENTS_PATH . '/views/';
	}

}