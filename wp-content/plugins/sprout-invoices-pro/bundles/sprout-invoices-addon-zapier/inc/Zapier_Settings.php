<?php

/**
 * Time_Tracking_Toggl Controller
 *
 * @package Sprout_Invoice
 * @subpackage Time_Tracking_Toggl
 */
class Zapier_Settings extends Zapier_Controller {
	const API_KEY = 'si_zapier_api_key';
	private static $api_key;

	public static function init() {

		self::$api_key = self::get_api_key();

		// Register Settings
		add_filter( 'si_settings', array( __CLASS__, 'register_settings' ) );

	}

	public static function get_api_key() {
		$key = get_option( self::API_KEY );
		if ( ! $key ) {
			$key = self::generate_api_key();
			update_option( self::API_KEY, $key );
		}
		return $key;
	}

	////////////
	// admin //
	////////////

	/**
	 * Hooked on init add the settings page and options.
	 *
	 */
	public static function register_settings( $settings = array() ) {

		$hidden_input = sprintf( '%1$s<input name="%2$s" type="hidden" value="%1$s" />', self::$api_key, self::API_KEY );

		// Settings
		$settings['zapier'] = array(
				'title' => __( 'Zapier API', 'sprout-invoices' ),
				'weight' => 1000, // Add-on settings are 1000 plus
				'tab' => 'addons',
				'settings' => array(
					self::API_KEY => array(
						'label' => __( 'API Key', 'sprout-invoices' ),
						'option' => array(
							'type' => 'bypass',
							'output' => $hidden_input,
							'description' => __( 'This is the API token to allow Zapier to integrate with your site.', 'sprout-invoices' ),
							),
						),
					'url' => array(
						'label' => __( 'API URL', 'sprout-invoices' ),
						'option' => array(
							'type' => 'bypass',
							'output' => trailingslashit( home_url() ) . trailingslashit( Zapier_Routes::API_SLUG ),
							'description' => __( 'This is the API url you will give Zapier to integrate with your site.', 'sprout-invoices' ),
							),
						),
					),
			);
		return $settings;
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
		return SA_ADDON_ZAPIER_PATH . '/views/';
	}

	public static function generate_api_key() {
		$chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
		$segment_chars = 5;
		$num_segments = 4;
		$key_string = '';

		for ( $i = 0; $i < $num_segments; $i++ ) {
			$segment = '';

			for ( $j = 0; $j < $segment_chars; $j++ ) {
				$segment .= $chars[ rand( 0, 35 ) ];
			}

			$key_string .= $segment;

			if ( $i < ($num_segments - 1) ) {
				$key_string .= '-'; }
		}

		return $key_string;
	}
}
