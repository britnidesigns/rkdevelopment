<?php

/**
 * SI_Login Controller
 *
 * @package Sprout_Invoice
 * @subpackage SI_Login
 */
class SI_Login extends SI_Controller {
	const FORCE_LOGIN_OPTION = 'si_force_login';
	const FORCE_LOGIN_META = '_si_force_login';
	const PASSWORD_META = '_si_force_login_password';
	const PASSWORD_INPUT = 'si_force_login_password';
	const COOKIE = 'si_login_password';
	private static $default_force_login;

	public static function init() {

		self::$default_force_login = (bool)get_option( self::FORCE_LOGIN_OPTION, 0 );

		// Change template
		add_action( 'si_doc_template', array( __CLASS__, 'maybe_force_login_template' ) );

		add_action( 'si_head', array( __CLASS__, 'maybe_add_login_scripts' ) );

		add_filter( 'sprout_notifications', array( __CLASS__, 'add_notification_shortcode_compatibility' ), 100 );
		add_filter( 'sprout_notification_shortcodes', array( __CLASS__, 'add_notification_shortcode' ), 100 );

		if ( is_admin() ) {

			// register settings
			add_action( 'init', array( __CLASS__, 'register_settings' ) );

			// meta boxes
			add_action( 'doc_information_meta_box_client_row_last', array( __CLASS__, 'meta_add_pass_protection' ) );
			add_action( 'si_save_line_items_meta_box', array( __CLASS__, 'save_password_selection' ) );
		}
	}


	////////////////
	// Front-end //
	////////////////



	public static function maybe_add_login_scripts() {
		if ( self::force_login() ) {
			$login_css = apply_filters( 'si_login_stylesheet', SA_ADDON_LOGIN_URL . '/resources/login.css' );
			?>
				<link rel="stylesheet" id="login-css" href="<?php echo esc_url( $login_css ) ?>" type="text/css" media=""> 
			<?php

		}
	}

	public static function maybe_force_login_template( $template = '' ) {
		if ( isset( $_POST[self::PASSWORD_INPUT] ) ) {
			setcookie( self::COOKIE, sanitize_text_field( $_POST[self::PASSWORD_INPUT] ), time() + ( 60 * 60 * 2 ), COOKIEPATH, COOKIE_DOMAIN );
			// redirect after setting the cookie
			wp_redirect( get_the_permalink() );
			exit();
		}

		if ( ! self::force_login() ) {
			return $template;
		}

		return self::addons_view_path() . 'login/force-login.php';
	}


	/////////////
	// Options //
	/////////////

	/**
	 * Hooked on init add the settings page and options.
	 *
	 */
	public static function register_settings() {
		// Settings
		$settings = array(
			'force_login' => array(
				'weight' => 2001,
				'tab' => 'settings',
				'settings' => array(
					self::FORCE_LOGIN_OPTION => array(
						'label' => __( 'Force Login', 'sprout-invoices' ),
						'option' => array(
							'label' => __( 'Force Login By Default on All Estimates and Invoices', 'sprout-invoices' ),
							'type' => 'checkbox',
							'value' => '1',
							'default' => self::$default_force_login,
							'description' => __( 'Force logins by default for all invoices and estimates.', 'sprout-invoices' )
							)
						),
					)
				)
			);
		do_action( 'sprout_settings', $settings, self::SETTINGS_PAGE );
	}

	public static function meta_add_pass_protection( $doc ) {
		self::load_addon_view( 'admin/meta-boxes/force-login', array(
				'password' => self::get_doc_password( $doc->get_id() ),
				'force_login' => self::get_doc_forced_login( $doc->get_id() ),
				'fields' => self::meta_box_fields( $doc )
			), false );
	}

	public static function save_password_selection( $post_id = 0 ) {
		$doc_password = ( isset( $_POST['sa_force_login_doc_password'] ) ) ? sanitize_text_field( $_POST['sa_force_login_doc_password'] ) : '' ;
		self::set_doc_password( $post_id, $doc_password );

		$doc_forced_login = ( isset( $_POST['sa_force_login_doc_forced_login'] ) ) ? 1 : 0 ;
		if ( $doc_password != '' ) {
			// the password supercedes
			$doc_forced_login = 0;
		}
		self::set_doc_forced_login( $post_id, $doc_forced_login );
	}

	public static function meta_box_fields( $doc ) {
		$fields = array();
		$fields['doc_forced_login'] = array(
			'weight' => 20,
			'label' => __( 'Force the client to login.', 'sprout-invoices' ),
			'type' => 'checkbox',
			'default' => self::get_doc_forced_login( $doc->get_id() ),
			'value' => '1',
		);
		$fields['doc_password'] = array(
			'weight' => 10,
			'placeholder' => __( 'Password', 'sprout-invoices' ),
			'type' => 'input',
			'default' => self::get_doc_password( $doc->get_id() ),
			'attributes' => array( 'class' => 'small-input' ),
			'description' => __( 'Password protection supersedes requiring the client login.', 'sprout-invoices' )
		);
		$fields = apply_filters( 'si_force_login_meta_fields', $fields );
		uasort( $fields, array( __CLASS__, 'sort_by_weight' ) );
		return $fields;
	}

