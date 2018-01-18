<?php


/**
 * Updates class
 *
 * @package Sprout_Invoice
 * @subpackage Updates
 */
class SI_Updates extends SI_Controller {
	const LICENSE_KEY_OPTION = 'si_license_key';
	const LICENSE_STATUS = 'si_license_status';
	protected static $license_key;
	protected static $license_status;

	public static function init() {
		self::$license_key = trim( get_option( self::LICENSE_KEY_OPTION, '' ) );
		self::$license_status = get_option( self::LICENSE_STATUS, false );

		self::register_settings();

		if ( is_admin() ) {
			add_action( 'admin_init', array( __CLASS__, 'init_edd_udpater' ) );

			// AJAX
			add_action( 'wp_ajax_si_activate_license',  array( __CLASS__, 'maybe_activate_license' ), 10, 0 );
			add_action( 'wp_ajax_si_deactivate_license',  array( __CLASS__, 'maybe_deactivate_license' ), 10, 0 );
			add_action( 'wp_ajax_si_check_license',  array( __CLASS__, 'maybe_check_license' ), 10, 0 );
		}
	}

	public static function init_edd_udpater() {

		// update vars
		$vars = array(
				'version' 	=> self::SI_VERSION, // current version number
				'license' 	=> self::$license_key, // license key (used get_option above to retrieve from DB)
				'item_name' => self::PLUGIN_NAME, // name of this plugin
				'author' 	=> 'Sprout Apps', // author of this plugin
			);

		// setup the updater
		$edd_updater = new EDD_SL_Plugin_Updater_SA_Mod( self::PLUGIN_URL, apply_filters( 'si_updater_plugin_file', self::PLUGIN_FILE ), apply_filters( 'si_updater_vars', $vars ) );

		// $edd_updater->api_request( 'plugin_latest_version', array( 'slug' => basename( self::PLUGIN_FILE, '.php' ) ) );

		// uncomment this line for testing
		// set_site_transient( 'update_plugins', null );
	}

	public static function license_key() {
		return self::$license_key;
	}

	public static function license_status() {
		return self::$license_status;
	}

	///////////////
	// Settings //
	///////////////

	/**
	 * Hooked on init add the settings page and options.
	 *
	 */
	public static function register_settings() {
		// Settings
		$settings = array(
			'so_activation' => array(
				'title' => __( 'Sprout Invoices Activation', 'sprout-invoices' ),
				'weight' => 0,
				'tab' => 'settings',
				'callback' => array( __CLASS__, 'update_setting_description' ),
				'settings' => array(
					self::LICENSE_KEY_OPTION => array(
						'label' => __( 'License Key', 'sprout-invoices' ),
						'option' => array(
							'type' => 'bypass',
							'output' => self::license_key_option(),
							'description' => sprintf( __( 'Enter your license key to enable automatic plugin updates. Find your license key in your Sprout Apps Dashboard under the <a href="%s" target="_blank">Downloads</a> section.', 'sprout-invoices' ), si_get_sa_link( 'https://sproutapps.co/account/' ) ),
							),
						),
					),
				),
			);
		do_action( 'sprout_settings', $settings, self::SETTINGS_PAGE );

	}

	public static function license_key_option() {
		ob_start(); ?>
			<input type="text" name="<?php echo self::LICENSE_KEY_OPTION ?>" id="<?php echo self::LICENSE_KEY_OPTION ?>" value="<?php echo self::$license_key ?>" class="<?php echo 'license_'.self::$license_status ?>" size="40" class="text-input">
			<?php if ( self::$license_status != false && self::$license_status == 'valid' ) : ?>
				<button id="activate_license" class="button" disabled="disabled"><?php _e( 'Activate License', 'sprout-invoices' ) ?></button> 
				<button id="deactivate_license" class="button"><?php _e( 'Deactivate License', 'sprout-invoices' ) ?></button>
			<?php else : ?>
				<button id="activate_license" class="button button-primary"><?php _e( 'Activate License', 'sprout-invoices' ) ?></button>
			<?php endif ?>
			<div id="license_message" class="clearfix"></div>
		<?php
		$view = ob_get_clean();
		return $view;
	}

	public static function update_setting_description() {
		// _e( 'TODO Describe the license key and how to purchase.', 'sprout-invoices' );
	}


	///////////////////
	// API Controls //
	///////////////////

