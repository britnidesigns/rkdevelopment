<?php

/**
 * SI_HTML_Notifications Controller
 *
 * @package Sprout_Invoice
 * @subpackage SI_HTML_Notifications
 */
class SI_HTML_Notifications extends SI_Controller {

	public static function init() {
		// Register Settings
		add_filter( 'si_notification_settings', array( __CLASS__, 'register_settings' ) );

		add_action( 'wp_ajax_si_load_html_templates', array( __CLASS__, 'maybe_load_html_templates' ) );

		// filter some shortcodes
		add_filter( 'shortcode_line_item_table', array( __CLASS__, 'item_list_table' ), 10, 3 );

	}

	////////////
	// admin //
	////////////

	/**
	 * Hooked on init add the settings page and options.
	 *
	 */
	public static function register_settings( $settings = array() ) {
		// Settings
		$settings['html_notifications'] = array(
			'title' => __( 'Load HTML Templates', 'sprout-invoices' ),
			'description' => __( 'Load all the default HTML notifications from the add-on.', 'sprout-invoices' ),
			'weight' => 30.15,
			'tab' => 'notifications',
			'settings' => array(
				'load_html_templates' => array(
					'option' => array(
						'type' => 'bypass',
						'output' => self::load_html_templates(),
						'description' => __( 'Loading the templates will delete the content of your existing notifications with pretty HTML templates.', 'sprout-invoices' ),
						),
					),
				),
			);
		return $settings;
	}

	public static function load_html_templates() {
		ob_start();
		?>
			<label for="load_html_templates" class="si_input_label"><?php _e( 'HTML Notifications', 'sprout-invoices' ) ?></label>
			<span class="button" id="load_html_templates" @click='loadHTMLTemplates'><?php _e( 'Load HTML Templates', 'sprout-invoices' ) ?></span>
			<img
				v-if='isLoading == true'
				id='load_html_templates_loading_indicator' src='<?php echo get_site_url() ?>/wp-admin/images/wpspin_light-2x.gif' alt='Loading indicator' />
		<?php
		return ob_get_clean();
	}

	public static function maybe_load_html_templates() {
		if ( ! current_user_can( 'manage_sprout_invoices_options' ) ) {
			return;
		}
		self::load_templates();
		die( '1' );
	}

	public static function load_templates() {
		$notifications = apply_filters( 'sprout_notifications', array() );
		foreach ( $notifications as $notification_id => $data ) {
			// Find existing notification
			$notification = SI_Notifications_Control::get_notification_instance( $notification_id );

			// Delete all the existing notifications
			if ( is_a( $notification, 'SI_Notification' ) ) {
				wp_delete_post( $notification->get_id(), true );
			}

			// Create new notification with new content
			$post_id = wp_insert_post( array(
				'post_status' => 'publish',
				'post_type' => SI_Notification::POST_TYPE,
				'post_title' => $data['default_title'],
				'post_content' => self::notification_content( $notification_id, $data ),
			) );
			$notification = SI_Notification::get_instance( $post_id );
			SI_Notifications_Control::save_meta_box_notification_submit( $post_id, $notification->get_post(), array(), $notification_id );

			// Don't allow for a notification to enabled if specifically shouldn't
			if ( isset( $data['always_disabled'] ) && $data['always_disabled'] ) {
				$notification->set_disabled( 'TRUE' );
			}

			update_option( SI_Notifications_Control::EMAIL_FORMAT, 'HTML' );
		}
	}

	public static function notification_content( $notification_id, $data ) {
		$content = '';
		if ( file_exists( self::addons_view_path() . 'notifications/'.$notification_id.'.php' ) ) {
			$content = self::load_addon_view_to_string( 'notifications/'.$notification_id.'.php', array() );
		} elseif ( isset( $data['default_content'] ) ) {
			$content = $data['default_content'];
		} else {
			return __( "I'm so sorry, I couldn't find a template", 'sprout-invoices' );
		}

		$address = SI_Admin_Settings::get_site_address();
		$content = str_replace(
			array(
					'Acme Inc.',
					'123 Mockingbird, Ventura 93004',
					'support@acme.inc',
					),
			array(
					si_get_company_name(),
					( isset( $address['street'] ) ) ? $address['street'] . ', '. $address['city'] . ' '. $address['postal_code'] : '',
					si_get_company_email(),
					), $content
		);
		return $content;
	}

	/////////////////
	// Shortcodes //
	/////////////////

	public static function item_list_table( $original_table, $line_items, $data ) {
			$args = array(
				'doc_id' => 0,
				'subtotal' => 0,
				'tax' => 0,
				'due' => 0,
				'subtotal' => 0,
				'total' => 0,
				'totals' => array(),
				'line_items' => $line_items,
				);
			if ( isset( $data['invoice'] ) && is_a( $data['invoice'], 'SI_Invoice' ) ) {
				$args['tax'] = $data['invoice']->get_tax_total() + $data['invoice']->get_tax_total();
				if ( (float) $data['invoice']->get_subtotal() !== (float) $data['invoice']->get_calculated_total() ) {
					$args['subtotal'] = $data['invoice']->get_subtotal();
				}
				if ( $data['invoice']->get_balance() != $data['invoice']->get_calculated_total() ) {
					$args['due'] = $data['invoice']->get_balance();
				}
				$args['total'] = $data['invoice']->get_calculated_total();
				$args['doc_id'] = $data['invoice']->get_id();
				$args['totals'] = SI_Line_Items::line_item_totals( $data['invoice']->get_id() );
			} elseif ( isset( $data['estimate'] ) && is_a( $data['estimate'], 'SI_Estimate' ) ) {
				$args['subtotal'] = $data['estimate']->get_subtotal();
				$args['tax'] = $data['estimate']->get_tax_total() + $data['estimate']->get_tax_total();
				$args['total'] = $data['estimate']->get_calculated_total();
				$args['doc_id'] = $data['estimate']->get_id();
				$args['totals'] = SI_Line_Items::line_item_totals( $data['estimate']->get_id() );
			}
			$table = self::load_addon_view_to_string( 'notifications/shortcodes/line_item_table.php', $args );
			return $table;
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
		return SA_ADDON_HTML_NOTIFICATIONS_PATH . '/views/';
	}
}
