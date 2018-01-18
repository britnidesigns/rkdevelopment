<?php

/**
 * @package Sprout_Invoices
 * @subpackage Attachment_Downloads
 */
class SI_Attachment_Downloads extends SI_Controller {

	private static $meta_keys = array(
		'attachments' => '_media_attachments', // array
		'is_private_download' => '_private_media_attachments', // bool
	);

	public static function init() {

		if ( is_admin() ) {
			// Meta boxes
			add_action( 'admin_init', array( __CLASS__, 'register_meta_boxes' ) );

			// Enqueue
			add_action( 'admin_enqueue_scripts', array( __CLASS__, 'register_resources' ) );
			add_action( 'admin_enqueue_scripts', array( __CLASS__, 'admin_enqueue' ), 20 );
		}

		add_action( 'si_document_details', array( __CLASS__, 'add_additional_info_to_doc' ), 100 );

		add_action( 'si_document_more_details', array( __CLASS__, 'add_additional_info_to_doc' ), 100 );

		add_action( 'si_head', array( __CLASS__, 'add_css' ), 5 );

	}


	public static function add_additional_info_to_doc() {
		$doc_id = get_the_id();
		$attachments = self::get_attachments( $doc_id );
		$is_private_download = self::is_private_download( $doc_id );
		if ( ! empty( $attachments ) ) {
			$private_extension = ( $is_private_download ) ? '-no-links' : '' ;
			$private_extension .= ( method_exists( 'SI_PDF', 'want_create_pdf' ) && SI_PDF::want_create_pdf() ) ? '-pdf' : '' ;
			self::load_addon_view( 'attachments-info/thumbnails' . $private_extension, array(
				'id' => $doc_id,
				'attachments' => $attachments,
			), true );

		}
	}

	public static function add_css() {
		echo '<link rel="stylesheet" id="si_doc_attachments" href="' . SA_ADDON_ATTACHMENT_DOWNLOADS_URL . '/resources/front-end/doc-attachments.css" type="text/css" media="all">';
	}

	/////////////////
	// Meta boxes //
	/////////////////

	/**
	 * Regsiter meta boxes for estimate editing.
	 *
	 * @return
	 */
	public static function register_meta_boxes() {
		// invoice specific
		$args = array(
			'si_attachments' => array(
				'title' => __( 'Attachments', 'sprout-invoices' ),
				'show_callback' => array( __CLASS__, 'show_attachments_meta_box' ),
				'save_callback' => array( __CLASS__, 'save_attachments_meta_box' ),
				'context' => 'normal',
				'priority' => 'low',
				'weight' => 0,
				'save_priority' => 0,
			),
		);
		do_action( 'sprout_meta_box', $args, SI_Invoice::POST_TYPE );
		do_action( 'sprout_meta_box', $args, SI_Estimate::POST_TYPE );
	}


