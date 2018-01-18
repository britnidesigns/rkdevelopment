<?php

/**
 * Controller
 * Adds meta boxes to client admin.
 */
class SI_Point_of_Contact extends SI_Controller {
	const POC_OPTION = 'client_poc';
	const POC_META = '_client_poc';

	public static function init() {
		// disabled notification if not poc
		add_filter( 'si_disable_this_notification', array( __CLASS__, 'maybe_disable_notification' ), 100, 4 );

		add_filter( 'client_submit_pre_invoices', array( __CLASS__, 'add_poc_option' ), -100 );
		add_action( 'save_post', array( __CLASS__, 'save_client_options' ), 10, 2 );
	}

	public static function maybe_disable_notification( $bool, $data = array(), $to = '', $notification_name = '' ) {
		// if already disabled don't override
		if ( $bool ) {
			return true; // true is to disable
		}

		if ( in_array( $notification_name, array( 'estimate_submitted', 'accepted_estimate', 'declined_estimate', 'payment_notification', 'admin_comment_notification_estimate', 'admin_comment_notification_invoice' ) ) ) {
			return $bool;
		}

		// get user id
		$user = get_user_by( 'email', $to );
		if ( ! is_a( $user, 'WP_User' ) ) {
			return $bool;
		}

		// get client
		$client = self::find_client_from_notification_data( $data );
		if ( ! $client ) {
			return $bool;
		}

		$poc_user_id = self::get_client_poc( $client->get_id() );
		$to_user_id = $user->ID;

		if ( (int) $poc_user_id ) {
			// check point of contact
			if ( (int) $to_user_id !== (int) $poc_user_id ) {
				$bool = true; // true is to disable
			}
		}

		return $bool;
	}

	public static function find_client_from_notification_data( $data = array() ) {
		$client = '';

		if ( isset( $data['client'] ) ) {
			$client = $data['client'];
		}

		if ( ! is_a( $client, 'SI_Client' ) && isset( $data['client_id'] ) ) {
			$client = SI_Client::get_instance( (int) $data['client_id'] );
		}

		if ( ! is_a( $client, 'SI_Client' ) && isset( $data['invoice'] ) && is_a( $data['invoice'], 'SI_Invoice' ) ) {
			if ( is_a( $data['invoice'], 'SI_Invoice' ) ) {
				$client = $data['invoice']->get_client();
			}
		}

		if ( ! is_a( $client, 'SI_Client' ) && isset( $data['estimate'] )&& is_a( $data['estimate'], 'SI_Estimate' ) ) {
			$client = $data['estimate']->get_client();
			if ( is_a( $client, 'SI_Client' ) ) {
				$client_id = $client->get_id();
			}
		}

		if ( ! is_a( $client, 'SI_Client' ) && isset( $data['invoice_id'] ) ) {
			$invoice = SI_Invoice::get_instance( (int) $data['invoice_id'] );
			if ( is_a( $invoice, 'SI_Invoice' ) ) {
				$client = $invoice->get_client();
			}
		}

		if ( ! is_a( $client, 'SI_Client' ) && isset( $data['estimate_id'] ) ) {
			$estimate = SI_Estimate::get_instance( (int) $data['estimate_id'] );
			if ( is_a( $estimate, 'SI_Estimate' ) ) {
				$client = $estimate->get_client();
			}
		}

		if ( ! is_a( $client, 'SI_Client' ) ) {
			return false;
		}

		return $client;
	}


	public static function add_poc_option() {
		$client_id = get_the_id();
		$client = SI_Client::get_instance( $client_id );
		$selected_id = self::get_client_poc( $client_id );
		$associated_users = $client->get_associated_users();
		self::load_addon_view( 'poc-option', array(
				'client' => $client,
				'client_id' => $client_id,
				'associated_users' => $associated_users,
				'selected_id' => $selected_id,
				'field_name' => self::POC_OPTION,
		) );
	}

	/**
	 * Save client options on advanced meta box save action
	 * @param  integer $post_id
	 * @return
	 */
	public static function save_client_options( $post_id, $post ) {
		if ( $post->post_type == SI_Client::POST_TYPE ) {

			$poc_id = ( isset( $_POST[ self::POC_OPTION ] ) ) ? wp_unslash( sanitize_title( $_POST[ self::POC_OPTION ] ) ) : '' ;

			self::set_client_poc( $post_id, $poc_id );
		}

	}

	public static function set_client_poc( $client_id = 0, $poc_id = 0 ) {
		update_post_meta( $client_id, self::POC_META, (int) $poc_id );
		return $poc_id;
	}

	public static function get_client_poc( $client_id = 0 ) {
		$poc_id = get_post_meta( $client_id, self::POC_META, true );
		return $poc_id;
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

	public static function load_addon_view_to_string( $view, $args, $allow_theme_override = true ) {
		ob_start();
		self::load_addon_view( $view, $args, $allow_theme_override );
		return ob_get_clean();
	}

	public static function addons_view_path() {
		return SA_ADDON_CLIENT_POC_PATH . '/views/';
	}
}
SI_Point_of_Contact::init();
