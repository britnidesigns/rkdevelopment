<?php

/**
 * SI_Embeds Controller
 *
 * @package Sprout_Invoice
 * @subpackage SI_Embeds
 */
class SI_Embeds extends SI_Controller {
	const INV_SHORTCODE = 'sprout_invoice';
	const EST_SHORTCODE = 'sprout_estimate';

	public static function init() {

		do_action( 'sprout_shortcode', self::INV_SHORTCODE, array( __CLASS__, 'invoice_embed' ) );
		do_action( 'sprout_shortcode', self::EST_SHORTCODE, array( __CLASS__, 'estimate_embed' ) );

		if ( ! is_admin() ) {
			// Enqueue
			add_action( 'wp_enqueue_scripts', array( __CLASS__, 'register_resources' ) );
			add_action( 'wp_enqueue_scripts', array( __CLASS__, 'frontend_enqueue' ), 20 );
		}

	}

	public static function invoice_embed( $atts = array() ) {

		do_action( 'sprout_invoices_invoice_embed' );

		$invoice_id = 0;

		// Make sure id given is for an invoice
		if ( isset( $atts['id'] ) ) {
			$invoice_id = (int) $atts['id'];
		}

		if ( isset( $_GET['si_id'] ) ) {
			$invoice_id = (int) $_GET['si_id'];
		}

		if ( SI_Invoice::POST_TYPE !== get_post_type( $invoice_id ) ) {
			return;
		}

		// enqueue style
		self::frontend_enqueue();

		// Show snippet view or not.
		$embed_view = '';
		if ( isset( $atts['snippet'] ) && 'true' === $atts['snippet'] ) {
			$embed_view = '-snippet';
		}

		self::remove_actions();

		// Setup global post for filters and functions to work
		global $post;
		$post = get_post( $invoice_id );
		setup_postdata( $post );

		$invoice = SI_Invoice::get_instance( $invoice_id );
		$line_items = $invoice->get_line_items();
		$view = self::load_addon_view_to_string( 'invoices/embed' . $embed_view, array(
				'id' => $invoice_id,
				'line_items' => $line_items,
				'prev_type' => '',
				'totals' => SI_Line_Items::line_item_totals( $invoice_id ),
			), false );

		// reset to the original post
		wp_reset_postdata();
		return $view;
	}

	public static function estimate_embed( $atts = array() ) {

		do_action( 'sprout_invoices_estimate_embed' );


		$estimate_id = 0;

		// Make sure id given is for an invoice
		if ( isset( $atts['id'] ) ) {
			$estimate_id = (int) $atts['id'];
		}

		if ( isset( $_GET['si_id'] ) ) {
			$estimate_id = (int) $_GET['si_id'];
		}

		if ( SI_Estimate::POST_TYPE !== get_post_type( $estimate_id ) ) {
			return;
		}

		// enqueue style
		self::frontend_enqueue();

		// Show snippet view or not.
		$embed_view = '';
		if ( isset( $atts['snippet'] ) && 'true' === $atts['snippet'] ) {
			$embed_view = '-snippet';
		}

		self::remove_actions();

		// Setup global post for filters and functions to work
		global $post;
		$post = get_post( $estimate_id );
		setup_postdata( $post );

		$estimate = SI_Estimate::get_instance( $estimate_id );
		$line_items = $estimate->get_line_items();
		$view = self::load_addon_view_to_string( 'estimates/embed' . $embed_view, array(
				'id' => $estimate_id,
				'line_items' => $line_items,
				'prev_type' => '',
				'totals' => SI_Line_Items::line_item_totals( $estimate_id ),
			), false );

		// reset to the original post
		wp_reset_postdata();
		return $view;
	}

	public static function remove_actions() {
		remove_action( 'si_get_front_end_line_item_pre_row', array( 'SI_Doc_Comments', 'toggle_line_item_comments' ) );
	}

	//////////////
	// Enqueue //
	//////////////

	public static function register_resources() {
		wp_register_style( 'si_embeds', SA_ADDON_EMBEDS_URL . '/resources/css/si-embeds.css', array(), self::SI_VERSION );
		wp_register_script( 'si_embeds', SA_ADDON_EMBEDS_URL . '/resources/js/si-embeds.js', array(), self::SI_VERSION );
	}

	public static function frontend_enqueue() {
		wp_enqueue_style( 'si_embeds' );
		wp_enqueue_script( 'si_embeds' );
		wp_localize_script( 'si_embeds', 'si_js_object', SI_Controller::get_localized_js() );
	}

	/////////////
	// Utility //
	/////////////

	public static function load_addon_view( $view, $args, $allow_theme_override = true ) {
		add_filter( 'si_views_path', array( __CLASS__, 'addons_view_path' ) );
		$view = self::load_view( $view, $args, $allow_theme_override );
		remove_filter( 'si_views_path', array( __CLASS__, 'addons_view_path' ) );
		return $view;
	}

	public static function load_addon_view_to_string( $view, $args, $allow_theme_override = true ) {
		ob_start();
		self::load_addon_view( $view, $args, $allow_theme_override );
		return ob_get_clean();
	}

	public static function addons_view_path() {
		return SA_ADDON_EMBEDS_PATH . '/views/';
	}

}