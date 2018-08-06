<?php

/**
 * Doc Comments Controller
 *
 * @package Sprout_Invoice
 * @subpackage Doc_Comments
 */
class SI_Doc_Comments extends SI_Controller {
	const DOC_COMMENT_TYPE = 'si_doc_comment';
	const DEPRECATED_DOC_COMMENT_META_POS = 'si_line_item';
	const DOC_COMMENT_META_POS = 'si_line_item_id';

	private static $meta_keys = array();

	public static function init() {

		if ( is_admin() ) {
			// Enqueue
			add_action( 'admin_enqueue_scripts', array( __CLASS__, 'register_resources' ) );
			add_action( 'admin_enqueue_scripts', array( __CLASS__, 'admin_enqueue' ), 20 );
		}

		// Enqueue
		add_action( 'si_head', array( __CLASS__, 'si_add_stylesheet' ) );

		// Add comment options for the line items
		add_action( 'si_get_front_end_line_item_post_row', array( __CLASS__, 'add_line_item_comment_form' ), 10, 4 );
		add_action( 'si_get_front_end_line_item_pre_row', array( __CLASS__, 'toggle_line_item_comments' ), 10, 4 );
		// backwards compatibility before 8.0
		add_action( 'si_line_item_build_row', array( __CLASS__, '_add_line_item_comment_form' ), 10, 4 );
		add_action( 'si_line_item_build_pre_row', array( __CLASS__, '_toggle_line_item_comments' ), 10, 4 );

		// Add comment action on the admin edit screen
		if ( is_admin() ) {
			add_action( 'si_line_item_build_option_action_row', array( __CLASS__, 'add_option_action' ), 10, 4 );
		}

		// Add comments to history
		add_action( 'si_doc_history_records_pre_sort', array( __CLASS__, 'add_comments_to_history' ), 10, 3 );

		// Filter from widget and others places
		add_action( 'pre_get_comments', array( __CLASS__, 'hide_comments' ) );

		// Notifications

		// AJAX
		add_action( 'wp_ajax_sa_create_doc_comment',  array( get_class(), 'maybe_create_doc_comment' ), 10, 0 );
		add_action( 'wp_ajax_nopriv_sa_create_doc_comment',  array( get_class(), 'maybe_create_doc_comment' ), 10, 0 );

		add_action( 'wp_ajax_sa_comments_view',  array( __CLASS__, 'comments_view' ), 10, 0 );
		add_action( 'wp_ajax_nopriv_sa_comments_view',  array( get_class(), 'comments_view' ), 10, 0 );
		add_action( 'wp_ajax_sa_comments_admin_view',  array( __CLASS__, 'comments_admin_view' ), 10, 0 );
		add_filter( 'si_admin_scripts_localization',  array( __CLASS__, 'ajax_l10n' ) );

		// Upgrade older comments
		add_action( 'si_save_line_items_meta_box', array( __CLASS__, 'maybe_update_older_comments_item_ids' ) );
	}

	// Filter out.

	/**
	 * Exclude comments from showing in Recent Comments widgets
	 *
	 * @since 1.4.1
	 * @param obj $query WordPress Comment Query Object
	 * @return void
	 */
	public static function hide_comments( $query ) {
		if ( is_admin() ) {
			return;
		}
	    global $wp_version;

		if ( version_compare( floatval( $wp_version ), '4.1', '>=' ) ) {

			if ( isset( $query->query_vars['type'] ) && self::DOC_COMMENT_TYPE === $query->query_vars['type'] ) {
				return;
			}

			$types = array();
			if ( isset( $query->query_vars['type__not_in'] ) ) {
				$type = ( is_array( $query->query_vars['type__not_in'] ) ) ? $query->query_vars['type__not_in'] : array( $query->query_vars['type__not_in'] ) ;
			}
			$types[] = self::DOC_COMMENT_TYPE;
			$query->query_vars['type__not_in'] = $types;
		}
	}


	//////////////
	// Enqueue //
	//////////////

	public static function register_resources() {
		// admin js
		wp_register_script( 'si_doc_comments', SA_ADDON_DOC_COMMENTS_URL . '/resources/admin/js/comments.js', array( 'jquery' ), self::SI_VERSION );
	}