	public static function activate_license( $args = array() ) {
		$license_data = self::api( 'activate_license', $args );
		update_option( self::LICENSE_STATUS, $license_data->license );
		// $license_data->license will be either "deactivated" or "failed"
		if ( $license_data->license == 'valid' ) {
			return true;
		}
		return false;
	}

	public static function deactivate_license() {
		$license_data = self::api( 'deactivate_license' );

		// $license_data->license will be either "deactivated" or "failed"
		if (  $license_data->license == 'deactivated' ) {
			delete_option( self::LICENSE_STATUS );
			return true;
		}
		return false;
	}

	public static function check_license() {
		$license_data = self::api( 'check_license' );
		return ( $license_data->license == 'valid' );
	}

	///////////
	// AJAX //
	///////////

	public static function maybe_activate_license() {
		if ( ! isset( $_REQUEST['security'] ) ) {
			self::ajax_fail( 'Forget something?' ); }

		$nonce = $_REQUEST['security'];
		if ( ! wp_verify_nonce( $nonce, self::NONCE ) ) {
			self::ajax_fail( 'Not going to fall for it!' ); }

		if ( ! current_user_can( 'activate_plugins' ) ) {
			return; }

		if ( ! isset( $_REQUEST['license'] ) ) {
			self::ajax_fail( 'No license key submitted' );
		}

		update_option( self::LICENSE_KEY_OPTION, $_REQUEST['license'] );
		self::$license_key = $_REQUEST['license'];

		$activated = self::activate_license();
		$message = ( $activated ) ? __( 'Thank you for supporting the future of Sprout Invoices.', 'sprout-invoices' ) : __( 'License is not active.', 'sprout-invoices' );
		$response = array(
				'activated' => $activated,
				'response' => $message,
				'error' => ! $activated,
			);

		header( 'Content-type: application/json' );
		echo wp_json_encode( $response );
		exit();
	}

	public static function maybe_deactivate_license() {
		if ( ! isset( $_REQUEST['security'] ) ) {
			self::ajax_fail( 'Forget something?' ); }

		$nonce = $_REQUEST['security'];
		if ( ! wp_verify_nonce( $nonce, self::NONCE ) ) {
			self::ajax_fail( 'Not going to fall for it!' ); }

		if ( ! current_user_can( 'activate_plugins' ) ) {
			return; }

		$deactivated = self::deactivate_license();
		$message = ( $deactivated ) ? __( 'License is deactivated.', 'sprout-invoices' ) : __( 'Something went wrong. Contact support for help.', 'sprout-invoices' );
		$response = array(
				'valid' => $deactivated,
				'response' => $message,
				'error' => ! $deactivated,
			);

		header( 'Content-type: application/json' );
		echo wp_json_encode( $response );
		exit();
	}

	public static function maybe_check_license() {
		if ( ! isset( $_REQUEST['security'] ) ) {
			self::ajax_fail( 'Forget something?' ); }

		$nonce = $_REQUEST['security'];
		if ( ! wp_verify_nonce( $nonce, self::NONCE ) ) {
			self::ajax_fail( 'Not going to fall for it!' ); }

		if ( ! current_user_can( 'activate_plugins' ) ) {
			return; }

		$is_valid = self::check_license();
		$message = ( $is_valid ) ? __( 'Thank you for supporting the future of Sprout Invoices.', 'sprout-invoices' ) : __( 'License is not valid.', 'sprout-invoices' );
		$response = array(
				'valid' => $is_valid,
				'response' => $message,
			);

		header( 'Content-type: application/json' );
		echo wp_json_encode( $response );
		exit();
	}

	//////////////
	// Utility //
	//////////////


	public static function api( $action = 'activate_license', $api_params = array() ) {
		// data to send in our API request
		$api_params_defaults = array(
			'edd_action' => $action,
			'license' => self::$license_key,
			'item_name' => urlencode( self::PLUGIN_NAME ),
			'url'       => urlencode( home_url() ),
		);
		$api_params = wp_parse_args( $api_params, $api_params_defaults );

		// Call the custom API.
		$response = wp_safe_remote_get( add_query_arg( $api_params, self::PLUGIN_URL ), array( 'timeout' => 15, 'sslverify' => false ) );

		// make sure the response came back okay
		if ( is_wp_error( $response ) ) {
			return false; }

		// decode the license data
		$license_data = json_decode( wp_remote_retrieve_body( $response ) );

		return $license_data;
	}
}
