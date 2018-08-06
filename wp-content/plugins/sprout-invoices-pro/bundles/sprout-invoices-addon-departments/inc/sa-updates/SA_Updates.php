<?php

/**
 * Updates class
 *
 * @package Sprout_Invoice
 * @subpackage Updates
 */
class SA_Tasks_and_Product_Updates extends SI_Updates {

	public static function init() {
		self::$license_key = trim( get_option( self::LICENSE_KEY_OPTION, '' ) );
		self::$license_status = get_option( self::LICENSE_STATUS, false );

		if ( is_admin() ) {
			add_action( 'admin_init', array( __CLASS__, 'init_edd_udpater' ) );
		}
	}

	public static function init_edd_udpater() {

		// setup the updater
		$edd_updater = new EDD_SL_Plugin_Updater_SA_Mod( self::PLUGIN_URL, SA_ADDON_DEPARTMENTS_FILE, array(
				'download_id' => SA_ADDON_DEPARTMENTS_DOWNLOAD_ID,		// Set the download_id manually
				'version' 	=> SA_ADDON_DEPARTMENTS_VERSION,		// current version number
				'license' 	=> self::license_key(),	 		// license key (used get_option above to retrieve from DB)
				'department_name' => SA_ADDON_DEPARTMENTS_NAME, 		// name of this plugin
				'author' 	=> 'Sprout Apps',// author of this plugin
			)
		);

		// $edd_updater->api_request( 'plugin_latest_version', array( 'slug' => basename( self::PLUGIN_FILE, '.php') ) );

		// uncomment this line for testing
		// set_site_transient( 'update_plugins', null );
	}
}
SA_Tasks_and_Product_Updates::init();