	public static function admin_enqueue() {
		add_thickbox();
		wp_enqueue_script( 'si_doc_comments' );
	}

	public static function si_add_stylesheet() {
		echo '<script type="text/javascript" src="' . SA_ADDON_DOC_COMMENTS_URL . '/resources/front-end/js/doc_comments.js"></script>';
	}

	///////////////
	// Frontend //
	///////////////

	public static function add_line_item_comment_form( $data = array(), $position = 1.0, $prev_type = '', $has_children = false ) {
		if ( ! $has_children ) {
			$_id = self::get_comment_line_item_id( $position, get_the_id() );
			$comments = self::get_line_item_comments( get_the_id(), $_id );
			echo '<div class="line_items_comments_wrap">'; // a wrapper is used to keep ajax loading simple
			print self::load_addon_view( 'public/line-items-comments', array(
					'doc_id' => get_the_id(),
					'position' => $_id,
					'comments' => $comments,
			), false );
			echo '</div>';
		}
	}

	public static function _add_line_item_comment_form( $data = array(), $items = array(), $position = 1.0, $children = array() ) {
		$has_children = ( empty( $children ) ) ? false : true ;
		self::add_line_item_comment_form( $data, $position, '', $has_children );
	}

	public static function add_comment_form() {
		$comments = self::get_doc_comments();
		self::load_addon_view( 'public/doc-comments', array(
				'comments' => $comments,
		), false );
	}

	public static function toggle_line_item_comments( $data = array(), $position = 1.0, $prev_type = '', $has_children = false ) {
		if ( ! $has_children ) {
			$_id = self::get_comment_line_item_id( $position, get_the_id() );
			$comments = self::get_line_item_comments( get_the_id(), $_id );
			$active = ( ! empty( $comments ) ) ? 'has_comments' : '' ;
			$toggle = '<a href="javascript:void(0)" class="li_comments_toggle '.$active.'" data-li_position="'.str_replace( '.', '-', $_id ).'"><span class="dashicons dashicons-format-chat"></span></a>';
			echo apply_filters( 'si_toggle_line_item_comments', $toggle );
		}
	}

	public static function _toggle_line_item_comments( $data = array(), $items = array(), $position = 1.0, $children = array() ) {
		$has_children = ( empty( $children ) ) ? false : true ;
		self::toggle_line_item_comments( $data, $position, '', $has_children );
	}

	public static function add_comments_to_history( $history = array(), $doc_id = 0, $filtered = true ) {
		$comment_ids = array();
		$comments = self::get_doc_comments( $doc_id );
		foreach ( $comments as $comment ) {
			$comment_ids[] = $comment->comment_ID;
		}
		return array_merge( $comment_ids, $history );
	}

	public static function comments_view() {
		if ( isset( $_REQUEST['doc_id'] ) ) {
			$doc_id = $_REQUEST['doc_id'];
		}

		if ( ! $doc_id ) {
			self::ajax_fail( 'No doc id' );
		}

		$position = '';
		if ( isset( $_REQUEST['li_position'] ) ) {
			$position = $_REQUEST['li_position'];
		}

		$_id = self::get_comment_line_item_id( $position, $doc_id );
		$comments = self::get_line_item_comments( $doc_id, $_id );
		print self::load_addon_view( 'public/line-items-comments', array(
				'doc_id' => $doc_id,
				'position' => $_id,
				'comments' => $comments,
		), false );
		exit();
	}

	public static function get_comment_line_item_id( $position = 0.00, $doc_id = 0 ) {
		if ( ! $doc_id ) {
			$doc_id = get_the_id();
		}
		$id = si_get_line_item_value( $doc_id, $position, '_id' );
		if ( '' === $id ) {
			// fallback to the line item position if this line item is pre 9.0
			return $position;
		}
		return $id;
	}

	////////////
	// admin //
	////////////

	public static function add_option_action( $data = array(), $items = array(), $position = 1.0, $children = array() ) {
		$doc_id = get_the_id();
		if ( ! $doc_id ) {
			return;
		}
		if ( empty( $children ) ) {
			$_id = self::get_comment_line_item_id( $position );
			$comments = self::get_line_item_comments( get_the_id(), $_id );
			$active = ( ! empty( $comments ) ) ? 'has_comments' : '' ;
			$toggle = '<div class="item_action item_comments show_doc_comments_modal '.$active.'" data-doc_id="'.$doc_id.'" data-li_position="'.$_id.'"><span class="dashicons dashicons-format-chat"></span></div>';
			echo apply_filters( 'si_admin_option_action_comments', $toggle );
		}
	}

