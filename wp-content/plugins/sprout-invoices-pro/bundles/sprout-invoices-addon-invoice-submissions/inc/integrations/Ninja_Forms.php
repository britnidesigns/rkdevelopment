<?php

class SI_IS_Ninja_Forms extends SI_Invoice_Submissions {
	const NINJA_FORM_ID = 'si_ninja_invoice_submissions_id';
	// Integration options
	protected static $ninja_form_id;

	public static function init() {
		// Store options
		self::$ninja_form_id = get_option( self::NINJA_FORM_ID, 0 );

		// filter options
		self::register_settings();

		if ( self::$ninja_form_id && function_exists( 'ninja_forms_register_field' ) ) {
			// 2.x
			add_action( 'ninja_forms_post_process', array( __CLASS__, 'maybe_process_ninjaforms_form' ), 10, 2 );
			// 3.x
			add_action( 'ninja_forms_after_submission', array( __CLASS__, 'maybe_process_ninja3forms_form' ), 10 );
			add_action( 'init', array( __CLASS__, 'ninja_custom_field' ) );
		}
	}

	///////////////
	// Settings //
	///////////////

	public static function register_settings() {

		$ninja_options = array( 0 => __( 'No forms found', 'sprout-invoices' ) );
		if ( function_exists( 'ninja_forms_get_all_forms' ) ) {
			$all_ninja_forms = ninja_forms_get_all_forms();
			if ( ! empty( $all_ninja_forms ) ) {
				$ninja_options = array();
				foreach ( $all_ninja_forms as $form ) {
					if ( isset( $form['data']['form_title'] ) ) {
						$ninja_options[ $form['id'] ] = $form['data']['form_title'];
					} else {
						$ninja_options[ $form['id'] ] = $form['data']['title'];
					}
				}
			}
		}

		$settings = array(
			self::NINJA_FORM_ID => array(
				'label' => __( 'Ninja Forms ID', 'sprout-invoices' ),
				'option' => array(
					'type' => 'select',
					'options' => $ninja_options,
					'default' => self::$ninja_form_id,
					'description' => sprintf( __( 'Select the submission form built with <a href="%s">Ninja Forms</a>.', 'sprout-invoices' ), 'https://sproutapps.co/link/ninja-forms' ),
				),
			),
			self::FORM_ID_MAPPING => array(
				'label' => __( 'Ninja Form ID Mapping', 'sprout-invoices' ),
				'option' => array( __CLASS__, 'show_ninja_form_field_mapping' ),
				'sanitize_callback' => array( __CLASS__, 'save_ninja_form_field_mapping' ),
			),
		);

		$all_settings = array(
			'si_invoice_submission_settings' => array(
				'title' => __( 'Invoice Submission', 'sprout-invoices' ),
				'weight' => 6,
				'tab' => 'settings',
				'settings' => $settings,
			),
		);

		do_action( 'sprout_settings', $all_settings );
	}

	public static function show_ninja_form_field_mapping() {
		return self::show_form_field_mapping( self::mapping_options() );
	}

	public static function save_ninja_form_field_mapping( $mappings = array() ) {
		return self::save_form_field_mapping( self::mapping_options() );
	}

	public static function mapping_options() {
		$options = array(
				'subject' => __( 'Subject/Title', 'sprout-invoices' ),
				'line_item_list' => __( 'Invoice Items', 'sprout-invoices' ),
				'email' => __( 'Email', 'sprout-invoices' ),
				'client_name' => __( 'Client/Company Name', 'sprout-invoices' ),
				'first_name' => __( 'First Name', 'sprout-invoices' ),
				'last_name' => __( 'Last Name', 'sprout-invoices' ),
				//'full_name' => __( 'Full Name', 'sprout-invoices' ),
				//'contact_address' => __( 'Address Fields', 'sprout-invoices' ),
				'contact_street' => __( 'Street Address', 'sprout-invoices' ),
				'contact_city' => __( 'City', 'sprout-invoices' ),
				'contact_zone' => __( 'State/Province', 'sprout-invoices' ),
				'contact_postal_code' => __( 'Zip/Postal', 'sprout-invoices' ),
				'contact_country' => __( 'Country', 'sprout-invoices' ),
			);
		return $options;
	}

