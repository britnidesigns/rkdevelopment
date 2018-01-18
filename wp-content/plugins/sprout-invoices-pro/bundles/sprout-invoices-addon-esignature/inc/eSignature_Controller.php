<?php

/**
 * eSignature_Controller Controller
 *
 * @package Sprout_Invoice
 * @subpackage eSignature_Controller
 */
class eSignature_Controller extends SI_Controller {
	const RECORD = 'si_esignature';
	const OPTION_META = '_si_esignature_required';
	const AJAX_ACTION = 'si_save_signature';
	const DIRECTORY_PREFIX = 'si-signs';

	public static function init() {

		add_action( 'si_head', array( __CLASS__, 'add_esig_resources' ), 10000000 );

		add_action( 'si_doc_actions_pre', array( __CLASS__, 'signature_required_button' ) );

		add_action( 'si_signature_button', array( __CLASS__, 'signature_required_button' ) );

		add_action( 'si_doc_wrap_end', array( __CLASS__, 'signature_section' ) );

		add_action( 'si_signature_section', array( __CLASS__, 'signature_section' ) );

		// AJAX
		add_action( 'wp_ajax_' . self::AJAX_ACTION, array( get_class(), 'maybe_save_doc_signature' ) );
		add_action( 'wp_ajax_nopriv_' . self::AJAX_ACTION, array( get_class(), 'maybe_save_doc_signature' ) );

		// Uploads
		add_action( 'admin_init', array( __CLASS__, 'create_protection_files' ) );
	}

	public static function add_esig_resources() {
		if ( ! self::doc_needs_sig() ) {
			return;
		}
		ob_start();
		?>
			<script type="text/javascript" src="<?php echo SA_ADDON_SI_ESIGNATURE_URL . '/resources/ss/ss.js' ?>"></script>
			<script type="text/javascript">
				var signObjects = new Array('ctlSignature');
				var objctlSignature = new SuperSignature({SignObject:"ctlSignature",SignWidth: "450",TransparentSign:"true",SignHeight: "300",IeModalFix: false,PenColor: "#0000FF",BorderStyle: "Dashed",BorderWidth: "2px",BackColor: "#FFFFFF", BorderColor: "#DDDDDD",RequiredPoints: "15",ClearImage:"<?php echo SA_ADDON_SI_ESIGNATURE_URL ?>/resources/ss/erase.png", PenCursor:"<?php echo SA_ADDON_SI_ESIGNATURE_URL ?>/resources/ss/pen.cur", StartMessage: "<?php _e( 'Please provide your signature above.', 'sprout-invoices' ) ?>", ErrorMessage: "<?php _e( 'Please continue with a valid signature above.', 'sprout-invoices' ) ?>",SuccessMessage: "<?php _e( 'Thank you! Please save your signature before proceeding.', 'sprout-invoices' ) ?>", SignzIndex: 1000, Visible: "true", forceMouseEvent: true, Enabled: "true"});
			</script>
			<script type="text/javascript">
				var $ = jQuery.noConflict();
				//<![CDATA[
				jQuery(document).ready( function($) {
					
					objctlSignature.Init();

					$("#sign_doc").on('click', function(event) {

						// scroll to signature
						$('html, body').animate({
							scrollTop: $("#ctlSignature_Container").offset().top-150
						}, 1000);
					});

					$("#save_signature_via_ajax").on('click', function(event) {

						if ( ! ValidateSignature() ) {
							return;
						}

						var sig_data = $("#ctlSignature_data").val(),
							sig_data_smooth = $("#ctlSignature_data_canvas").val();
						
						$(this).after(si_js_object.inline_spinner);

						$.post( si_js_object.ajax_url, { action: '<?php echo self::AJAX_ACTION ?>', invoice_id: <?php echo get_the_ID() ?>, sig_data: sig_data, sig_canvas_data: sig_data_smooth, nonce: si_js_object.security },
							function( response ) {
								$('.spinner').hide();
								if ( response.success ) {

									// hide sig options
									$(this).hide();
									$('#sign_doc').hide();
									$('#ctlSignature_toolbar').hide();

									// show signature
									$('#si_signature_wrap').html( response.data.img );

									// Add ability to pay
									$('.button.status_change').show();
									$('a.button.primary_button.purchase_button').show();
									$('a.button.primary_button.payment_option').show();
									// New Theme
									$('#paybar .inner .button.open').show();
									
									// scroll to the top
									$('html, body').animate({
										scrollTop: $("#outer_doc_wrap").offset().top-150
									}, 500);
								}
								else {
									$("#save_signature_via_ajax").after('<span class="inline_message inline_error_message">' + response.data.message + '</span>');
								};
							}
						);
					});

				});
				//]]>
			</script>
		<?php
		$css = ob_get_clean();
		print apply_filters( 'si_add_esig_resources', $css );
	}

	public static function signature_required_button() {
		if ( ! self::doc_needs_sig() ) {
			return;
		}
		self::signature_required_button_view();
		return;
	}


	public static function signature_required_button_view() {
		ob_start();
		?>
			<style type="text/css">
				.button.status_change, a.button.primary_button.purchase_button, a.button.primary_button.payment_option {
				    display: none;
				}
				.button.status_change[data-status-change='decline'] {
				    display: inline-block !important;
				}
			</style>
			<a id="sign_doc" class="button signature_button" href="javascript:void(0)"><?php _e( 'Signature Required', 'sprout-invoices' ) ?></a>
		<?php
		$button = ob_get_clean();
		print apply_filters( 'si_signature_required_button', $button );
	}

	public static function signature_section() {
		if ( ! self::doc_needs_sig() ) {
			$signature = self::get_doc_signature();
			print $signature;
			return;
		}

		self::load_addon_view( 'public/signature-section', array() );
		return;
	}

