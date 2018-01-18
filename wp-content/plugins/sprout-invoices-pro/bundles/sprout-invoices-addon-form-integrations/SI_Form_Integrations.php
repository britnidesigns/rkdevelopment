<?php

class SI_Form_Integrations extends SI_Estimate_Submissions {
	const NINJA_FORM_ID = 'si_ninja_form_integration_id';
	const GRAVITY_FORM_ID = 'si_gravity_form_integration_id';
	const FORMIDABLE_FORM_ID = 'si_formidable_form_integration_id';
	const FORM_ID_MAPPING = 'si_integration_form_mapping';
	protected static $ninja_form_id;
	protected static $gravity_form_id;
	protected static $frdbl_form_id;
	protected static $form_mapping;
	// form fields
	protected static $subject_id;
	protected static $requirements_id;
	protected static $email_id;
	protected static $client_name_id;
	protected static $full_name_id;
	protected static $website_id;
	protected static $contact_street_id;
	protected static $contact_city_id;
	protected static $contact_zone_id;
	protected static $contact_postal_code_id;
	protected static $contact_country;

	public static function init() {
		// Store options
		self::$ninja_form_id = get_option( self::NINJA_FORM_ID, 0 );
		self::$gravity_form_id = get_option( self::GRAVITY_FORM_ID, 0 );
		self::$frdbl_form_id = get_option( self::FORMIDABLE_FORM_ID, 0 );
		self::$form_mapping = get_option( self::FORM_ID_MAPPING, array() );

		// filter options
		add_filter( 'si_add_options', array( __CLASS__, 'replace_integration_addon_option' ) );

		if ( self::$ninja_form_id ) {
			// 2.x
			add_action( 'ninja_forms_post_process', array( __CLASS__, 'maybe_process_ninjaforms_form' ), 10 );
			// 3.x
			add_action( 'ninja_forms_after_submission', array( __CLASS__, 'maybe_process_ninja3forms_form' ), 10 );
		}
		if ( self::$gravity_form_id ) {
			add_action( 'gform_after_submission', array( __CLASS__, 'maybe_process_gravity_form' ), 10, 2 );
		}
		if ( self::$frdbl_form_id ) {
			add_action( 'frm_after_create_entry', array( __CLASS__, 'maybe_process_formidable_form' ), 10, 2 );
		}
	}

	///////////////
	// Settings //
	///////////////

	public static function replace_integration_addon_option( $options = array() ) {
		if ( ! isset( $options['settings']['estimate_submissions']['settings']['advanced_submission_integration_addon'] ) ) {
			return $options;
		}
		// remove the integration addon ad
		unset( $options['settings']['estimate_submissions']['settings']['advanced_submission_integration_addon'] );
		// store other settings.
		$default_options = $options['settings']['estimate_submissions']['settings'];
		// combine the new settings with the old.
		$form_settings = self::register_form_settings();
		$options['settings']['estimate_submissions']['settings'] = array_merge( $form_settings, $default_options );
		return $options;
	}