	public static function ninja_custom_field() {
		$args = array(
			'name' => __( 'Invoice Items', 'sprout-invoices' ),
			'edit_options' => array(),
			'display_function' => array( __CLASS__, 'ninja_forms_invoice_items_display' ),
			'group' => '',
			'edit_label' => true,
			'edit_label_pos' => true,
			'edit_req' => true,
			'edit_custom_class' => true,
			'edit_help' => true,
			'edit_meta' => false,
			'sidebar' => 'template_fields',
			'edit_conditional' => true,
			'conditional' => array(
				'action' => array(
					'show' => array(
						'name'        => __( 'Show This', 'ninja-forms' ),
						'js_function' => 'show',
						'output'      => 'hide',
					),
					'hide' => array(
						'name'        => __( 'Hide This', 'ninja-forms' ),
						'js_function' => 'hide',
						'output'      => 'hide',
					),
				),
				'value' => array(
					'type' => 'list',
				),
			),
		);

		if ( function_exists( 'ninja_forms_register_field' ) ) {
			ninja_forms_register_field( '_invoice_items', $args );
		}
	}

	public static function ninja_forms_invoice_items_display( $field_id, $data, $form_id = '' ) {
		/**
		 * Only a specific form do this process
		 */
		if ( $form_id != self::$ninja_form_id ) {
			return;
		}

		$items_and_products = Predefined_Items::get_items_and_products();
		$item_groups = apply_filters( 'si_predefined_items_for_submission', $items_and_products );
		$list_options_span_class = apply_filters( 'ninja_forms_display_list_options_span_class', '', $field_id );
		$field_class = ninja_forms_get_field_class( $field_id, $form_id );

		?>
			<input type="hidden" id="ninja_forms_field_<?php echo $field_id;?>" name="ninja_forms_field_<?php echo $field_id;?>" value="">

			<span id="ninja_forms_field_<?php echo $field_id;?>_options_span" class="<?php echo $list_options_span_class;?>" rel="<?php echo $field_id;?>">
				<?php
				foreach ( $item_groups as $type => $items ) {
					$x = 0;
					foreach ( $items as $key ) {
						$value = $key['id'];
						$label = sprintf( '&nbsp;&nbsp;<b>%s</b><br/><small>%s</small>', $key['title'], $key['content'] );
						$option = sprintf( '<label id="ninja_forms_field_%1$s_%2$s_label" class="ninja-forms-field-%1$s-options"><input id="ninja_forms_field_%1$s_%2$s" name="ninja_forms_field_%1$s[]" type="checkbox" class="%5$s ninja_forms_field_%1$s" value="%3$s" rel="%1$s"/>%4$s</label>', $field_id, $x, $value, $label, $list_options_span_class );
						print apply_filters( 'si_predefined_option_field', $option, $field_id, $x, $value, $label, $list_options_span_class );
						$x++;
					}
				} ?>
			</span>

		<?php
	}

	public static function maybe_redirect_after_submission( $invoice_id ) {
		if ( apply_filters( 'si_invoice_submission_redirect_to_invoice', true ) ) {
			if ( get_post_type( $invoice_id ) == SI_Invoice::POST_TYPE ) {
				$url = get_permalink( $invoice_id );
				wp_redirect( $url );
				die();
			}
		}
	}

	////////////////////
	// Process forms //
	////////////////////

