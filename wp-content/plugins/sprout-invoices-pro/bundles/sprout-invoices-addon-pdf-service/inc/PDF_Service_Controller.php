<?php

/**
 * Sprout_PDFs_Controller Controller
 *
 * @package Sprout_Invoice
 * @subpackage Sprout_PDFs_Controller
 */
class SI_Sprout_PDFs_Controller extends SI_Controller {
	const QUERY_ARG = 'pdf';
	const PDC_VIEW_QUERY_ARG = 'genpdf';
	const USERNAME = '_si_pdf_username';
	const API_KEY = '_si_pdf_apikey_v2';
	const API_CB = 'https://sproutapps.co/';
	const DIRECTORY_PREFIX = 'si-pdfs';

	protected static $username;
	protected static $api_key;

	public static function init() {

		self::$username = get_option( self::USERNAME, 'sproutapps' );
		self::$api_key = get_option( self::API_KEY, false );

		// delete stored file on save
		add_action( 'save_post', array( __CLASS__, 'maybe_delete_stored_pdf' ), 10, 2 );
		add_action( 'si_new_payment', array( __CLASS__, 'maybe_delete_stored_pdf_after_new_payment' ) );
		add_action( 'si_invoice_status_updated', array( __CLASS__, 'maybe_delete_stored_pdf_after_status_update' ) );
		add_action( 'si_estimate_status_updated', array( __CLASS__, 'maybe_delete_stored_pdf_after_status_update' ) );

		// Uploads
		add_action( 'admin_init', array( __CLASS__, 'create_protection_files' ) );

		// login commpatibility
		add_action( 'si_login_bypass', array( __CLASS__, 'maybe_download_pdf' ) );

		// admin page
		add_filter( 'post_row_actions', array( __CLASS__, 'admin_download_pdf' ), 10, 2 );

	}

	public static function admin_download_pdf( $actions, $post ) {
		if ( SI_Estimate::POST_TYPE === $post->post_type || SI_Invoice::POST_TYPE === $post->post_type ) {
			$actions['pdf_link'] = sprintf( '<a href="%s"  id="quick_pdf_link" class="si_pdf" title="%s" target="_blank">%s</a>', self::get_doc_pdf_url( $post->ID ), __( 'View PDF', 'sprout-invoices' ), __( 'PDF', 'sprout-invoices' ) );
		}
		return $actions;
	}

	public static function get_username() {
		$username = self::$username;
		if ( ! $username ) {
			$username = SI_PDFC_UN;
		}
		return $username;
	}

	public static function get_api_key() {
		$api_key = self::$api_key;
		if ( ! $api_key ) {
			$api_key = self::get_default_api_key();
		}
		return $api_key;
	}

	public static function get_doc_pdf_url( $doc_id = 0, $generate = true ) {
		if ( ! $doc_id ) {
			$doc_id = get_the_ID();
		}
		/*/
		$pdf_file_path = self::get_generated_pdf( $doc_id );
		if ( ! $pdf_file_path && $generate ) {
			$pdf_file_path = SI_Sprout_PDFs_API::get_pdf( $doc_id );
		}
		$pdf_url = _convert_content_file_path_to_url( $pdf_file_path );
		/**/
		$pdf_url = add_query_arg( array( self::QUERY_ARG => 1 ), get_permalink( $doc_id ) );
		return $pdf_url;
	}

	public static function get_file_name( $doc_id = 0 ) {
		if ( ! $doc_id ) {
			$doc_id = get_the_ID();
		}
		if ( SI_DEV ) {
			return '123.pdf';
		}
		$doc_type = self::doc_type( $doc_id );

		$file_name = '';
		// Invoicing
		if ( 'invoice' === $doc_type ) {
			$file_name = apply_filters( 'si_pdf_invoice_file_name', 'invoice_' . $doc_id . '.pdf' );
		}

		// Estimates
		if ( 'estimate' === $doc_type ) {
			$file_name = apply_filters( 'si_pdf_estimate_file_name', 'estimate_' . $doc_id . '.pdf' );
		}

		return $file_name;
	}

