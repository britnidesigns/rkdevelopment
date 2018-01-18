<?php

/**
 * Time_Tracking_Toggl Controller
 *
 * @package Sprout_Invoice
 * @subpackage Time_Tracking_Toggl
 */
class Toggl_Controller extends SI_Controller {
	const PROJECT_ID_META = 'si_toggle_project_id';
	const SYNC_TIME_META = 'si_toggle_sync_time';
	const DEFAULT_TIME_ACTIVITY = 'si_toggle_default_activity_time';
	const CLIENT_ID_META = 'si_toggle_client_id'; // TODO

	public static function init() {
		if ( false === get_option( Toggl_Settings::API_KEY, false ) ) {
			return;
		}

		// Adding projects
		add_action( 'sa_new_project', array( __CLASS__, 'maybe_create_toggl_project' ), 10, 2 );

		// adding time
		add_action( 'si_time_created', array( __CLASS__, 'send_new_time_to_toggl' ) );
		add_action( 'si_time_deleted', array( __CLASS__, 'maybe_delete_toggl_time' ) );

		if ( is_admin() ) {
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
		wp_register_script( 'si_time_tracking_toggl', SA_ADDON_TIME_TRACKING_TOGGL_URL . '/resources/admin/js/time_toggl.js', array( 'jquery' ), self::SI_VERSION );
	}

	public static function admin_enqueue() {
		add_thickbox();
		wp_enqueue_script( 'si_time_tracking_toggl' );
	}

	///////////
	// Meta //
	///////////

	public static function get_projects_toggl_id( $project_id = 0 ) {
		$toggl_id = get_post_meta( $project_id, self::PROJECT_ID_META, true );
		return $toggl_id;
	}

	public static function set_projects_toggl_id( $project_id = 0, $toggl_id ) {
		update_post_meta( $project_id, self::PROJECT_ID_META, $toggl_id );
		return $toggl_id;
	}

	public static function does_sync_time( $project_id = 0 ) {
		if ( ! self::get_projects_toggl_id( $project_id ) ) {
			return false;
		}
		$sync = get_post_meta( $project_id, self::SYNC_TIME_META, true );
		return $sync;
	}

	public static function set_to_sync_time( $project_id = 0, $sync = false ) {
		update_post_meta( $project_id, self::SYNC_TIME_META, $sync );
		return $sync;
	}

	public static function get_projects_default_time_import_activity( $project_id = 0 ) {
		$activity_id = get_post_meta( $project_id, self::DEFAULT_TIME_ACTIVITY, true );
		return $activity_id;
	}

	public static function set_projects_default_time_import_activity( $project_id = 0, $activity_id ) {
		update_post_meta( $project_id, self::DEFAULT_TIME_ACTIVITY, $activity_id );
		return $activity_id;
	}

	///////////
	// time //
	///////////

	public static function send_new_time_to_toggl( $time_id = 0 ) {
		$time = SI_Record::get_instance( $time_id );
		if ( ! is_a( $time, 'SI_Record' ) ) {
			return;
		}
		$data = $time->get_data();
		$project_id = $data['project_id'];
		if ( ! self::does_sync_time( $project_id ) ) {
			return;
		}
		self::send_time_to_toggl( $project_id, $time );
	}

	public static function maybe_delete_toggl_time( $time_id ) {
		$time = SI_Record::get_instance( $time_id );
		if ( ! is_a( $time, 'SI_Record' ) ) {
			return;
		}
		$data = $time->get_data();
		$project_id = $data['project_id'];
		if ( ! self::does_sync_time( $project_id ) ) {
			return;
		}
		if ( ! isset( $data['toggl_id'] ) ) {
			return; // time was sent already
		}
		self::delete_time_from_toggl( $data['toggl_id'] );

	}

	public static function send_toggl_all_times( $project_id = 0 ) {
		if ( ! self::does_sync_time( $project_id ) ) {
			return;
		}
		$project = SI_Project::get_instance( $project_id );
		$time_records = $project->get_associated_times();
		foreach ( $time_records as $time_id ) {
			$time = SI_Record::get_instance( $time_id );
			if ( ! is_a( $time, 'SI_Record' ) ) {
				continue;
			}
			$data = $time->get_data();
			if ( isset( $data['toggl_id'] ) ) {
				continue; // time was sent already
			}
			self::send_time_to_toggl( $project_id, $time );
		}
	}

	public static function send_time_to_toggl( $project_id = 0, SI_Record $time ) {
		$time_data = $time->get_data();
		$billable = false; // TODO sync activities too.
		$date = date( 'd F o', $time_data['date'] ) . ' ' . date( 'H:i:00', current_time( 'timestamp' ) );
		$start = date( 'c', strtotime( $date ) );
		$data = array(
			'time_entry' => array(
				'description' => $time->get_title(),
				'pid' => self::get_projects_toggl_id( $project_id ),
				'billable' => $billable,
				'start' => $start,
				'duration' => $time_data['time_val'] * 60 * 60,
				'created_with' => self::PLUGIN_NAME,
				'at' => date( 'c', strtotime( $time->get_post_date() ) ),
				),
			);
		$response = Toggl_API::api_request( 'time_entries', $data );
		if ( $response ) {
			$time_data['toggl_id'] = $response->data->id;
			$time->set_data( $time_data );
		}
	}

	public static function delete_time_from_toggl( $toggl_id = 0 ) {
		$response = Toggl_API::api_request( 'time_entries/' . $toggl_id, 'delete' );
	}

	public static function download_toggl_time( $project_id = 0 ) {
		$toggl_id = self::get_projects_toggl_id( $project_id );
		if ( ! $toggl_id ) {
			return;
		}
		$entries = Toggl_API::get_workspace_time( $toggl_id );
		if ( ! isset( $entries->data ) ) {
			return;
		}
		$project = SI_Project::get_instance( $project_id );
		if ( ! is_a( $project, 'SI_Project' ) ) {
			return;
		}
		// Don't import times already imported, duh.
		$time_records = $project->get_associated_times();
		$already_imported = array();
		foreach ( $time_records as $time_id ) {
			$time = SI_Record::get_instance( $time_id );
			if ( ! is_a( $time, 'SI_Record' ) ) {
				continue;
			}
			$data = $time->get_data();
			if ( isset( $data['toggl_id'] ) ) {
				$already_imported[] = $data['toggl_id'];
			}
		}

		$entries = apply_filters( 'si_toggl_import_entries', $entries->data );
		foreach ( $entries as $key => $time_entry ) {
			if ( in_array( $time_entry->id, $already_imported ) ) {
				continue; // already imported
			}
			$data = array(
				'project_id' => (int) $project_id,
				'activity_id' => (int) self::get_projects_default_time_import_activity( $project_id ),
				'time_val' => apply_filters( 'sa_toggl_time_value', ( $time_entry->dur ) / 3600000, $time_entry ), // convert from milliseconds
				'note' => $time_entry->description,
				'date' => strtotime( $time_entry->start ),
				'toggl_id' => $time_entry->id,
			);
			$project->create_associated_time( $data );
		}
	}

	///////////////
	// Projects //
	///////////////

	public static function maybe_create_toggl_project( SI_Project $project, $args = array() ) {

		$data = array(
			'project' => array(
				'name' => $args['project_name'],
				),
			);
		$response = Toggl_API::api_request( 'projects', $data );
		if ( ! isset( $response->data->id ) ) {
			return; // not created
		}
		self::set_projects_toggl_id( $project->get_id(), $response->data->id );
		return $response->data->id;
	}

	public static function update_toggl_project( $post_id, $post ) {
		$toggl_id = self::get_projects_toggl_id( $post_id );
		if ( ! $toggl_id ) {
			// no id found to update
			return;
		}
		$data = array(
			'project' => array(
				'name' => $post->post_title,
				),
			);
		Toggl_API::api_request( 'projects/'.$toggl_id, $data );
	}
}