	public static function maybe_process_ninjaforms_form() {
		global $ninja_forms_processing;
		// Only a specific form do this process
		if ( $ninja_forms_processing->data['form_ID'] != self::$ninja_form_id ) {
			return;
		}
		$entry = $ninja_forms_processing->get_all_fields();
		$entry_id = $ninja_forms_processing->data['form']['sub_id'];

		/**
		 * Set variables
		 * @var string
		 */
		$subject = isset( $entry[ self::get_form_map_id( 'subject' ) ] ) ? $entry[ self::get_form_map_id( 'subject' ) ] : '';
		$email = isset( $entry[ self::get_form_map_id( 'email' ) ] ) ? $entry[ self::get_form_map_id( 'email' ) ] : '';
		$client_name = isset( $entry[ self::get_form_map_id( 'client_name' ) ] ) ? $entry[ self::get_form_map_id( 'client_name' ) ] : '';
		$full_name = isset( $entry[ self::get_form_map_id( 'first_name' ) ] ) ? $entry[ self::get_form_map_id( 'first_name' ) ] . ' ' . $entry[ self::get_form_map_id( 'last_name' ) ] : '';
		$website = isset( $entry[ self::get_form_map_id( 'website' ) ] ) ? $entry[ self::get_form_map_id( 'website' ) ] : '';
		$contact_street = isset( $entry[ self::get_form_map_id( 'contact_street' ) ] ) ? $entry[ self::get_form_map_id( 'contact_street' ) ] : '';
		$contact_city = isset( $entry[ self::get_form_map_id( 'contact_city' ) ] ) ? $entry[ self::get_form_map_id( 'contact_city' ) ] : '';
		$contact_zone = isset( $entry[ self::get_form_map_id( 'contact_zone' ) ] ) ? $entry[ self::get_form_map_id( 'contact_zone' ) ] : '';
		$contact_postal_code = isset( $entry[ self::get_form_map_id( 'contact_postal_code' ) ] ) ? $entry[ self::get_form_map_id( 'contact_postal_code' ) ] : '';
		$contact_country = isset( $entry[ self::get_form_map_id( 'contact_country' ) ] ) ? $entry[ self::get_form_map_id( 'contact_country' ) ] : '';

		/**
		 * Build line item array
		 * @var array
		 */
		$line_item_list = array();
		if ( ! empty( $entry[ self::get_form_map_id( 'line_item_list' ) ] ) ) {
			$line_item_list = $entry[ self::get_form_map_id( 'line_item_list' ) ];
		}

		/**
		 * Create invoice
		 * @var array
		 */
		$invoice_args = array(
			'status' => SI_Invoice::STATUS_PENDING,
			'subject' => $subject,
			'line_item_list' => $line_item_list,
			'fields' => $entry,
			'form' => $entry,
			'history_link' => sprintf( '<a href="%s">#%s</a>', add_query_arg( array( 'post' => $entry_id ), admin_url( 'post.php?action=edit' ) ), $entry_id ),
		);
		$invoice = self::maybe_create_invoice( $invoice_args, $entry_id );

		/**
		 * Make sure an invoice was created, if so create a client
		 */
		if ( is_a( $invoice, 'SI_Invoice' ) ) {
			$client_args = array(
				'email' => $email,
				'client_name' => $client_name,
				'full_name' => $full_name,
				'website' => $website,
				'contact_street' => $contact_street,
				'contact_city' => $contact_city,
				'contact_zone' => $contact_zone,
				'contact_postal_code' => $contact_postal_code,
				'contact_country' => $contact_country,
			);
			$client_args = apply_filters( 'si_invoice_submission_maybe_process_ninja_form_client_args', $client_args, $entry, $ninja_forms_processing );
			self::maybe_create_client( $invoice, $client_args );

			do_action( 'si_invoice_submission_complete', $invoice->get_id() );
			self::maybe_redirect_after_submission( $invoice->get_id() );
		}
	}