	public static function register_form_settings() {
		$settings = array();
		$error = false;
		// Settings
		if ( class_exists( 'RGFormsModel' ) && method_exists( 'RGFormsModel', 'get_forms' ) ) {
			$gravity_options = array( 0 => __( 'No forms found', 'sprout-invoices' ) );
			if ( class_exists( 'RGFormsModel' ) && method_exists( 'RGFormsModel', 'get_forms' ) ) {
				$all_grav_forms = RGFormsModel::get_forms( 1, 'title' );
				$gravity_options = array();
				foreach ( $all_grav_forms as $form ) {
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
						'description' => sprintf( __( 'Select the submission form built with <a href="%s">Gravity Forms</a>.', 'sprout-invoices' ), 'https://sproutapps.co/link/gravity-forms' ),
						),
					),
				);
		} elseif ( function_exists( 'ninja_forms_get_all_forms' ) ) {
			$ninja_options = array( 0 => __( 'No forms found', 'sprout-invoices' ) );
			if ( function_exists( 'ninja_forms_get_all_forms' ) ) {
				$all_ninja_forms = ninja_forms_get_all_forms();
				$ninja_options = array();
				foreach ( $all_ninja_forms as $form ) {
					if ( isset( $form['data']['form_title'] ) ) {
						$ninja_options[ $form['id'] ] = $form['data']['form_title'];
					} else {
						$ninja_options[ $form['id'] ] = $form['data']['title'];
					}
				}
			}

			// Settings
			$settings = array(
				self::NINJA_FORM_ID => array(
					'label' => __( 'NinjaForms ID', 'sprout-invoices' ),
					'option' => array(
						'type' => 'select',
						'options' => $ninja_options,
						'default' => self::$ninja_form_id,
						'description' => sprintf( __( 'Select the submission form built with <a href="%s">Ninja Forms</a>.', 'sprout-invoices' ), 'https://sproutapps.co/link/ninja-forms' ),
						),
					),
				);
		} elseif ( function_exists( 'frm_forms_autoloader' ) ) {
			$frdbl_options = array( 0 => __( 'No forms found', 'sprout-invoices' ) );
			$forms = FrmForm::get_published_forms();
			if ( ! empty( $forms ) ) {
				$frdbl_options = array();
				foreach ( $forms as $form ) {
					$frdbl_options[ $form->id ] = ( ! isset( $form->name ) ) ? __( '(no title)', 'formidable' ) : esc_attr( FrmAppHelper::truncate( $form->name, 33 ) );
				}
			}
			// Settings
			$settings = array(
				self::FORMIDABLE_FORM_ID => array(
					'label' => __( 'Formidable ID', 'sprout-invoices' ),
					'option' => array(
						'type' => 'select',
						'options' => $frdbl_options,
						'default' => self::$frdbl_form_id,
						'description' => sprintf( __( 'Select the submission form built with <a href="%s">Formidable</a>.', 'sprout-invoices' ), 'https://sproutapps.co/link/formidable' ),
						),
					),
				);
		} else {
			// Settings
			$settings = array(
				self::NINJA_FORM_ID => array(
					'label' => __( 'Integration Error', 'sprout-invoices' ),
					'option' => array(
						'type' => 'bypass',
						'output' => sprintf( __( 'It looks like neither <a href="%s">Gravity Forms</a> or <a href="%s">Ninja Forms</a> or <a href="%s">Formidable</a> is active.', 'sprout-invoices' ), 'https://sproutapps.co/link/gravity-forms', 'https://sproutapps.co/link/ninja-forms', 'https://sproutapps.co/link/formidable' ),
						),
					),
				);
			$error = true;
		}