	//////////
	// Meta //
	//////////

	public static function get_doc_password( $doc_id = 0 ) {
		if ( ! $doc_id ) {
			$doc_id = get_the_ID();
		}
		return get_post_meta( $doc_id, self::PASSWORD_META, true );
	}

	public static function set_doc_password( $doc_id = 0, $password = '' ) {
		if ( ! $doc_id ) {
			$doc_id = get_the_ID();
		}
		return update_post_meta( $doc_id, self::PASSWORD_META, $password );
	}

	public static function is_pass_protected( $doc_id = 0 ) {
		if ( ! $doc_id ) {
			$doc_id = get_the_ID();
		}
		$option = self::get_doc_password( $doc_id );
		return ( $option != '' ) ? true : false ;
	}

	public static function get_doc_forced_login( $doc_id = 0 ) {
		if ( ! $doc_id ) {
			$doc_id = get_the_ID();
		}
		$option = get_post_meta( $doc_id, self::FORCE_LOGIN_META, true );
		if ( $option == '' ) {
			$option = self::$default_force_login;
		}
		return $option;
	}

	public static function set_doc_forced_login( $doc_id = 0, $option = 0 ) {
		if ( ! $doc_id ) {
			$doc_id = get_the_ID();
		}
		$option = update_post_meta( $doc_id, self::FORCE_LOGIN_META, $option );
		return $option;
	}

	///////////////
	// Passwords //
	///////////////

	public static function is_client_check_cookie( $bool = false ) {
		if ( isset( $_COOKIE[self::COOKIE] ) ) {
			$password = $_COOKIE[self::COOKIE];
			$set_password = self::get_doc_password();
			return ( $password == $set_password );
		}
		return false;
	}


	public static function add_notification_shortcode_compatibility( $notifications = array() ) {
		foreach ( $notifications as $key => $data ) {
			if ( in_array( $key, array( 'send_estimate', 'send_invoice', 'deposit_payment', 'reminder_payment', 'final_payment' ) ) ) { // don't show for admin notifications
				$notifications[$key]['shortcodes'] = array_merge( $notifications[$key]['shortcodes'], array( 'doc_password' ) );
			}
		}
		return $notifications;
	}

	public static function add_notification_shortcode( $default_shortcodes = array() ) {
		$new_shortcodes = array(
			'doc_password' => array(
				'description' => __( 'Used to provide the client with the password, if one is set. Format if password present: "Password: private-key-123"', 'sprout-invoices' ),
				'callback' => array( __CLASS__, 'shortcode_password' )
			)
		);
		return array_merge( $new_shortcodes, $default_shortcodes );
	}

	public static function shortcode_password( $atts, $content, $code, $data ) {
		$doc_id = 0;
		if ( isset( $data['invoice'] ) && is_a( $data['invoice'], 'SI_Invoice' ) ) {
			$doc_id = $data['invoice']->get_ID();
		}
		if ( isset( $data['estimate'] ) && is_a( $data['estimate'], 'SI_Estimate' ) ) {
			$doc_id = $data['estimate']->get_ID();
		}

		if ( $doc_id ) {
			$password = self::get_doc_password( $doc_id );
			if ( $password ) {
				return sprintf( __( 'Password: %s', 'sprout-invoices' ), $password );
			}
		}
		return '';
	}


	/////////////
	// Utility //
	/////////////

	public static function doc_has_forced_login( $doc_id = 0 ) {
		if ( ! $doc_id ) {
			$doc_id = get_the_ID();
		}

		if ( self::is_pass_protected( $doc_id ) ) {
			return true;
		}
		return self::get_doc_forced_login( $doc_id );
	}

	public static function force_login() {
		// Only doc pages
		if ( ! is_single() ) {
			return false;
		}

		if ( current_user_can( 'edit_sprout_invoices' ) ) {
			return false;
		}

		$force_login = false;
		if ( self::doc_has_forced_login() ) {
			$force_login = true;

			// Entered password
			if ( self::is_client_check_cookie() ) {
				$force_login = false;
			}

			// check if user has access to the doc
			if ( is_user_logged_in() ) {
				$doc = si_get_doc_object( get_the_ID() );
				$client = $doc->get_client();
				$client_user_ids = $client->get_associated_users();
				if ( in_array( get_current_user_id(), $client_user_ids ) ) {
					$force_login = false;
				}
			}
		}

		return $force_login;

	}

	public static function load_addon_view( $view, $args, $allow_theme_override = true ) {
		add_filter( 'si_views_path', array( __CLASS__, 'addons_view_path' ) );
		$view = self::load_view( $view, $args, $allow_theme_override );
		remove_filter( 'si_views_path', array( __CLASS__, 'addons_view_path' ) );
		return $view;
	}

	protected static function load_addon_view_to_string( $view, $args, $allow_theme_override = true ) {
		ob_start();
		self::load_addon_view( $view, $args, $allow_theme_override );
		return ob_get_clean();
	}

	public static function addons_view_path() {
		return SA_ADDON_LOGIN_PATH . '/views/';
	}


}