	////////////////
	// AJAX View //
	////////////////

	public static function ajax_l10n( $js_object = array() ) {
		$js_object['doc_comments_modal_title'] = __( 'Line Item Discussion', 'sprout-invoices' );
		$js_object['doc_comments_modal_url'] = admin_url( 'admin-ajax.php?action=sa_comments_admin_view&height=300' );
		$js_object['doc_comments_success_message'] = __( 'Comment Added!', 'sprout-invoices' );
		return $js_object;
	}

	public static function comments_admin_view() {
		if ( ! current_user_can( 'edit_sprout_invoices' ) ) {
			self::ajax_fail( 'User cannot create new posts!' );
		}

		if ( isset( $_REQUEST['doc_id'] ) ) {
			$doc_id = $_REQUEST['doc_id'];
		}

		if ( ! $doc_id ) {
			self::ajax_fail( 'No doc id' );
		}

		$position = '';
		if ( isset( $_REQUEST['li_position'] ) ) {
			$position = $_REQUEST['li_position'];
		}

		$_id = self::get_comment_line_item_id( $position, $doc_id );
		$comments = self::get_line_item_comments( $doc_id, $_id );
		self::load_addon_view( 'admin/section/comments-modal', array(
				'comments' => $comments,
				'position' => $position,
				'doc_id' => $doc_id,
		), false );
		exit();
	}

	///////////////
	// Comments //
	///////////////

	public static function create_comment( $doc_id = 0, $data = array(), $line_item = 0 ) {
		if ( ! $doc_id ) {
			$doc_id = get_the_ID();
		}

		$doc = si_get_doc_object( $doc_id );
		if ( ! is_a( $doc, 'SI_Estimate' ) || ! is_a( $doc, 'SI_Invoice' ) ) {
			return 0;
		}

		$defaults = array(
			'comment_post_ID' => $doc_id,
			'comment_content' => __( 'Comment N/A', 'sprout-invoices' ),
			'user_id' => 0,
			'comment_author_email' => 'unknown',
			'comment_author' => 'unknown',
			'comment_author_url' => 'unknown',
			'comment_type' => self::DOC_COMMENT_TYPE,
			'comment_parent' => 0,
			'comment_author_IP' => '127.0.0.1',
			'comment_agent' => 'Mozilla/5.0 (Windows; U; Windows NT 5.1; en-US; rv:1.9.0.10) Gecko/2009042316 Firefox/3.0.10 (.NET CLR 3.5.30729)',
			'comment_date' => current_time( 'mysql' ),
			'comment_approved' => 1,
		);
		$data = wp_parse_args( $data, $defaults );

		$comment_id = wp_insert_comment( $data );
		if ( $line_item ) {
			add_comment_meta( $comment_id, self::DOC_COMMENT_META_POS, $line_item );
		}
		do_action( 'si_insert_doc_comment', $comment_id, $doc, $data, $line_item );
		return $comment_id;
	}


	public static function get_doc_comments( $doc_id = 0 ) {
		if ( ! $doc_id ) {
			$doc_id = get_the_ID();
		}
		$args = array(
			'post_id' => $doc_id,
			'type' => self::DOC_COMMENT_TYPE,
			'order' => 'ASC',
			);
		$query = new WP_Comment_Query;
		$comments = $query->query( $args );
		return $comments;
	}

	public static function get_line_item_comments( $doc_id = 0, $id = 0 ) {
		if ( ! $doc_id ) {
			$doc_id = get_the_ID();
		}
		$args = array(
			'post_id' => (int) $doc_id,
			'type' => self::DOC_COMMENT_TYPE,
			'order' => 'ASC',
			'meta_query' => array(
				'relation' => 'OR',
				array(
					'key' => self::DOC_COMMENT_META_POS,
					'value' => (float) $id,
					),
				array(
					'key' => self::DEPRECATED_DOC_COMMENT_META_POS,
					'value' => (float) $id,
					),
				),
			);
		$query = new WP_Comment_Query;
		$comments = $query->query( $args );
		return $comments;
	}