	public static function maybe_process_ninja3forms_form( $form_data ) {
		// Only a specific form do this process
		if ( (int) $form_data['form_id'] !== (int) self::$ninja_form_id ) {
			return;
		}

		$entry = $form_data['fields'];
		$entry_id = $form_data['actions']['save']['sub_id'];

		/**
		 * Set variables
		 * @var string
		 */
		$subject = isset( $entry[ self::get_form_map_id( 'subject' ) ] ) ? $entry[ self::get_form_map_id( 'subject' ) ]['value'] : '';
		$email = isset( $entry[ self::get_form_map_id( 'email' ) ] ) ? $entry[ self::get_form_map_id( 'email' ) ]['value'] : '';
		$client_name = isset( $entry[ self::get_form_map_id( 'client_name' ) ] ) ? $entry[ self::get_form_map_id( 'client_name' ) ]['value'] : '';
		$full_name = isset( $entry[ self::get_form_map_id( 'first_name' ) ] ) ? $entry[ self::get_form_map_id( 'first_name' ) ]['value'] . ' ' . $entry[ self::get_form_map_id( 'last_name' ) ]['value'] : '';
		$website = isset( $entry[ self::get_form_map_id( 'website' ) ] ) ? $entry[ self::get_form_map_id( 'website' ) ]['value'] : '';
		$contact_street = isset( $entry[ self::get_form_map_id( 'contact_street' ) ] ) ? $entry[ self::get_form_map_id( 'contact_street' ) ]['value'] : '';
		$contact_city = isset( $entry[ self::get_form_map_id( 'contact_city' ) ] ) ? $entry[ self::get_form_map_id( 'contact_city' ) ]['value'] : '';
		$contact_zone = isset( $entry[ self::get_form_map_id( 'contact_zone' ) ] ) ? $entry[ self::get_form_map_id( 'contact_zone' ) ]['value'] : '';
		$contact_postal_code = isset( $entry[ self::get_form_map_id( 'contact_postal_code' ) ] ) ? $entry[ self::get_form_map_id( 'contact_postal_code' ) ]['value'] : '';
		$contact_country = isset( $entry[ self::get_form_map_id( 'contact_country' ) ] ) ? $entry[ self::get_form_map_id( 'contact_country' ) ]['value'] : '';

		/**
		 * Build line item array
		 * @var array
		 */
		$line_item_list = array();
		if ( ! empty( $entry[ self::get_form_map_id( 'line_item_list' ) ] ) ) {
			$line_item_list = $entry[ self::get_form_map_id( 'line_item_list' ) ]['value'];
		}

		/**
		 * Create invoice
		 * @var array
		 */
		$invoice_args = array(
			'status' => SI_Invoice::STATUS_PENDING,
			'subject' => $subject,
			'line_item_list' => $line_item_list,
			'fields' => $entry,
			'form' => $entry,
			'history_link' => sprintf( '<a href="%s">#%s</a>', add_query_arg( array( 'post' => $entry_id ), admin_url( 'post.php?action=edit' ) ), $entry_id ),
		);
		$invoice = self::maybe_create_invoice( $invoice_args, $entry_id );

		/**
		 * Make sure an invoice was created, if so create a client
		 */
		if ( is_a( $invoice, 'SI_Invoice' ) ) {
			$client_args = array(
				'email' => $email,
				'client_name' => $client_name,
				'full_name' => $full_name,
				'website' => $website,
				'contact_street' => $contact_street,
				'contact_city' => $contact_city,
				'contact_zone' => $contact_zone,
				'contact_postal_code' => $contact_postal_code,
				'contact_country' => $contact_country,
			);
			$client_args = apply_filters( 'si_invoice_submission_maybe_process_ninja_form_client_args', $client_args, $entry, $ninja_forms_processing );
			self::maybe_create_client( $invoice, $client_args );

			do_action( 'si_invoice_submission_complete', $invoice->get_id() );
			self::maybe_redirect_after_submission( $invoice->get_id() );
		}
	}
}
SI_IS_Ninja_Forms::init();