		$map_settings = array();
		if ( ! $error ) {
			// Settings
			$map_settings = array(
				self::FORM_ID_MAPPING => array(
					'label' => __( 'Form ID Mapping', 'sprout-invoices' ),
					'option' => array( __CLASS__, 'show_form_field_mapping' ),
					'sanitize_callback' => array( __CLASS__, 'save_form_field_mapping' ),
					),
				);
		}
		return array_merge( $settings, $map_settings );
	}

	public static function show_form_field_mapping() {
		$mappings = array(
			'subject' => isset( self::$form_mapping['subject'] ) ? self::$form_mapping['subject'] : '',
			'requirements' => isset( self::$form_mapping['requirements'] ) ? self::$form_mapping['requirements'] : '',
			'email' => isset( self::$form_mapping['email'] ) ? self::$form_mapping['email'] : '',
			'client_name' => isset( self::$form_mapping['client_name'] ) ? self::$form_mapping['client_name'] : '',
			'first_name' => isset( self::$form_mapping['first_name'] ) ? self::$form_mapping['first_name'] : '',
			'last_name' => isset( self::$form_mapping['last_name'] ) ? self::$form_mapping['last_name'] : '',
			'full_name' => isset( self::$form_mapping['full_name'] ) ? self::$form_mapping['full_name'] : '',
			'website' => isset( self::$form_mapping['website'] ) ? self::$form_mapping['website'] : '',
			'contact_address' => isset( self::$form_mapping['contact_address'] ) ? self::$form_mapping['contact_address'] : '',
			'contact_street' => isset( self::$form_mapping['contact_street'] ) ? self::$form_mapping['contact_street'] : '',
			'contact_city' => isset( self::$form_mapping['contact_city'] ) ? self::$form_mapping['contact_city'] : '',
			'contact_zone' => isset( self::$form_mapping['contact_zone'] ) ? self::$form_mapping['contact_zone'] : '',
			'contact_postal_code' => isset( self::$form_mapping['contact_postal_code'] ) ? self::$form_mapping['contact_postal_code'] : '',
			'contact_country' => isset( self::$form_mapping['contact_country'] ) ? self::$form_mapping['contact_country'] : '',
		);
		if ( defined( 'Ninja_Forms::VERSION' ) ) {
			if ( version_compare( floatval( Ninja_Forms::VERSION ), '3.0', '>=' ) ) {
				self::ninja_threes_options( $mappings );
				return;
			}
		}
		?>
			<p>
				<label><input type="text" name="sa_form_map_subject" value="<?php esc_attr_e( $mappings['subject'], 'sprout-invoices' ) ?>" size="2"> <?php _e( 'Subject/Title', 'sprout-invoices' ) ?></label><br/>
				<label><input type="text" name="sa_form_map_requirements" value="<?php esc_attr_e( $mappings['requirements'], 'sprout-invoices' ) ?>" size="2"> <?php _e( 'Requirements', 'sprout-invoices' ) ?></label><br/>
				<label><input type="text" name="sa_form_map_email" value="<?php esc_attr_e( $mappings['email'], 'sprout-invoices' ) ?>" size="2"> <?php _e( 'Email', 'sprout-invoices' ) ?></label><br/>
				<label><input type="text" name="sa_form_map_client_name" value="<?php esc_attr_e( $mappings['client_name'], 'sprout-invoices' ) ?>" size="2"> <?php _e( 'Client/Company Name', 'sprout-invoices' ) ?></label><br/>
				<label><input type="text" name="sa_form_map_website" value="<?php esc_attr_e( $mappings['website'], 'sprout-invoices' ) ?>" size="2"> <?php _e( 'Website', 'sprout-invoices' ) ?></label><br/>
				
				<?php if ( class_exists( 'RGFormsModel' ) && method_exists( 'RGFormsModel', 'get_forms' ) ) :  ?>

					<label><input type="text" name="sa_form_map_full_name" value="<?php esc_attr_e( $mappings['full_name'], 'sprout-invoices' ) ?>" size="2"> <?php _e( 'Full Name', 'sprout-invoices' ) ?></label><br/>
					<label><input type="text" name="sa_form_map_contact_address" value="<?php esc_attr_e( $mappings['contact_address'], 'sprout-invoices' ) ?>" size="2"> <?php _e( 'Address Fields', 'sprout-invoices' ) ?></label><br/>

				<?php else : ?>

					<label><input type="text" name="sa_form_map_first_name" value="<?php esc_attr_e( $mappings['first_name'], 'sprout-invoices' ) ?>" size="2"> <?php _e( 'First Name', 'sprout-invoices' ) ?></label>&nbsp;<label><input type="text" name="sa_form_map_last_name" value="<?php esc_attr_e( $mappings['last_name'], 'sprout-invoices' ) ?>" size="2"> <?php _e( 'Last Name', 'sprout-invoices' ) ?></label><br/>
					<label><input type="text" name="sa_form_map_contact_street" value="<?php esc_attr_e( $mappings['contact_street'], 'sprout-invoices' ) ?>" size="2"> <?php _e( 'Street Address', 'sprout-invoices' ) ?></label><br/>
					<label><input type="text" name="sa_form_map_contact_city" value="<?php esc_attr_e( $mappings['contact_city'], 'sprout-invoices' ) ?>" size="2"> <?php _e( 'City', 'sprout-invoices' ) ?></label><br/>
					<label><input type="text" name="sa_form_map_contact_zone" value="<?php esc_attr_e( $mappings['contact_zone'], 'sprout-invoices' ) ?>" size="2"> <?php _e( 'State/Province', 'sprout-invoices' ) ?></label><br/>
					<label><input type="text" name="sa_form_map_contact_postal_code" value="<?php esc_attr_e( $mappings['contact_postal_code'], 'sprout-invoices' ) ?>" size="2"> <?php _e( 'Postal Code', 'sprout-invoices' ) ?></label><br/>
					<label><input type="text" name="sa_form_map_contact_country" value="<?php esc_attr_e( $mappings['contact_country'], 'sprout-invoices' ) ?>" size="2"> <?php _e( 'Country', 'sprout-invoices' ) ?></label><br/>

				<?php endif ?>

			</p>
			<p class="description"><?php _e( 'Map the field IDs of your form to the data name.', 'sprout-invoices' ) ?></p>
		<?php
	}

	public static function ninja_forms_select_options( $input_name = '', $selected = 0 ) {
		if ( ! $selected ) {

		}
		$selected_form = ninja_forms_get_fields_by_form_id( self::$ninja_form_id );
		if ( empty( $selected_form ) ) {
			return '<code>&nbsp;&nbsp;&nbsp;</code>';
		}
		$option = sprintf( '<select type="select" name="sa_form_map_%s"><option></option>', $input_name );
		foreach ( $selected_form as $field ) {
			$option .= sprintf( '<option value="%s" %s>%s (%s)</option>', $field['id'], selected( $selected, $field['id'], false ), $field['data']['label'], $field['type'] );
		}
		$option .= '</select>';
		return $option;
	}

	public static function ninja_threes_options( $mappings = array() ) {
		$selected_form = ninja_forms_get_fields_by_form_id( self::$ninja_form_id );
		if ( empty( $selected_form ) ) {
			printf( '<p class="description">%s</p>', __( 'Select the form and save before mapping the inputs.', 'sprout-invoices' ) );
		} ?>
			<p>
				<label><?php echo self::ninja_forms_select_options( 'subject', $mappings['subject'] ) ?> <?php _e( 'Subject/Title', 'sprout-invoices' ) ?></label>

				<br/>
				<label><?php echo self::ninja_forms_select_options( 'requirements', $mappings['requirements'] ) ?> <?php _e( 'Requirements', 'sprout-invoices' ) ?></label>

				<br/>
				<label><?php echo self::ninja_forms_select_options( 'email', $mappings['email'] ) ?> <?php _e( 'Email', 'sprout-invoices' ) ?></label>

				<br/>
				<label><?php echo self::ninja_forms_select_options( 'client_name', $mappings['client_name'] ) ?> <?php _e( 'Client/Company Name', 'sprout-invoices' ) ?></label>

				<br/>
				<label><?php echo self::ninja_forms_select_options( 'website', $mappings['website'] ) ?> <?php _e( 'Website', 'sprout-invoices' ) ?></label>

				<br/>
				<label><?php echo self::ninja_forms_select_options( 'first_name', $mappings['first_name'] ) ?> <?php _e( 'First Name', 'sprout-invoices' ) ?></label>&nbsp;<label><?php echo self::ninja_forms_select_options( 'last_name', $mappings['last_name'] ) ?> <?php _e( 'Last Name', 'sprout-invoices' ) ?></label>

				<br/>
				<label><?php echo self::ninja_forms_select_options( 'contact_street', $mappings['contact_street'] ) ?> <?php _e( 'Street Address', 'sprout-invoices' ) ?></label>

				<br/>
				<label><?php echo self::ninja_forms_select_options( 'contact_city', $mappings['contact_city'] ) ?> <?php _e( 'City', 'sprout-invoices' ) ?></label>

				<br/>
				<label><?php echo self::ninja_forms_select_options( 'contact_zone', $mappings['contact_zone'] ) ?> <?php _e( 'State/Province', 'sprout-invoices' ) ?></label>

				<br/>
				<label><?php echo self::ninja_forms_select_options( 'contact_postal_code', $mappings['contact_postal_code'] ) ?> <?php _e( 'Postal Code', 'sprout-invoices' ) ?></label>

				<br/>
				<label><?php echo self::ninja_forms_select_options( 'contact_country', $mappings['contact_country'] ) ?> <?php _e( 'Country', 'sprout-invoices' ) ?>

				<br/>

			</p>
			<p class="description"><?php _e( 'Map the field IDs of your form to the data name.', 'sprout-invoices' ) ?></p>
		<?php
	}

	public static function save_form_field_mapping( $mappings = array() ) {
		$mappings = array(
			'subject' => isset( $_POST['sa_form_map_subject'] ) ? $_POST['sa_form_map_subject'] : '',
			'requirements' => isset( $_POST['sa_form_map_requirements'] ) ? $_POST['sa_form_map_requirements'] : '',
			'email' => isset( $_POST['sa_form_map_email'] ) ? $_POST['sa_form_map_email'] : '',
			'client_name' => isset( $_POST['sa_form_map_client_name'] ) ? $_POST['sa_form_map_client_name'] : '',
			'first_name' => isset( $_POST['sa_form_map_first_name'] ) ? $_POST['sa_form_map_first_name'] : '',
			'last_name' => isset( $_POST['sa_form_map_last_name'] ) ? $_POST['sa_form_map_last_name'] : '',
			'full_name' => isset( $_POST['sa_form_map_full_name'] ) ? $_POST['sa_form_map_full_name'] : '',
			'website' => isset( $_POST['sa_form_map_website'] ) ? $_POST['sa_form_map_website'] : '',
			'contact_address' => isset( $_POST['sa_form_map_contact_address'] ) ? $_POST['sa_form_map_contact_address'] : '',
			'contact_street' => isset( $_POST['sa_form_map_contact_street'] ) ? $_POST['sa_form_map_contact_street'] : '',
			'contact_city' => isset( $_POST['sa_form_map_contact_city'] ) ? $_POST['sa_form_map_contact_city'] : '',
			'contact_zone' => isset( $_POST['sa_form_map_contact_zone'] ) ? $_POST['sa_form_map_contact_zone'] : '',
			'contact_postal_code' => isset( $_POST['sa_form_map_contact_postal_code'] ) ? $_POST['sa_form_map_contact_postal_code'] : '',
			'contact_country' => isset( $_POST['sa_form_map_contact_country'] ) ? $_POST['sa_form_map_contact_country'] : '',
		);
		return $mappings;
	}


	////////////////////
	// Process forms //
	////////////////////

	public static function maybe_process_ninja3forms_form( $form_data ) {
		// Only a specific form do this process
		if ( (int) $form_data['form_id'] !== (int) self::$ninja_form_id ) {
			return;
		}
		$all_fields = $form_data['fields'];

		$subject = $all_fields[ self::$form_mapping['subject'] ]['value'];
		$requirements = $all_fields[ self::$form_mapping['requirements'] ]['value'];
		$email = $all_fields[ self::$form_mapping['email'] ]['value'];
		$client_name = $all_fields[ self::$form_mapping['client_name'] ]['value'];
		$full_name = $all_fields[ self::$form_mapping['first_name'] ]['value'] . ' ' . $all_fields[ self::$form_mapping['last_name'] ]['value'];
		$website = $all_fields[ self::$form_mapping['website'] ]['value'];
		$contact_street = $all_fields[ self::$form_mapping['contact_street'] ]['value'];
		$contact_city = $all_fields[ self::$form_mapping['contact_city'] ]['value'];
		$contact_zone = $all_fields[ self::$form_mapping['contact_zone'] ]['value'];
		$contact_postal_code = $all_fields[ self::$form_mapping['contact_postal_code'] ]['value'];
		$contact_country = $all_fields[ self::$form_mapping['contact_country'] ]['value'];

		$sub_id = $form_data['actions']['save']['sub_id'];

		$estimate_args = array(
			'subject' => $subject,
			'requirements' => $requirements,
			'fields' => array(),
			'entry' => $all_fields,
			'history_link' => sprintf( '<a href="%s">#%s</a>', add_query_arg( array( 'post' => $sub_id ), admin_url( 'post.php?action=edit' ) ), $sub_id ),
		);
		$estimate = self::maybe_create_estimate( $estimate_args );

		if ( is_a( $estimate, 'SI_Estimate' ) ) {
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
			$client_args = apply_filters( 'si_afi_maybe_process_ninjaforms_form_client_args', $client_args, $all_fields, $ninja_forms_processing );
			self::maybe_create_client( $estimate, $client_args );
		}

	}

	public static function maybe_process_ninjaforms_form( $form_data ) {
		// Only a specific form do this process
		if ( (int) $form_data['form_id'] !== (int) self::$ninja_form_id ) {
			return;
		}
		$all_fields = $form_data['fields'];

		$subject = $all_fields[ self::$form_mapping['subject'] ];
		$requirements = $all_fields[ self::$form_mapping['requirements'] ];
		$email = $all_fields[ self::$form_mapping['email'] ];
		$client_name = $all_fields[ self::$form_mapping['client_name'] ];
		$full_name = $all_fields[ self::$form_mapping['first_name'] ] . ' ' . $all_fields[ self::$form_mapping['last_name'] ];
		$website = $all_fields[ self::$form_mapping['website'] ];
		$contact_street = $all_fields[ self::$form_mapping['contact_street'] ];
		$contact_city = $all_fields[ self::$form_mapping['contact_city'] ];
		$contact_zone = $all_fields[ self::$form_mapping['contact_zone'] ];
		$contact_postal_code = $all_fields[ self::$form_mapping['contact_postal_code'] ];
		$contact_country = $all_fields[ self::$form_mapping['contact_country'] ];

		$estimate_args = array(
			'subject' => $subject,
			'requirements' => $requirements,
			'fields' => array(),
			'entry' => $all_fields,
			'history_link' => sprintf( '<a href="%s">#%s</a>', add_query_arg( array( 'post' => $ninja_forms_processing->data['form']['sub_id'] ), admin_url( 'post.php?action=edit' ) ), $ninja_forms_processing->data['form']['sub_id'] ),
		);
		$estimate = self::maybe_create_estimate( $estimate_args );

		if ( is_a( $estimate, 'SI_Estimate' ) ) {
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
				'entry' => $all_fields,
			);
			$client_args = apply_filters( 'si_afi_maybe_process_ninjaforms_form_client_args', $client_args, $all_fields, $ninja_forms_processing );
			self::maybe_create_client( $estimate, $client_args );
		}

	}

	public static function maybe_process_gravity_form( $entry, $form ) {
		// Only a specific form do this process
		if ( $entry['form_id'] != self::$gravity_form_id ) {
			return;
		}

		$subject = $entry[ self::$form_mapping['subject'] ];
		$requirements = $entry[ self::$form_mapping['requirements'] ];
		$email = $entry[ self::$form_mapping['email'] ];
		$client_name = $entry[ self::$form_mapping['client_name'] ];
		$full_name = $entry[ self::$form_mapping['full_name'].'.3' ] . ' ' . $entry[ self::$form_mapping['full_name'].'.6' ];
		$website = $entry[ self::$form_mapping['website'] ];
		$contact_street = $entry[ self::$form_mapping['contact_address'].'.1' ] . ' ' . $entry[ self::$form_mapping['contact_address'].'.2' ];
		$contact_city = $entry[ self::$form_mapping['contact_address'].'.3' ];
		$contact_zone = $entry[ self::$form_mapping['contact_address'].'.4' ];
		$contact_postal_code = $entry[ self::$form_mapping['contact_address'].'.5' ];
		$contact_country = $entry[ self::$form_mapping['contact_address'].'.6' ];

		$estimate_args = array(
			'subject' => $subject,
			'requirements' => $requirements,
			'fields' => array(),
			'entry' => $entry,
			'form' => $form,
			'history_link' => sprintf( '<a href="%s">#%s</a>', add_query_arg( array( 'id' => $entry['form_id'], 'lid' => $entry['id'] ), admin_url( 'admin.php?page=gf_entries&view=entry' ) ), $entry['id'] ),
		);
		$estimate = self::maybe_create_estimate( $estimate_args );

		if ( is_a( $estimate, 'SI_Estimate' ) ) {
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
			$client_args = apply_filters( 'si_afi_maybe_process_gravity_form_client_args', $client_args, $entry, $form );
			self::maybe_create_client( $estimate, $client_args );
		}
	}

	public static function maybe_process_formidable_form( $entry_id, $form_id ) {
		if ( $form_id != self::$frdbl_form_id ) {
			return;
		}

		$subject = $_POST['item_meta'][ self::$form_mapping['subject'] ];
		$requirements = $_POST['item_meta'][ self::$form_mapping['requirements'] ];
		$email = $_POST['item_meta'][ self::$form_mapping['email'] ];
		$client_name = $_POST['item_meta'][ self::$form_mapping['client_name'] ];
		$full_name = $_POST['item_meta'][ self::$form_mapping['first_name'] ] . ' ' . $_POST['item_meta'][ self::$form_mapping['last_name'] ];
		$website = $_POST['item_meta'][ self::$form_mapping['website'] ];
		$contact_street = $_POST['item_meta'][ self::$form_mapping['contact_street'] ];
		$contact_city = $_POST['item_meta'][ self::$form_mapping['contact_city'] ];
		$contact_zone = $_POST['item_meta'][ self::$form_mapping['contact_zone'] ];
		$contact_postal_code = $_POST['item_meta'][ self::$form_mapping['contact_postal_code'] ];
		$contact_country = $_POST['item_meta'][ self::$form_mapping['contact_country'] ];

		$estimate_args = array(
			'subject' => $subject,
			'requirements' => $requirements,
			'fields' => array(),
			'entry' => $_POST,
			'history_link' => sprintf( '<a href="%s">Entry #%s</a>', add_query_arg( array( 'frm_action' => 'show', 'id' => $entry_id ), admin_url( 'admin.php?page=formidable-entries' ) ), $entry_id ),
		);
		$estimate = self::maybe_create_estimate( $estimate_args );

		if ( is_a( $estimate, 'SI_Estimate' ) ) {
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
			$client_args = apply_filters( 'si_afi_maybe_process_formidable_form_client_args', $client_args, $_POST );
			self::maybe_create_client( $estimate, $client_args );
		}
	}

	//////////////
	// Utility //
	//////////////

	/**
	 * Create Estimate
	 * @param  array  $args * subject
	 *                      * requirements
	 *                      * fields
	 *
	 */
	public static function maybe_create_estimate( $args = array() ) {
		$defaults = array(
			'subject' => sprintf( __( 'New Estimate: %s', 'sprout-invoices' ), date( get_option( 'date_format' ).' @ '.get_option( 'time_format' ), current_time( 'timestamp' ) ) ),
			'requirements' => __( 'No requirements submitted. Check to make sure the "requirements" field is required.', 'sprout-invoices' ),
			'fields' => $_REQUEST,
		);
		$parsed_args = apply_filters( 'si_afi_maybe_create_estimate', wp_parse_args( $args, $defaults ) );

		// Create estimate
		$estimate_id = SI_Estimate::create_estimate( $parsed_args );
		$estimate = SI_Estimate::get_instance( $estimate_id );

		// TODO Add images

		// TODO Set the solution type

		// End, don't use estimate_submitted since a notification will be fired.
		do_action( 'estimate_submitted_from_adv_form', $estimate, $parsed_args );

		// History
		do_action( 'si_new_record',
			sprintf( __( 'Estimate Submitted: Form %s.', 'sprout-invoices' ), $parsed_args['history_link'] ),
			self::SUBMISSION_UPDATE,
			$estimate_id,
			sprintf( __( 'Estimate Submitted: Form %s.', 'sprout-invoices' ), $parsed_args['history_link'] ),
			0,
		false );

		return $estimate;
	}

	/**
	 * Maybe create a client from submission
	 * @param  SI_Estimate $estimate
	 * @param  array       $args     * email - required
	 *                               * client_id - if client_id is passed than just assign estimate
	 *                               * client_name - required
	 *                               * full_name -
	 *                               * website
	 *                               * contact_street
	 *                               * contact_city
	 *                               * contact_zone
	 *                               * contact_postal_code
	 *                               * contact_country
	 *
	 */
	public static function maybe_create_client( SI_Estimate $estimate, $args = array() ) {

		$args = apply_filters( 'si_afi_maybe_create_client', $args );

		$client_id = ( isset( $args['client_id'] ) && get_post_type( $args['client_id'] ) == SI_Client::POST_TYPE ) ? $args['client_id'] : 0;
		$user_id = get_current_user_id();

		// check to see if the user exists by email
		if ( isset( $args['email'] ) && $args['email'] != '' ) {
			if ( $user = get_user_by( 'email', $args['email'] ) ) {
				$user_id = $user->ID;
			}
		}

		// Check to see if the user is assigned to a client already
		if ( ! $client_id ) {
			$client_ids = SI_Client::get_clients_by_user( $user_id );
			if ( ! empty( $client_ids ) ) {
				$client_id = array_pop( $client_ids );
			}
		}

		// Create a user for the submission if an email is provided.
		if ( ! $user_id ) {
			// email is critical
			if ( isset( $args['email'] ) && $args['email'] != '' ) {
				$user_args = array(
					'user_login' => esc_html( $args['email'] ),
					'display_name' => isset( $args['client_name'] ) ? esc_html( $args['client_name'] ) : esc_html( $args['email'] ),
					'user_pass' => wp_generate_password(), // random password
					'user_email' => isset( $args['email'] ) ? esc_html( $args['email'] ) : '',
					'first_name' => si_split_full_name( esc_html( $args['full_name'] ), 'first' ),
					'last_name' => si_split_full_name( esc_html( $args['full_name'] ), 'last' ),
					'user_url' => isset( $args['website'] ) ? esc_html( $args['website'] ) : '',
				);
				$user_id = SI_Clients::create_user( $user_args );
			}
		}

		// create the client based on what's submitted.
		if ( ! $client_id ) {
			$address = array(
				'street' => isset( $args['contact_street'] ) ?esc_html( $args['contact_street'] ) : '',
				'city' => isset( $args['contact_city'] ) ? esc_html( $args['contact_city'] ) : '',
				'zone' => isset( $args['contact_zone'] ) ? esc_html( $args['contact_zone'] ) : '',
				'postal_code' => isset( $args['contact_postal_code'] ) ? esc_html( $args['contact_postal_code'] ) : '',
				'country' => isset( $args['contact_country'] ) ? esc_html( $args['contact_country'] ) : '',
			);

			$args = array(
				'company_name' => isset( $args['client_name'] ) ? esc_html( $args['client_name'] ) : '',
				'website' => isset( $args['website'] ) ? esc_html( $args['website'] ) : '',
				'address' => $address,
				'user_id' => $user_id,
			);

			$client_id = SI_Client::new_client( $args );
			// History
			do_action( 'si_new_record',
				sprintf( 'Client Created & Assigned: %s', get_the_title( $client_id ) ),
				self::SUBMISSION_UPDATE,
				$estimate->get_id(),
				sprintf( 'Client Created & Assigned: %s', get_the_title( $client_id ) ),
				0,
			false );
		}

		// Set the estimates client
		$estimate->set_client_id( $client_id );

		do_action( 'si_created_client_via_submission', $client_id, $args, $estimate );
	}
}