	public static function get_generated_pdf( $doc_id = 0 ) {
		if ( ! $doc_id ) {
			$doc_id = get_the_ID();
		}
		$upload_dir = self::get_upload_path();
		$file_name = self::get_file_name( $doc_id );
		$pdf_file = trailingslashit( $upload_dir ) . $file_name;
		if ( ! file_exists( $pdf_file ) ) {
			return false;
		}
		if ( filesize( $pdf_file ) < 0.01 ) {
			do_action( 'si_error', 'PDF Generation Error', $doc_id );
		}
		return $pdf_file;
	}

	public static function doc_type( $doc_id = 0 ) {
		if ( ! $doc_id ) {
			$doc_id = get_the_ID();
		}
		$doc_type = 'invoice';
		if ( ! $doc_id ) {
			if ( SI_Invoice::is_invoice_query() ) {
				$doc_type = 'invoice';
			}
			if ( SI_Estimate::is_estimate_query() ) {
				$doc_type = 'estimate';
			}
		} else {
			$doc_type = si_get_doc_context( $doc_id );
		}
		return $doc_type;
	}

	public static function get_default_api_key() {

		$cached_key = get_transient( self::API_KEY );
		if ( $cached_key && '' !== $cached_key ) {
			return $cached_key;
		}

		$uid = ( class_exists( 'SI_Free_License' ) ) ? SI_Free_License::uid() : 0 ;
		$ref = ( $uid ) ? $uid : 'na' ;
		// data to send in our API request
		$api_params = array(
			'action' => 'sa_pdf_api',
			'item_name' => urlencode( self::PLUGIN_NAME ),
			'url' => urlencode( home_url() ),
			'uid' => $uid,
			'ref' => $ref,
			'license_key' => SI_Updates::license_key(),
		);

		// Call the custom API.
		$response = wp_safe_remote_get( add_query_arg( $api_params, self::API_CB . 'wp-admin/admin-ajax.php' ), array( 'timeout' => 15, 'sslverify' => false ) );
		// make sure the response came back okay
		if ( is_wp_error( $response ) ) {
			return false;
		}

		$key_info = json_decode( wp_remote_retrieve_body( $response ) );

		if ( ! isset( $key_info->unique_key ) ) {
			return $key_info;
		}
		$api_key = $key_info->unique_key;

		set_transient( self::API_KEY, $api_key, 5 * DAY_IN_SECONDS );

		return $api_key;
	}

	public static function get_content( $doc_id = 0 ) {
		if ( ! $doc_id ) {
			$doc_id = get_the_id();
		}
		$doc_url = get_permalink( $doc_id );
		$content = file_get_contents( $doc_url );
		return $content;
	}

	// actions

	public static function maybe_delete_stored_pdf( $post_id, $post ) {
		if ( ! in_array( $post->post_type, array( SI_Invoice::POST_TYPE, SI_Estimate::POST_TYPE ) ) ) {
			return;
		}
		self::delete_stored_pdf( $post_id );
	}

	public static function maybe_delete_stored_pdf_after_new_payment( SI_Payment $payment ) {
		$invoice_id = $payment->get_invoice_id();
		self::delete_stored_pdf( $invoice_id );
	}

	public static function maybe_delete_stored_pdf_after_status_update( $doc ) {
		$doc_id = $doc->get_id();
		self::delete_stored_pdf( $doc_id );
	}

	public static function delete_stored_pdf( $doc_id = 0 ) {
		$get_stored_pdf_path = self::get_generated_pdf( $doc_id );
		if ( ! file_exists( $get_stored_pdf_path ) ) {
			return;
		}
		unlink( $get_stored_pdf_path );
		return;
	}

	public static function maybe_download_pdf() {
		// todo use some sort of transient check, since this is rather insecure.
		if ( ! isset( $_GET[ self::PDC_VIEW_QUERY_ARG ] ) || ! $_GET[ self::PDC_VIEW_QUERY_ARG ] ) {
			return;
		}
		return true;
	}

	// Storing



