<?php

class SI_IS_Gravity_Forms extends SI_Invoice_Submissions {
	const GRAVITY_FORM_ID = 'si_gravity_invoice_submissions_id';
	// Integration options
	protected static $gravity_form_id;

	public static function init() {
		// Store options
		self::$gravity_form_id = get_option( self::GRAVITY_FORM_ID, 0 );

		// filter options
		self::register_settings();

		if ( self::$gravity_form_id ) {
			// Create invoice before confirmation
			add_action( 'gform_entry_created', array( __CLASS__, 'maybe_process_gravity_form' ), 10, 2 );
			// Redirect confirmation
			add_filter( 'gform_confirmation_' . self::$gravity_form_id, array( __CLASS__, 'maybe_redirect_after_submission' ), 10, 4 );
			// Add pre-defined items
			add_filter( 'gform_pre_render_' . self::$gravity_form_id, array( __CLASS__, 'populate_gf_choice_fields' ), 9 );
			add_filter( 'gform_pre_submission_filter_' . self::$gravity_form_id, array( __CLASS__, 'populate_gf_choice_fields' ), 9 );
			add_filter( 'gform_pre_validation', array( __CLASS__, 'populate_gf_choice_fields' ), 9 );
		}
	}

	///////////////
	// Settings //
	///////////////

	public static function register_settings() {

		$gravity_options = array( 0 => __( 'No forms found', 'sprout-invoices' ) );
		$all_grav_forms = RGFormsModel::get_forms( 1, 'title' );
		if ( ! empty( $all_grav_forms ) ) {
			$gravity_options = array();
			foreach ( $all_grav_forms as $form ){
				$gravity_options[ absint( $form->id ) ] = esc_html( $form->title );
			}
		}

		$settings = array(
			self::GRAVITY_FORM_ID => array(
				'label' => __( 'GravityForms ID', 'sprout-invoices' ),
				'option' => array(
					'type' => 'select',
					'options' => $gravity_options,
					'default' => self::$gravity_form_id,
					'description' => sprintf( __( 'Select the submission form built with <a href="%s">Gravity Forms</a>.', 'sprout-invoices' ), 'https://sproutapps.co/link/gravity-forms' )
				)
			),
			self::FORM_ID_MAPPING => array(
				'label' => __( 'Gravity Form ID Mapping', 'sprout-invoices' ),
				'option' => array( __CLASS__, 'show_gravity_form_field_mapping' ),
				'sanitize_callback' => array( __CLASS__, 'save_gravity_form_field_mapping' )
			),
		);

		$all_settings = array(
			'si_invoice_submission_settings' => array(
				'title' => __( 'Invoice Submission', 'sprout-invoices' ),
				'weight' => 6,
				'tab' => 'settings',
				'settings' => $settings
			)
		);

		do_action( 'sprout_settings', $all_settings );
	}

	public static function show_gravity_form_field_mapping() {
		return self::show_form_field_mapping( self::mapping_options() );
	}

	public static function save_gravity_form_field_mapping( $mappings = array() ) {
		return self::save_form_field_mapping( self::mapping_options() );
	}

	public static function mapping_options() {
		$options = array(
				'subject' => __( 'Subject/Title', 'sprout-invoices' ),
				'line_item_list' => __( 'Pre-defined Item Selection (Checkboxes Field)', 'sprout-invoices'  ),
				'email' => __( 'Email', 'sprout-invoices' ),
				'client_name' => __( 'Client/Company Name', 'sprout-invoices' ),
				//'first_name' => __( 'First Name', 'sprout-invoices' ),
				//'last_name' => __( 'Last Name', 'sprout-invoices' ),
				'full_name' => __( 'Full Name', 'sprout-invoices' ),
				'contact_address' => __( 'Address Fields', 'sprout-invoices' ),
				//'contact_street' => __( 'Street Address', 'sprout-invoices' ),
				//'contact_city' => __( 'City', 'sprout-invoices' ),
				//'contact_zone' => __( 'State/Province', 'sprout-invoices' ),
				//'contact_postal_code' => __( 'Zip/Postal', 'sprout-invoices' ),
				//'contact_country' => __( 'Country', 'sprout-invoices' ),
			);
		return $options;
	}

	//////////////////////////////
	// Populate Front-end Form //
	//////////////////////////////

	public static function populate_gf_choice_fields( $form ) {
		/**
		 * Only a specific form do this process
		 */
		if ( $form['id'] != self::$gravity_form_id ) {
			return $form;
		}
		foreach ( $form['fields'] as $key => $data ) {
			if ( $data['id'] == self::get_form_map_id( 'line_item_list' ) ) {
				$form['fields'][$key]['choices'] = self::line_item_choices();
				$form['fields'][$key]['inputs'] = self::line_item_choices();
			}
		}
		return $form;

	}