	public static function show_attachments_meta_box( $post, $metabox ) {
		$attachments = self::get_attachments( $post->ID );
		$is_checked = self::is_private_download( $post->ID );

		$option_message = __( 'Do not link to these files until the invoice is paid in full.', 'sprout-invoices' );
		if ( SI_Estimate::POST_TYPE === $post->post_type ) {
			$option_message = __( 'Do not link to these files until the estimate is accepted.', 'sprout-invoices' );
		}
		?>
			<style type="text/css">
				#doc_attachment_thumbnails a {
					margin-right: 30px;
					margin-left: 20px;
				}
				#doc_attachments label {
					display: inline-block;
					margin-left: 20px;
				}
			</style>
			<p id="doc_attachments">
				<input type="button" id="add-doc-atachments" class="button" value="<?php _e( 'Choose or Upload a File', 'sprout-invoices' )?>" />
				<label for="private_attachments_checkbox"><input type="checkbox" name="private_attachments" value="1" id="private_attachments_checkbox" <?php checked( true, $is_checked ) ?> />&nbsp;<?php echo $option_message ?></label>
			</p>
			<p id="doc_attachment_thumbnails">
				<?php foreach ( $attachments as $media_id ) : ?>

					<?php
						$file = basename( get_attached_file( $media_id ) );
						$filetype = wp_check_filetype( $file );
						$thumb_url = wp_get_attachment_thumb_url( $media_id );
						?>
					<span data-id="<?php echo esc_attr( $media_id ) ?>" class="remove_media_item del_button">X</span>&nbsp;<a href="<?php echo get_edit_post_link( $media_id ) ?>" target="_blank" class="<?php echo esc_attr( $filetype['ext'] ) ?>" data-id="<?php echo esc_attr( $media_id ) ?>"><?php echo esc_html( $file ) ?></a>
					<input type="hidden" name="doc_media[]" value="<?php echo esc_attr( $media_id ) ?>">
				<?php endforeach ?>
			</p>
		<?php
	}

	public static function save_attachments_meta_box( $post_id ) {
		self::clear_attachments( $post_id );
		if ( isset( $_POST['doc_media'] ) ) {
			self::set_attachments( $post_id, $_POST['doc_media'] );
		}

		self::set_as_not_private_download( $post_id );
		if ( isset( $_POST['private_attachments'] ) ) {
			self::set_private_download( $post_id );
		}
	}


	//////////////
	// Enqueue //
	//////////////

	public static function register_resources() {
		// admin js
		wp_register_script( 'si_doc_attachments', SA_ADDON_ATTACHMENT_DOWNLOADS_URL . '/resources/admin/doc-attachments.js', array( 'jquery' ), self::SI_VERSION );
	}

	public static function admin_enqueue() {
		wp_enqueue_media();

		wp_localize_script( 'si_doc_attachments', 'doc_upload',
			array(
				'title' => __( 'Choose or Upload a File', 'sprout-invoices' ),
				'button' => __( 'Attach File', 'sprout-invoices' ),
				)
		);
		wp_enqueue_script( 'si_doc_attachments' );
	}

	///////////
	// Meta //
	///////////


	/**
	 * Get the associated attachments with this doc
	 * @return array
	 */
	public static function get_attachments( $doc_id = 0 ) {
		$doc = si_get_doc_object( $doc_id );
		$attachments = $doc->get_post_meta( self::$meta_keys['attachments'], true );
		if ( ! is_array( $attachments ) ) {
			$attachments = array();
		}
		return array_filter( $attachments );
	}

	/**
	 * Save the associated attachments with this doc
	 * @param array $attachments
	 */
	public static function set_attachments( $doc_id = 0, $attachments = array() ) {
		$doc = si_get_doc_object( $doc_id );
		$doc->save_post_meta( array(
			self::$meta_keys['attachments'] => $attachments,
		) );
		return $attachments;
	}

	/**
	 * Clear out the associated attachments
	 * @param array $attachments
	 */
	public static function clear_attachments( $doc_id = 0 ) {
		$doc = si_get_doc_object( $doc_id );
		$doc->delete_post_meta( array(
			self::$meta_keys['attachments'] => '',
		) );
	}


	/**
	 * Is Recurring
	 */
	public static function is_private_download( $doc_id = 0 ) {
		$doc = si_get_doc_object( $doc_id );
		if ( ! is_object( $doc ) ) {
			return 0;
		}
		if ( is_a( $doc, 'SI_Invoice' ) ) {
			// paid in full
			if ( ! si_get_invoice_balance( $doc_id ) ) {
				return false;
			}
		}
		if ( is_a( $doc, 'SI_Estimate' ) ) {
			// paid in full
			if ( si_is_estimate_approved( $doc_id ) ) {
				return false;
			}
		}
		$bool = (bool) $doc->get_post_meta( self::$meta_keys['is_private_download'] );
		if ( ! $bool ) {
			$bool = false;
		}
		return $bool;
	}

	public static function set_private_download( $doc_id = 0 ) {
		$doc = si_get_doc_object( $doc_id );
		if ( ! is_object( $doc ) ) {
			return 0;
		}
		$doc->save_post_meta( array(
			self::$meta_keys['is_private_download'] => 1,
		) );
		return 1;
	}

	public static function set_as_not_private_download( $doc_id = 0 ) {
		$doc = si_get_doc_object( $doc_id );
		if ( ! is_object( $doc ) ) {
			return 0;
		}
		$doc->save_post_meta( array(
			self::$meta_keys['is_private_download'] => 0,
		) );
		return 1;
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
		return SA_ADDON_ATTACHMENT_DOWNLOADS_PATH . '/views/';
	}

	public static function get_attachment_icon( $media_id = 0 ) {
		$file = basename( get_attached_file( $media_id ) );
		$filetype = wp_check_filetype( $file );
		switch ( $filetype['ext'] ) {
			case 'pdf':
				$img = SA_ADDON_ATTACHMENT_DOWNLOADS_URL . '/resources/icons/pdf.png';
				break;
			case 'zip':
				$img = SA_ADDON_ATTACHMENT_DOWNLOADS_URL . '/resources/icons/zip.png';
				break;
			case 'rar':
				$img = SA_ADDON_ATTACHMENT_DOWNLOADS_URL . '/resources/icons/rar.png';
				break;
			case 'mp3':
			case 'swa':
			case 'wav':
			case 'wma':
			case 'm4a':
				$img = SA_ADDON_ATTACHMENT_DOWNLOADS_URL . '/resources/icons/audio.png';
				break;
			case 'm1a':
			case 'm1s':
			case 'm1v':
			case 'm15':
			case 'm75':
			case 'mp2':
			case 'mpa':
			case 'mpeg':
			case 'mpg':
			case 'mov':
			case 'mpm':
			case 'mpv':
			case 'm4v':
			case 'mp4':
			case 'mpg4':
			case 'wmv':
				$img = SA_ADDON_ATTACHMENT_DOWNLOADS_URL . '/resources/icons/video.png';
				break;
			case 'csv':
			case 'xls':
				$img = SA_ADDON_ATTACHMENT_DOWNLOADS_URL . '/resources/icons/csv.png';
				break;
			case 'ppt':
				$img = SA_ADDON_ATTACHMENT_DOWNLOADS_URL . '/resources/icons/csv.png';
				break;
			case 'bmp':
			case 'gif':
			case 'jpeg':
			case 'jpg':
			case 'png':
			case 'tiff':
				$img = SA_ADDON_ATTACHMENT_DOWNLOADS_URL . '/resources/icons/image.png';
				break;

			default:
				$img = SA_ADDON_ATTACHMENT_DOWNLOADS_URL . '/resources/icons/default.png';
				break;
		}

		return $img;

	}
}