	public static function set_upload_dir( $upload ) {

		// Override the year / month being based on the post publication date, if year/month organization is enabled
		if ( get_option( 'uploads_use_yearmonth_folders' ) ) {
			// Generate the yearly and monthly dirs
			$time = current_time( 'mysql' );
			$y = substr( $time, 0, 4 );
			$m = substr( $time, 5, 2 );
			$upload['subdir'] = "/$y/$m";
		}

		$upload['subdir'] = '/' . self::DIRECTORY_PREFIX . $upload['subdir'];
		$upload['path']   = $upload['basedir'] . $upload['subdir'];
		$upload['url']    = $upload['baseurl'] . $upload['subdir'];
		return $upload;
	}

	public static function get_upload_path() {
		add_filter( 'upload_dir', array( __CLASS__, 'set_upload_dir' ) );
		$wp_upload_dir = wp_upload_dir();
		wp_mkdir_p( $wp_upload_dir['path'] );
		$path = $wp_upload_dir['path'];

		return apply_filters( 'si_pdf_get_upload_path', $path );
	}

	public static function get_upload_dir() {
		$wp_upload_dir = wp_upload_dir();
		wp_mkdir_p( $wp_upload_dir['basedir'] . '/' . self::DIRECTORY_PREFIX );
		$path = $wp_upload_dir['basedir'] . '/' . self::DIRECTORY_PREFIX;

		return apply_filters( 'si_pdf_get_upload_dir', $path );
	}

	public static function create_protection_files( $force = false, $method = false ) {
		$transient_key = 'si_pdf_check_protection_files';
		if ( false === get_transient( $transient_key ) || $force ) {

			$upload_path = self::get_upload_dir();

			// Make sure the /edd folder is created
			wp_mkdir_p( $upload_path );

			// Prevent directory browsing and direct access to all files
			$rules = "Options -Indexes\n";
			$rules .= "deny from all\n";

			if ( file_exists( $upload_path . '/.htaccess' ) ) {
				$contents = @file_get_contents( $upload_path . '/.htaccess' );
				if ( $contents !== $rules || ! $contents ) {
					// Update the .htaccess rules if they don't match
					@file_put_contents( $upload_path . '/.htaccess', $rules );
				}
			} elseif ( wp_is_writable( $upload_path ) ) {
				// Create the file if it doesn't exist
				@file_put_contents( $upload_path . '/.htaccess', $rules );
			}

			// Top level blank index.php
			if ( ! file_exists( $upload_path . '/index.php' ) && wp_is_writable( $upload_path ) ) {
				@file_put_contents( $upload_path . '/index.php', '<?php' . PHP_EOL . '// Silence is golden.' );
			}

			// Now place index.php files in all sub folders
			$folders = self::scan_folders( $upload_path );
			foreach ( $folders as $folder ) {
				// Create index.php, if it doesn't exist
				if ( ! file_exists( $folder . 'index.php' ) && wp_is_writable( $folder ) ) {
					@file_put_contents( $folder . 'index.php', '<?php' . PHP_EOL . '// Silence is golden.' );
				}
			}
			// Check for the files once per day
			set_transient( $transient_key, true, 3600 * 24 );
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

	protected static function load_addon_view_to_string( $view, $args, $allow_theme_override = true ) {
		ob_start();
		self::load_addon_view( $view, $args, $allow_theme_override );
		return ob_get_clean();
	}

	public static function addons_view_path() {
		return SA_ADDON_SPROUT_PDFS_PATH . '/views/';
	}

	public static function scan_folders( $path = '', $return = array() ) {
		$path = ( '' === $path ) ? dirname( __FILE__ ) : $path;
		$lists = @scandir( $path );

		if ( ! empty( $lists ) ) {
			foreach ( $lists as $f ) {
				if ( is_dir( $path . DIRECTORY_SEPARATOR . $f ) && $f != '.' && $f != '..' ) {
					if ( ! in_array( $path . DIRECTORY_SEPARATOR . $f, $return ) ) {
						$return[] = trailingslashit( $path . DIRECTORY_SEPARATOR . $f ); }

					self::scan_folders( $path . DIRECTORY_SEPARATOR . $f, $return );
				}
			}
		}

		return $return;
	}
}