	public static function maybe_redirect_after_submission( $confirmation, $form, $lead, $ajax ) {
		if ( apply_filters( 'si_invoice_submission_redirect_to_invoice', true ) ) {
			$invoice_id = self::get_invoice_id_from_entry_id( $lead['id'] );
			if ( get_post_type( $invoice_id ) == SI_Invoice::POST_TYPE ) {
				$url = get_permalink( $invoice_id );
				if ( headers_sent() || $ajax ) {
					$confirmation = GFFormDisplay::get_js_redirect_confirmation( $url, $ajax );
				} else {
					$confirmation = array( 'redirect' => $url );
				}
			}
		}
		return $confirmation;
	}

	public static function line_item_choices() {
		$choices = array();
		$items_and_products = Predefined_Items::get_items_and_products();
		$item_groups = apply_filters( 'si_predefined_items_for_submission', $items_and_products );
		foreach ( $item_groups as $type => $items ) {
			$index = 0;
			foreach ( $items as $key ) {
				$index++;
				$choices[] = array(
						'id' => self::get_form_map_id( 'line_item_list' ) . '.'.$index,
						'label' => $key['title'],
						'text' => sprintf( '<b>%s</b><br/><small>%s</small>', $key['title'], $key['content'] ),
						'value' => $key['id'],
						'price' => $key['rate'],
						'name' => '',
					);
			}
		}
		return $choices;
	}

	////////////////////
	// Process forms //
	////////////////////

	public static function maybe_process_gravity_form( $entry, $form ) {
		/**
		 * Only a specific form do this process
		 */
		if ( $entry['form_id'] != self::$gravity_form_id ) {
			return;
		}

		/**
		 * Set variables
		 * @var string
		 */
		$subject = isset( $entry[ self::get_form_map_id( 'subject' ) ] ) ? $entry[ self::get_form_map_id( 'subject' ) ] : '';
		$client_name = isset( $entry[ self::get_form_map_id( 'client_name' ) ] ) ? $entry[ self::get_form_map_id( 'client_name' ) ] : '';
		$email = isset( $entry[ self::get_form_map_id( 'email' ) ] ) ? $entry[ self::get_form_map_id( 'email' ) ] : '';
		$full_name = isset( $entry[ self::get_form_map_id( 'full_name' ) . '.3'] ) ? $entry[ self::get_form_map_id( 'full_name' ) . '.3' ] . ' ' . $entry[ self::get_form_map_id( 'full_name' ) . '.6' ] : '';
		$website = isset( $entry[ self::get_form_map_id( 'website' ) ] ) ? $entry[ self::get_form_map_id( 'website' ) ] : '';
		$contact_street = isset( $entry[ self::get_form_map_id( 'contact_address' ) . '.1'] ) ? $entry[ self::get_form_map_id( 'contact_address' ) . '.1'] . ' ' . $entry[ self::get_form_map_id( 'contact_address' ) . '.2'] : '';
		$contact_city = isset( $entry[ self::get_form_map_id( 'contact_address' ) . '.3'] ) ? $entry[ self::get_form_map_id( 'contact_address' ) . '.3'] : '';
		$contact_zone = isset( $entry[ self::get_form_map_id( 'contact_address' ) . '.4'] ) ? $entry[ self::get_form_map_id( 'contact_address' ) . '.4'] : '';
		$contact_postal_code = isset( $entry[ self::get_form_map_id( 'contact_address' ) . '.5'] ) ? $entry[ self::get_form_map_id( 'contact_address' ) . '.5'] : '';
		$contact_country = isset( $entry[ self::get_form_map_id( 'contact_address' ) . '.6'] ) ? $entry[ self::get_form_map_id( 'contact_address' ) . '.6'] : '';

		/**
		 * Build line item array
		 * @var array
		 */
		$line_item_list = array();
		if ( isset( $entry[ self::get_form_map_id( 'line_item_list' ) . '.1']) ) {
			$number_of_choices = count( self::line_item_choices() );
			for ( $i = 1; $i < $number_of_choices + 1; $i++ ) {
				if ( is_numeric( $entry[ self::get_form_map_id( 'line_item_list' ) . '.'.$i] ) ) {
					$line_item_list[] = $entry[ self::get_form_map_id( 'line_item_list' ) . '.'.$i];
				}
			}
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
			'form' => $form,
			'history_link' => sprintf( '<a href="%s">#%s</a>', add_query_arg( array( 'id' => $entry['form_id'], 'lid' => $entry['id'] ), admin_url( 'admin.php?page=gf_entries&view=entry' ) ), $entry['id'] ),
		);
		$invoice = self::maybe_create_invoice( $invoice_args, $entry['id'] );

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
				'contact_country' => $contact_country
			);
			$client_args = apply_filters( 'si_invoice_submission_maybe_process_gravity_form_client_args', $client_args, $entry, $form );
			self::maybe_create_client( $invoice, $client_args );

			do_action( 'si_invoice_submission_complete', $invoice->get_id() );
		}
	}
}
SI_IS_Gravity_Forms::init();