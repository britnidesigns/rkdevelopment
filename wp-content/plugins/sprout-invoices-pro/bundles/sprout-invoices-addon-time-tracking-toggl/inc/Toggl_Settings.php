<?php

/**
 * Time_Tracking_Toggl Controller
 *
 * @package Sprout_Invoice
 * @subpackage Time_Tracking_Toggl
 */
class Toggl_Settings extends Toggl_Controller {
	const API_KEY = 'si_toggle_api_key';
	const WORKSPACE_ID = 'si_toggle_workspace_id';
	private static $api_key;
	private static $workspace_id;

	public static function init() {

		self::$api_key = get_option( self::API_KEY, '' );
		self::$workspace_id = get_option( self::WORKSPACE_ID, '' );

		// Register Settings
		add_filter( 'si_settings', array( __CLASS__, 'register_settings' ) );

		if ( is_admin() ) {

			if ( self::$api_key != '' ) {
				// Meta boxes
				add_action( 'admin_init', array( __CLASS__, 'register_meta_boxes' ) );
			}
		}
	}

	public static function get_api_key() {
		return self::$api_key;
	}

	public static function get_workspace_id() {
		return self::$workspace_id;
	}

	////////////
	// admin //
	////////////

	/**
	 * Hooked on init add the settings page and options.
	 *
	 */
	public static function register_settings( $settings = array() ) {

		$options = array( 0 => __( 'Default', 'sprout-invoices' ) );
		if ( self::$api_key ) {
			// Only do an API callback on the settings page.
			// TODO register settings on the settings pages only.
			if ( isset( $_GET['page'] ) && $_GET['page'] == 'sprout-invoices-settings' ) {
				$workspaces = Toggl_API::get_workspaces();
				if ( ! empty( $workspaces ) ) {
					foreach ( $workspaces as $key => $workspace ) {
						$options[ $workspace->id ] = $workspace->name;
					}
				}
			}
		}

		// Settings
		$settings['toggl'] = array(
				'title' => __( 'Toggl Settings', 'sprout-invoices' ),
				'weight' => 1000, // Add-on settings are 1000 plus
				'tab' => 'addons',
				'settings' => array(
					self::API_KEY => array(
						'label' => __( 'API Key', 'sprout-invoices' ),
						'option' => array(
							'type' => 'text',
							'default' => self::$api_key,
							'description' => __( 'This is your API token found on your profile page, not the Workspace API Token.', 'sprout-invoices' ),
							),
						),
					self::WORKSPACE_ID => array(
						'label' => __( 'Workspace', 'sprout-invoices' ),
						'option' => array(
							'type' => 'select',
							'options' => $options,
							'default' => self::$workspace_id,
							'description' => __( 'You can select a different workspace after saving your API Token.', 'sprout-invoices' ),
							),
						),
					),
			);
		return $settings;
	}
	/**
	 * Regsiter meta boxes for notification editing.
	 * @return
	 */
	public static function register_meta_boxes() {
		// estimate specific
		$args = array(
			'si_project_timetracking_toggl' => array(
				'title' => __( 'Toggl Time Tracking', 'sprout-invoices' ),
				'show_callback' => array( __CLASS__, 'show_timetracking_toggl_meta_box' ),
				'save_callback' => array( __CLASS__, 'save_meta_box_time_tracking_toggl' ),
				'context' => 'side',
				'priority' => 'low',
				'save_priority' => 0,
			),
		);
		do_action( 'sprout_meta_box', $args, SI_Project::POST_TYPE );
	}

	/**
	 * Show time tracking metabox.
	 * @param  WP_Post $post
	 * @param  array $metabox
	 * @return
	 */
	public static function show_timetracking_toggl_meta_box( $post, $metabox ) {
		$fields = self::toggl_entry_fields( $post->ID, true );
		$project = SI_Project::get_instance( $post->ID );
		self::load_addon_view( 'admin/meta-boxes/projects/toggl-project', array(
				'fields' => $fields,
				'project_id' => $project->get_id(),
		), false );
	}

	public static function save_meta_box_time_tracking_toggl( $post_id, $post, $callback_args, $invoice_id = null ) {
		$toggl_id = ( isset( $_POST['sa_toggl_toggl_id'] ) ) ? $_POST['sa_toggl_toggl_id'] : 0 ;

		// Create new toggl project if selection permits
		if ( $toggl_id == 'create_new' ) {
			// args
			$project = SI_Project::get_instance( $post_id );
			$args = array( 'project_name' => $post->post_title );
			// create
			$toggl_id = self::maybe_create_toggl_project( $project, $args );
			return;
		}
		// set the meta
		self::set_projects_toggl_id( $post_id, (int) $toggl_id );

		self::set_to_sync_time( $post_id ); // Reset
		if ( isset( $_POST['sa_toggl_sync_time'] ) && $_POST['sa_toggl_sync_time'] ) {
			self::set_to_sync_time( $post_id, true );
		}

		$default_activity = ( isset( $_POST['sa_toggl_default_activity'] ) ) ? $_POST['sa_toggl_default_activity'] : 0 ;
		self::set_projects_default_time_import_activity( $post_id, (int) $default_activity );

		if ( isset( $_POST['sa_toggl_pulldown_time'] ) && $_POST['sa_toggl_pulldown_time'] ) {
			self::download_toggl_time( $post_id );
		}
	}

	public static function toggl_entry_fields( $project_id = 0 ) {

		$toggl_projects = array( 0 => __( 'None', 'sprout-invoices' ), 'create_new' => __( 'Create Project at Toggl', 'sprout-invoices' ) );
		foreach ( Toggl_API::get_projects() as $key => $project ) {
			$toggl_projects[ $project->id ] = $project->name;
		}

		$fields['toggl_id'] = array(
			'weight' => 10,
			'label' => __( 'Toggl Project', 'sprout-invoices' ),
			'type' => 'select',
			'description' => __( 'Link a project at toggl or create a new one.', 'sprout-invoices' ),
			'options' => $toggl_projects,
			'default' => self::get_projects_toggl_id( $project_id ),
			'attributes' => array( 'class' => 'select2' ),
		);

		$fields['sync_time'] = array(
			'weight' => 50,
			'label' => __( 'Sync Time with Toggl', 'sprout-invoices' ),
			'type' => 'checkbox',
			'default' => self::does_sync_time( $project_id ),
			'description' => __( 'Automatically send time created here to Toggle and delete any time at Toggl if deleted here.', 'sprout-invoices' ),
		);

		$fields['pulldown_time'] = array(
			'weight' => 60,
			'label' => __( 'Retrieve Project Time from Toggl', 'sprout-invoices' ),
			'type' => 'checkbox',
			'description' => __( 'Check, then save to import time for this project. Time does not import automatically (yet).', 'sprout-invoices' ),
		);

		$activities = SI_Time::get_activities();
		$fields['default_activity'] = array(
			'weight' => 70,
			'label' => __( 'Activity for Imported Time', 'sprout-invoices' ),
			'type' => 'select',
			'options' => $activities,
			'default' => self::get_projects_default_time_import_activity( $project_id ),
			'description' => __( 'Check, then save to import time for this project. Time does not import automatically (yet).', 'sprout -invoices' ),
			'attributes' => array( 'class' => 'select2' ),
		);

		$fields = apply_filters( 'si_toggl_time_entry_form_fields', $fields );
		uasort( $fields, array( __CLASS__, 'sort_by_weight' ) );
		return $fields;
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
		return SA_ADDON_TIME_TRACKING_TOGGL_PATH . '/views/';
	}
}