	public static function doc_needs_sig( $doc_id = 0 ) {
		if ( ! $doc_id ) {
			$doc_id = get_the_id();
		}

		if ( self::is_doc_signed( $doc_id ) ) {
			return false;
		}

		$required = self::is_signature_required( $doc_id );
		if ( 'false' === $required || ! $required ) {
			return false;
		}
		return true;
	}

	public static function is_doc_signed( $doc_id = 0 ) {
		if ( ! $doc_id ) {
			$doc_id = get_the_ID();
		}
		$signature = self::get_doc_signature( $doc_id );
		return ( false === $signature ) ? false : true ;
	}

	public static function get_doc_signature( $doc_id = 0 ) {
		if ( ! $doc_id ) {
			$doc_id = get_the_ID();
		}

		$signed = false;

		$doc = si_get_doc_object( $doc_id );
		$history = $doc->get_history();
		foreach ( $history as $item_id ) {
			$record = SI_Record::get_instance( $item_id );
			if ( self::RECORD === $record->get_type() ) {
				$data = $record->get_data();
				if ( strpos( $data, 'img' ) !== false ) {
					$signed = $data;
				}
			}
		}

		return apply_filters( 'si_was_doc_signed', $signed, $doc_id );
	}


	//////////
	// Meta //
	//////////

	public static function is_signature_required( $doc_id = 0 ) {
		$option = self::get_doc_signature_required_option( $doc_id );
		$r = ( 'false' !== $option ) ? true : false ;
		return apply_filters( 'si_is_signature_required', $r, $doc_id );
	}

	public static function get_doc_signature_required_option( $doc_id = 0 ) {
		$option = get_post_meta( $doc_id, self::OPTION_META, true );
		return apply_filters( 'si_get_doc_signature_required_option', $option, $doc_id );
	}

	public static function save_doc_signature_required_option( $doc_id = 0, $option = true ) {
		update_post_meta( $doc_id, self::OPTION_META, $option );
	}

	//////////
	// AJAX //
	//////////

	public static function maybe_save_doc_signature() {
		$nonce = $_REQUEST['nonce'];
		if ( ! wp_verify_nonce( $nonce, self::NONCE ) ) {
			wp_send_json_error( array( 'message' => __( 'Not going to fall for it!', 'sprout-invoices' ) ) );
		}

		$sign_data = ( strlen( $_REQUEST['sig_data'] ) > 0 ) ? $_REQUEST['sig_data'] : false ;
		$sign_data_smooth = ( strlen( $_REQUEST['sig_canvas_data'] ) > 0 ) ? $_REQUEST['sig_canvas_data'] : false ;
		$doc_id = (int) $_REQUEST['invoice_id'];

		if ( $sign_data ) {
			$image = GetSignatureImage( $sign_data );
		} elseif ( $sign_data_smooth ) {
			$image = GetSignatureImageSmooth( $sign_data_smooth );
		} else {
			wp_send_json_error( array( 'message' => __( 'Missing signature data!', 'sprout-invoices' ) ) );
		}

		$transparent_image = si_set_img_transparency( $image );

		$file_name = self::get_file_name( $doc_id );
		$upload_dir = self::get_upload_path();
		$uploaded_file = trailingslashit( $upload_dir ) . $file_name;

		imagepng( $transparent_image, $uploaded_file, 0 , null );

		if ( is_user_logged_in() ) {
			$user = get_userdata( get_current_user_id() );
			$name = $user->first_name . ' ' . $user->last_name;
			$whom = $name . ' (' . $user->user_login. ')';
		} else {
			$whom = self::get_user_ip();
		}

		$wp_upload_dir = wp_upload_dir();
		$img_src = str_replace( $wp_upload_dir['basedir'], $wp_upload_dir['baseurl'], $uploaded_file );

		do_action( 'si_new_record',
			'<img src="'.$img_src.'" />',
			self::RECORD,
			$doc_id,
			sprintf( __( 'Signature from %s.', 'sprout-invoices' ), $whom ),
			0,
		false );

		wp_send_json_success( array( 'img' => '<img src="'.$img_src.'" />' ) );
	}

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
		remove_filter( 'upload_dir', array( __CLASS__, 'set_upload_dir' ) );
		return apply_filters( 'si_sign_get_upload_path', $path );
	}

	public static function get_file_name( $doc_id = 0 ) {
		$doc_type = self::doc_type( $doc_id );

		$file_name = '';
		// Invoicing
		if ( 'invoice' === $doc_type ) {
			$file_name = apply_filters( 'si_esignature_invoice_file_name', 'invoice_' . $doc_id . '.png' );
		}

		// Estimates
		if ( 'estimate' === $doc_type ) {
			$file_name = apply_filters( 'si_esignature_estimate_file_name', 'estimate_' . $doc_id . '.png' );
		}

		return $file_name;
	}

	public static function doc_type( $doc_id = 0 ) {
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

	public static function get_upload_dir() {
		$wp_upload_dir = wp_upload_dir();
		wp_mkdir_p( $wp_upload_dir['basedir'] . '/' . self::DIRECTORY_PREFIX );
		$path = $wp_upload_dir['basedir'] . '/' . self::DIRECTORY_PREFIX;

		return apply_filters( 'si_sign_get_upload_dir', $path );
	}

	public static function create_protection_files( $force = false, $method = false ) {
		$transient_key = 'si_sign_check_protection_files';
		if ( false === get_transient( $transient_key ) || $force ) {

			$upload_path = self::get_upload_dir();

			// Make sure the folder is created
			wp_mkdir_p( $upload_path );

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
		return SA_ADDON_SI_ESIGNATURE_PATH . '/views/';
	}
}