	public static function maybe_update_older_comments_item_ids( $doc_id = 0 ) {
		if ( ! $doc_id ) {
			$doc_id = get_the_ID();
		}
		$args = array(
			'post_id' => (int) $doc_id,
			'type' => self::DOC_COMMENT_TYPE,
			'order' => 'ASC',
			'meta_query' => array(
				array(
					'key' => self::DEPRECATED_DOC_COMMENT_META_POS,
					),
				),
			);
		$query = new WP_Comment_Query;
		$comments = $query->query( $args );
		if ( empty( $comments ) ) {
			return;
		}
		foreach ( $comments as $comment ) {
			$position = $comment->meta_value;
			$_id = self::get_comment_line_item_id( $position, $doc_id );
			add_comment_meta( $comment->comment_ID, self::DOC_COMMENT_META_POS, $_id );
			delete_comment_meta( $comment->comment_ID, self::DEPRECATED_DOC_COMMENT_META_POS );
		}
	}

	public static function comment_data( $doc_id = 0, $comment = '' ) {
		$user_id = 0;
		$author_email = __( 'unknown', 'sprout-invoices' );
		$author = __( 'unknown', 'sprout-invoices' );
		$author_url = __( 'unknown', 'sprout-invoices' );

		$user = wp_get_current_user();
		$doc = si_get_doc_object( $doc_id );

		// If the user isn't logged in than determine comment info from
		// the docs client info
		if ( ! $user->exists() ) {
			$client = $doc->get_client();
			if ( ! is_wp_error( $client ) ) {
				$client_users = $client->get_associated_users();
				$client_user_id = array_shift( $client_users );
				$user = get_userdata( $client_user_id );
			}
		}

		// Use the user data to set the comment info
		if ( $user->exists() ) {
			$user_id = $user->ID;
			$author_email = $user->user_email;
			$author = $user->display_name;
			$author_url = $user->website;
		}
		$data = array(
			'comment_post_ID' => $doc_id,
			'comment_content' => $comment,
			'user_id' => $user_id,
			'comment_author_email' => $author_email,
			'comment_author' => $author,
			'comment_author_url' => $author_url,
		);
		return $data;
	}

	///////////
	// AJAX //
	///////////

	public static function maybe_create_doc_comment() {
		if ( ! isset( $_REQUEST['doc_comment_sec'] ) ) {
			self::ajax_fail( 'Forget something?' ); }

		$nonce = $_REQUEST['doc_comment_sec'];
		if ( ! wp_verify_nonce( $nonce, SI_Controller::NONCE ) ) {
			self::ajax_fail( 'Not going to fall for it!' ); }

		if ( ! isset( $_REQUEST['comment'] ) ) {
			self::ajax_fail( 'No comment submitted.' );
		}

		if ( $_REQUEST['comment'] == '' ) {
			self::ajax_fail( 'No comment submitted.' );
		}

		if ( ! isset( $_REQUEST['id'] ) ) {
			self::ajax_fail( 'No doc associated.' );
		}

		$doc_id = (int) $_REQUEST['id'];
		$doc = si_get_doc_object( $doc_id );
		if ( ! is_a( $doc, 'SI_Estimate' ) && ! is_a( $doc, 'SI_Invoice' ) ) {
			self::ajax_fail( 'Not correct post type.' );
		}

		$comment = esc_textarea( $_REQUEST['comment'] );
		$item_position = ( isset( $_REQUEST['item_position'] ) ) ? (float) $_REQUEST['item_position'] : 0 ;
		$comment_id = self::create_comment( $doc_id, self::comment_data( $doc_id, $comment ), $item_position );
		if ( ! $comment_id ) {
			self::ajax_fail( 'Something went wrong.' );
		}

		$response_data = array(
				'comment' => $comment,
				'comment_id' => $comment_id,
				'position' => $item_position,
				'response' => __( 'Comment Received', 'sprout-invoices' ),
			);
		header( 'Content-type: application/json' );
		if ( self::DEBUG ) { header( 'Access-Control-Allow-Origin: *' ); }
		echo wp_json_encode( $response_data );
		exit();
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
		return SA_ADDON_DOC_COMMENTS_PATH . '/views/';
	}
}
