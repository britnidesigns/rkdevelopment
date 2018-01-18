<?php

/**
 * Expense_Tracking Controller
 *
 * @package Sprout_Invoice
 * @subpackage Expense_Tracking
 */
class SI_Expense_Tracking_Premium extends SI_Controller {
	const SUBMISSION_NONCE = 'si_expense_submission';
	const IMPORT_QUERY_VAR = 'import-unbilled-expense';
	const LINE_ITEM_TYPE = 'expense';


	public static function init() {

		if ( is_admin() ) {
			// Meta boxes
			add_action( 'admin_init', array( __CLASS__, 'register_meta_boxes' ) );

			add_action( 'project_quick_links', array( __CLASS__, 'quick_link' ) );

			// Invoicing
			add_action( 'si_post_add_line_item', array( __CLASS__, 'import_billed_expense' ) );
			add_action( 'si_save_line_items_meta_box', array( __CLASS__, 'mark_expense_billed' ), 10, 3 );

			// AJAX
			add_action( 'wp_ajax_sa_get_expense_item',  array( __CLASS__, 'maybe_get_item' ), 10, 0 );

			// Admin bar
			add_action( 'admin_bar_menu', array( __CLASS__, 'add_expense_button' ), 62 );

			// Enqueue
			add_action( 'admin_enqueue_scripts', array( __CLASS__, 'register_resources' ) );
			add_action( 'admin_enqueue_scripts', array( __CLASS__, 'admin_enqueue' ), 20 );

			// Dash widgets
			add_action( 'si_dashboard_setup', array( __CLASS__, 'add_dashboard_widgets' ) );
			add_action( 'wp_dashboard_setup', array( __CLASS__, 'add_dashboard_widgets' ), 10, 0 );

			// Admin columns
			add_filter( 'manage_edit-'.SI_Project::POST_TYPE.'_columns', array( __CLASS__, 'register_columns' ), 100 );
			add_filter( 'manage_'.SI_Project::POST_TYPE.'_posts_custom_column', array( __CLASS__, 'column_display' ), 100, 2 );
		}

		// Add Expense Type
		add_filter( 'si_line_item_types',  array( __CLASS__, 'add_expense_line_item_type' ) );
		add_filter( 'si_line_item_columns',  array( __CLASS__, 'add_expense_line_item_type_columns' ), -10, 2 );

		// ajax actions
		add_action( 'wp_ajax_sa_create_category',  array( __CLASS__, 'maybe_create_expense_category' ), 10, 0 );
		add_action( 'wp_ajax_sa_save_expense',  array( __CLASS__, 'maybe_save_expense' ), 10, 0 );
		add_action( 'wp_ajax_sa_remove_expense_entry',  array( __CLASS__, 'maybe_delete_expense_entry' ), 10, 0 );
		add_action( 'wp_ajax_sa_remove_expense',  array( __CLASS__, 'maybe_delete_expense' ), 10, 0 );
		add_action( 'wp_ajax_sa_projects_expense',  array( __CLASS__, 'projects_expense' ), 10, 0 );
		// ajax views
		add_action( 'wp_ajax_sa_manage_expense',  array( __CLASS__, 'expense_admin_view' ), 10, 0 );
		add_action( 'wp_ajax_sa_expense_creation',  array( __CLASS__, 'expense_creation_view' ), 10, 0 );
		add_action( 'wp_ajax_sa_expense_tracker',  array( __CLASS__, 'expense_tracker_view' ), 10, 0 );
		add_action( 'wp_ajax_sa_expense_entries_table',  array( __CLASS__, 'expense_entries_table_view' ), 10, 0 );

		add_filter( 'si_admin_scripts_localization',  array( __CLASS__, 'ajax_l10n' ) );

		// front-end
		add_action( 'wp_ajax_sa_expense_tracker_view',  array( __CLASS__, 'front_end_expense_tracker_view' ), 10, 0 );
		add_action( 'wp_ajax_nopriv_sa_expense_tracker_view',  array( __CLASS__, 'front_end_expense_tracker_view' ), 10, 0 );
	}

	///////////////
	// Line Item //
	///////////////

	public static function add_expense_line_item_type( $types = array() ) {
		$types = array_merge( $types, array( self::LINE_ITEM_TYPE => __( 'Expense', 'sprout-invoices' ) ) );
		return $types;
	}

	public static function add_expense_line_item_type_columns( $columns = array(), $type = '' ) {
		if ( self::LINE_ITEM_TYPE !== $type ) {
			return $columns;
		}
		$columns = array(
			'desc' => array(
					'label' => __( 'Expense', 'sprout-invoices' ),
					'type' => 'textarea',
					'calc' => false,
					'hide_if_parent' => false,
					'weight' => 1,
				),
			'rate' => array(
					'label' => __( 'Cost', 'sprout-invoices' ),
					'type' => 'small-input',
					'placeholder' => '120',
					'calc' => false,
					'hide_if_parent' => true,
					'weight' => 5,
				),
			'qty' => array(
					'type' => 'hidden',
					'placeholder' => 1,
					'calc' => true,
					'hide_if_parent' => true,
					'weight' => 10,
				),
			'tax' => array(
					'label' => sprintf( '&#37; <span class="helptip" title="%s"></span>', __( 'A percentage adjustment per line item, i.e. tax or discount', 'sprout-invoices' ) ),
					'type' => 'small-input',
					'placeholder' => 0,
					'calc' => false,
					'hide_if_parent' => true,
					'weight' => 15,
				),
			'total' => array(
					'label' => __( 'Amount', 'sprout-invoices' ),
					'type' => 'total',
					'placeholder' => sa_get_formatted_money( 0 ),
					'calc' => true,
					'hide_if_parent' => false,
					'weight' => 50,
				),
			'sku' => array(
					'type' => 'hidden',
					'placeholder' => '',
					'calc' => false,
					'weight' => 50,
				),
			'expense_id' => array(
					'type' => 'hidden',
					'placeholder' => '',
					'calc' => false,
					'weight' => 50,
				),
		);
		return $columns;
	}

	//////////////
	// Enqueue //
	//////////////

	public static function register_resources() {
		// admin js
		wp_register_script( 'si_expense_tracking', SA_ADDON_EXPENSE_TRACKING_URL . '/resources/admin/js/expense_entry.js', array( 'jquery' ), self::SI_VERSION );
	}

	public static function admin_enqueue() {
		add_thickbox();
		wp_enqueue_media();

		wp_localize_script( 'si_expense_tracking', 'expense_upload',
			array(
				'title' => __( 'Choose or Upload a File', 'sprout-invoices' ),
				'button' => __( 'Attach File', 'sprout-invoices' ),
				)
		);
		wp_enqueue_script( 'si_expense_tracking' );
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
		// estimate specific
		$args = array(
			'si_project_expensetracking' => array(
				'title' => __( 'Expense Tracking', 'sprout-invoices' ),
				'show_callback' => array( __CLASS__, 'show_expensetracking_meta_box' ),
				'save_callback' => array( __CLASS__, 'save_meta_box_expense_tracking' ),
				'context' => 'normal',
				'priority' => 'high',
				'save_priority' => 0,
			),
		);
		do_action( 'sprout_meta_box', $args, SI_Project::POST_TYPE );
	}

	public static function quick_link() {

	}

	/**
	 * Show expense tracking metabox.
	 * @param  WP_Post $post
	 * @param  array $metabox
	 * @return
	 */
	public static function show_expensetracking_meta_box( $post, $metabox ) {
		$project = SI_Project::get_instance( $post->ID );
		$expenses = $project->get_associated_expenses();
		self::load_addon_view( 'admin/meta-boxes/projects/expense-entries', array(
				'expenses' => $expenses,
				'project_id' => $project->get_id(),
		), true );

		// Form
		$fields = self::expense_entry_fields( $post->ID, true );
		self::load_addon_view( 'admin/sections/expense-tracker', array(
				'fields' => $fields,
				'project_id' => $project->get_id(),
		), true );
	}

	public static function save_meta_box_expense_tracking() {
		// Expense tracking is done via AJAX
	}

	////////////////
	// Invoicing //
	////////////////

	public static function import_billed_expense() {
		$invoice = SI_Invoice::get_instance( get_the_ID() );
		if ( ! is_a( $invoice, 'SI_Invoice' ) ) {
			return;
		}
		$client_id = $invoice->get_client_id();
		$project_id = $invoice->get_project_id();

		self::load_addon_view( 'admin/meta-boxes/invoices/expense-invoicing.php', array(
				'invoice' => $invoice,
				'project_id' => $project_id,
				'client_id' => $client_id,
		), true );
	}

	/**
	 * Save the expense id within the line item data array, then add the invoice id
	 * to the the expense so it will be marked as billed.
	 * @param  int $invoice_id
	 * @param  array $post
	 * @param  object $invoice
	 * @return
	 */
	public static function mark_expense_billed( $invoice_id, $post, $invoice ) {
		if ( ! is_a( $invoice, 'SI_Invoice' ) ) { // not estimates
			return;
		}

		// expense to mark as billed
		$mark_expense_billed = array();
		$line_items = $invoice->get_line_items();
		foreach ( $_POST['line_item_key'] as $key => $order ) {
			if ( isset( $_POST['line_item_desc'][ $key ] ) && $_POST['line_item_desc'][ $key ] != '' ) {
				if ( isset( $line_items[ $order ] ) ) {
					$line_items[ $order ]['expense_id'] = ( isset( $_POST['line_item_expense_id'][ $key ] ) && $_POST['line_item_expense_id'][ $key ] != '' ) ? $_POST['line_item_expense_id'][ $key ] : 0;
					if ( $line_items[ $order ]['expense_id'] ) {
						$mark_expense_billed[] = $line_items[ $order ]['expense_id'];
					}
				}
			}
		}

		// Add the invoice id to the expense's data
		foreach ( $mark_expense_billed as $expense_id ) {
			SI_Expense::add_invoice_id( $expense_id, $invoice_id );
		}
	}

	public static function add_expense_button( WP_Admin_Bar $wp_admin_bar ) {

		if ( ! current_user_can( 'edit_sprout_invoices' ) ) {
			return;
		}

		// do not show on project page, since the view is inserted already.
		if ( get_the_id() && get_post_type( get_the_id() ) === SI_Project::POST_TYPE ) {
			return;
		}

		$args = array(
			'id'    => 'si_expense_tracker',
			'title' => '<span class="dashicons dashicons-paperclip ab-icon"></span>',
			'href'	=> '#expense_tracker',
			'meta'  => array( 'class' => 'expense_tracker_popup' ),
		);
		$wp_admin_bar->add_node( $args );

	}

	public static function maybe_get_item() {
		if ( ! current_user_can( 'publish_sprout_invoices' ) ) {
			wp_send_json_error( array( 'message' => __( 'User cannot create an item!', 'sprout-invoices' ) ) );
		}

		$expense = array();
		if ( isset( $_REQUEST['expense'] ) && is_array( $_REQUEST['expense'] ) ) {
			$expense = $_REQUEST['expense'];
		}

		if ( ! $expense ) {
			wp_send_json_error( array( 'message' => __( 'No expense given!', 'sprout-invoices' ) ) );
		}
		$item_data = array(
				'type' => self::LINE_ITEM_TYPE,
				'desc' => $expense['description'],
				'rate' => $expense['rate'],
				'qty' => $expense['qty'],
				'sku' => $expense['category_id'],
				'expense_id' => $expense['id'],
			);

		ob_start();
		SI_Controller::load_view( 'admin/sections/line-item-options', array(
			'columns' => SI_Line_Items::line_item_columns( 'expense' ),
			'item_data' => $item_data,
			'has_children' => false,
			'items' => array(),
			'position' => 0,
			'children' => array(),
		), false );
		$option = ob_get_clean();

		$view = sprintf( '<li id="line_item_loaded_%1$s" class="item line_item_type_%1$s" data-id="0">%2$s</li>', $item_data['type'], $option );

		$response = array(
				'option' => $view,
				'type' => $item_data['type'],
			);
		wp_send_json_success( $response );
	}


	///////////
	// Form //
	///////////

	public static function expense_entry_fields( $context = 0, $hide_project_select = false ) {
		$projects = array();
		$expense_types = array();
		if ( ! $context ) {
			$context = get_the_id();
		}
		// Get associated projects based on context
		switch ( get_post_type( $context ) ) {
			case SI_Project::POST_TYPE:
				$project_id = $context;
				// Projects
				$projects = array( $project_id );
				break;
			case SI_Client::POST_TYPE:
				$client_id = $context;
				// Projects
				$projects = SI_Project::get_projects_by_client( $client_id );
				break;
			case SI_Invoice::POST_TYPE:
				$invoice_id = $context;
				// Projects
				$invoice = SI_Invoice::get_instance( $invoice_id );
				$project_id = $invoice->get_project();
				if ( $project_id ) {
					$projects = array( $project_id );
				}
				break;

			default:
				// Projects
				$args = array(
					'post_type' => SI_Project::POST_TYPE,
					'post_status' => 'any',
					'posts_per_page' => -1,
					'fields' => 'ids',
				);
				$projects = get_posts( $args );
				break;
		}

		$fields = array();

		$project_options = array();
		foreach ( $projects as $project_id ) {
			$title = get_the_title( $project_id );
			$title = ( $title == __( 'Auto Draft' ) ) ? __( 'Current Project', 'sprout-invoices' ) : $title ;
			$project_options[ $project_id ] = $title;
		}
		$fields['project_id'] = array(
				'weight' => 1,
				'label' => __( 'Project', 'sprout-invoices' ),
				'type' => 'select',
				'options' => $project_options,
			);

		$description = sprintf( __( 'Select an category, <a href="%s">create a new category</a> or <a class="thickbox" href="%s" title="Edit Categories">manage existing categories</a>.', 'sprout-invoices' ), 'javascript:void(0)" id="show_expense_creation_modal"', admin_url( 'admin-ajax.php?action=sa_manage_expense&width=750&height=450' ) );

		$expense_types_options = SI_Expense::get_categories();
		$fields['category_id'] = array(
				'weight' => 10,
				'label' => __( 'Category', 'sprout-invoices' ),
				'type' => 'select',
				'description' => $description,
				'options' => $expense_types_options,
			);

		$fields['title'] = array(
			'weight' => 15,
			'label' => __( 'Expense', 'sprout-invoices' ),
			'type' => 'text',
			'placeholder' => __( 'Ghost Busters', 'sprout-invoices' ),
			'default' => '',
		);

		$fields['expense_cost'] = array(
			'weight' => 20,
			'label' => __( 'Cost', 'sprout-invoices' ),
			'type' => 'text',
			'placeholder' => __( '1,968.42', 'sprout-invoices' ),
			'description' => sprintf( __( 'Number formated, i.e. 1.25 instead of %s.', 'sprout-invoices' ), sa_get_formatted_money( 1.25 ) ),
		);

		$fields['note'] = array(
			'weight' => 30,
			'label' => __( 'Note', 'sprout-invoices' ),
			'type' => 'textarea',
			'default' => '',
			'placeholder' => __( 'Called them to remove ghosts from client website and they charged for a year of containment services.', 'sprout-invoices' ),
		);

		$fields['attachments'] = array(
			'weight' => 35,
			'label' => __( 'Attachments', 'sprout-invoices' ),
			'type' => 'bypass',
			'output' => self::media_option(),
			'default' => '',
		);

		$fields['date'] = array(
			'weight' => 100,
			'label' => __( 'Date', 'sprout-invoices' ),
			'type' => 'date',
			'default' => date( 'Y-m-d', current_time( 'timestamp' ) ),
			'placeholder' => '',
		);

		$fields['nonce'] = array(
			'type' => 'hidden',
			'value' => wp_create_nonce( self::SUBMISSION_NONCE ),
			'weight' => 10000,
		);

		$fields = apply_filters( 'si_expense_entry_form_fields', $fields );
		uasort( $fields, array( __CLASS__, 'sort_by_weight' ) );
		return $fields;
	}

	public static function media_option() {
		ob_start();
		?>
			<style type="text/css">
				#expense_attachment_thumbnails a {
					margin-right: 30px;
					margin-left: 20px;
				}
				#expense_attachments label {
					display: inline-block;
					margin-left: 20px;
				}
			</style>
			<p id="expense_attachments">
				<input type="button" id="add-expense-atachments" class="button" value="<?php _e( 'Choose or Upload a File', 'sprout-invoices' )?>" />
			</p>
			<p id="expense_attachment_thumbnails"></p>
		<?php
		$option = ob_get_clean();
		return $option;
	}

	public static function expense_creation_fields( $id = 0 ) {

		$fields['name'] = array(
			'weight' => 0,
			'label' => __( 'Category Name', 'sprout-invoices' ),
			'type' => 'text',
		);

		$fields['billable'] = array(
			'weight' => 50,
			'label' => __( 'Billable', 'sprout-invoices' ),
			'type' => 'checkbox',
		);

		$fields['nonce'] = array(
			'type' => 'hidden',
			'value' => wp_create_nonce( self::SUBMISSION_NONCE ),
			'weight' => 10000,
		);

		$fields = apply_filters( 'si_expense_creation_form_fields', $fields );
		uasort( $fields, array( __CLASS__, 'sort_by_weight' ) );
		return $fields;
	}

	/////////////////////
	// AJAX Callbacks //
	/////////////////////

	public static function maybe_create_expense_category() {
		if ( ! current_user_can( 'edit_sprout_invoices' ) ) {
			self::ajax_fail( 'User cannot create SI expense!' );
		}

		$nonce = $_REQUEST['nonce'];
		if ( ! wp_verify_nonce( $nonce, self::SUBMISSION_NONCE ) ) {
			self::ajax_fail( 'Not going to fall for it!' ); }

		if ( ! isset( $_REQUEST['name'] ) || $_REQUEST['name'] == '' ) {
			self::ajax_fail( 'No name given.' );
		}

		$args = array();
		$args['name'] = $_REQUEST['name'];
		$args['billable'] = ( isset( $_REQUEST['billable'] ) ) ? true : false ;
		if ( isset( $_REQUEST['rate'] ) ) {
			$args['rate'] = (float) $_REQUEST['rate'];
		}
		if ( isset( $_REQUEST['percentage'] ) ) {
			$args['percentage'] = (float) $_REQUEST['percentage'];
		}
		$id = SI_Expense::new_category( $args );
		$expense = SI_Expense::get_instance( $id );

		$response = array(
				'id' => $id,
				'title' => get_the_title( $id ),
				'option_name' => $expense->get_title(),
			);

		header( 'Content-type: application/json' );
		if ( self::DEBUG ) { header( 'Access-Control-Allow-Origin: *' ); }
		echo wp_json_encode( $response );
		exit();
	}

	public static function maybe_save_expense() {
		$nonce = $_REQUEST['nonce'];
		if ( ! wp_verify_nonce( $nonce, self::SUBMISSION_NONCE ) ) {
			self::ajax_fail( 'Not going to fall for it!' );
		}

		if ( isset( $_REQUEST['project_id'] ) ) {
			$project_id = $_REQUEST['project_id'];
		}
		if ( ! $project_id ) {
			self::ajax_fail( 'No project id' );
		}

		if ( isset( $_REQUEST['expense_val'] ) ) {
			$expense_val = floatval( $_REQUEST['expense_val'] );
		}
		if ( ! $expense_val ) {
			self::ajax_fail( 'A expense value is required.' );
		}

		$project = SI_Project::get_instance( $project_id );
		if ( ! is_a( $project, 'SI_Project' ) ) {
			self::ajax_fail( 'Project not found.' );
		}

		$args = array();
		$args['project_id'] = (int) $project_id;
		$args['expense_val'] = (float) si_get_number_format( (float) $expense_val );

		if ( isset( $_REQUEST['title'] ) ) {
			$args['title'] = esc_textarea( $_REQUEST['title'] );
		}

		if ( isset( $_REQUEST['note'] ) ) {
			$args['note'] = esc_textarea( $_REQUEST['note'] );
		}

		if ( isset( $_REQUEST['category_id'] ) ) {
			$args['category_id'] = (int) $_REQUEST['category_id'];
		}

		if ( isset( $_REQUEST['attachments'] ) ) {
			$args['attachments'] = $_REQUEST['attachments'];
		}

		if ( isset( $_REQUEST['date'] ) ) {
			$args['date'] = (int) strtotime( $_REQUEST['date'] );
		}
		$defaults = array(
			'project_id' => (int) $project->get_id(),
			'category_id' => (int) 0,
			'expense_val' => (float) si_get_number_format( (float) 0 ),
			'title' => '',
			'attachments' => array(),
			'note' => '',
			'date' => (int) current_time( 'timestamp' ),
			'user_id' => get_current_user_id(),
		);
		$data = wp_parse_args( $args, $defaults );
		$new_expense_id = $project->create_associated_expense( $data );
		do_action( 'si_expense_created', $new_expense_id );

		header( 'Content-type: application/json' );
		if ( self::DEBUG ) { header( 'Access-Control-Allow-Origin: *' ); }
		echo wp_json_encode( $data );
		exit();
	}

	public static function maybe_delete_expense() {
		$nonce = $_REQUEST['nonce'];
		if ( ! wp_verify_nonce( $nonce, self::SUBMISSION_NONCE ) ) {
			self::ajax_fail( 'Not going to fall for it!' ); }

		if ( isset( $_REQUEST['id'] ) ) {
			$expense_id = $_REQUEST['id'];
		}
		if ( ! $expense_id ) {
			self::ajax_fail( 'No id given.' );
		}
		if ( get_post_type( $expense_id ) != SI_Expense::POST_TYPE ) {
			self::ajax_fail( 'Not an category.' );
		}

		self::delete_expense_entry( $expense_id );

		echo true;
		exit();
	}

	public static function maybe_delete_expense_entry() {
		$nonce = $_REQUEST['nonce'];
		if ( ! wp_verify_nonce( $nonce, self::SUBMISSION_NONCE ) ) {
			self::ajax_fail( 'Not going to fall for it!' ); }

		if ( isset( $_REQUEST['project_id'] ) ) {
			$project_id = $_REQUEST['project_id'];
		}
		if ( ! $project_id ) {
			self::ajax_fail( 'No project id' );
		}
		$project = SI_Project::get_instance( $project_id );

		if ( isset( $_REQUEST['id'] ) ) {
			$expense_id = $_REQUEST['id'];
		}
		if ( ! $expense_id ) {
			self::ajax_fail( 'No id given.' );
		}
		$expense = SI_Record::get_instance( $expense_id );

		$project->remove_expense_associated( $expense_id );

		do_action( 'si_expense_deleted', $expense_id );

		echo true;
		exit();
	}

	public static function projects_expense() {
		$nonce = $_REQUEST['nonce'];
		if ( ! wp_verify_nonce( $nonce, self::SUBMISSION_NONCE ) ) {
			self::ajax_fail( 'Not going to fall for it!' ); }

		if ( isset( $_REQUEST['project_id'] ) ) {
			$project_id = $_REQUEST['project_id'];
		}
		if ( ! $project_id ) {
			self::ajax_fail( 'No project id' );
		}
		$project = SI_Project::get_instance( $project_id );
		if ( ! is_a( $project, 'SI_Project' ) ) {
			self::ajax_fail( 'Project not found' );
		}
		$expenses = $project->get_associated_expenses();

		$expense_data = array();
		if ( ! empty( $expenses ) ) {
			foreach ( $expenses as $expense_id ) {
				$expense = SI_Record::get_instance( $expense_id );
				if ( ! is_a( $expense, 'SI_Record' ) ) {
					continue;
				}
				$category = SI_Expense::get_instance( $expense->get_associate_id() );
				$data = $expense->get_data();

				// If expense is unbillable don't import
				// This includes not returning expense that has already been invoiced.
				if ( isset( $_REQUEST['billable'] ) ) {
					if ( is_a( $category, 'SI_Expense' ) && ! $category->is_billable() ) {
						continue;
					}
					// Don't return the expense that has already been invoiced
					if ( isset( $data['invoice_id'] ) ) {
						continue;
					}
				}

				$description = ( is_a( $category, 'SI_Expense' ) ) ? '<b>' . get_the_title( $category->get_id() ) . "</b>\n" . $expense->get_title() . "\n<small>" . date_i18n( get_option( 'date_format' ), $data['date'] ) . '</small>' : $expense->get_title() . "\n<small>" . date_i18n( get_option( 'date_format' ), $data['date'] ) . '</small>';
				if ( isset( $data['attachments'] ) && ! empty( $data['attachments'] ) ) {
					foreach ( $data['attachments'] as $media_id ) {
						$file = basename( get_attached_file( $media_id ) );
						$filetype = wp_check_filetype( $file );
						$icon = self::get_attachment_icon( $media_id );
						$description .= sprintf(
							'<li>
								<a href="%1$s">
									<img src="%2$s" title="%3$s" class="doc_attachment attachment_type_%4$s"></a><br/><span class="attachment_title">%3$s</span>
								</a>
							</li>',
							wp_get_attachment_url( $media_id ),
							esc_url_raw( $icon ),
							get_the_title( $media_id ),
							esc_attr( $filetype['ext'] )
						);
					}
				}

				$description = apply_filters( 'the_content', $description );

				$expense_data[] = array(
					'id' => $expense_id,
					'date' => date_i18n( get_option( 'date_format' ), $data['date'] ),
					'title' => apply_filters( 'the_content', $expense->get_title() ),
					'note' => apply_filters( 'the_content', $data['note'] ),
					'attachments' => $data['attachments'],
					'qty' => 1,
					'rate' => $data['expense_val'],
					'description' => apply_filters( 'si_project_expense_imported_description', $description ),
					'category_id' => ( is_a( $category, 'SI_Expense' ) ) ? $category->get_id() : false,
					'category' => ( is_a( $category, 'SI_Expense' ) ) ? $category->get_title() : '',
					);
			}
		}
		if ( empty( $expense_data ) ) {
			self::ajax_fail( 'Nothing to import' );
		}
		header( 'Content-type: application/json' );
		if ( self::DEBUG ) { header( 'Access-Control-Allow-Origin: *' ); }
		echo wp_json_encode( $expense_data );
		exit();

	}

	////////////////
	// AJAX View //
	////////////////

	public static function ajax_l10n( $js_object = array() ) {
		$js_object['expense_creation_modal_title'] = __( 'Create Category', 'sprout-invoices' );
		$js_object['expense_creation_modal_url'] = admin_url( 'admin-ajax.php?action=sa_expense_creation&height=300' );
		$js_object['expense_tracker_modal_title'] = __( 'Track Expense', 'sprout-invoices' );
		$js_object['expense_tracker_modal_url'] = admin_url( 'admin-ajax.php?action=sa_expense_tracker&height=450&width=600' );
		$js_object['expense_tracker_success_message'] = __( 'Expense saved!', 'sprout-invoices' );
		return $js_object;
	}

	public static function expense_admin_view() {
		if ( ! current_user_can( 'edit_sprout_invoices' ) ) {
			self::ajax_fail( 'User cannot create new posts!' );
		}

		$args = array(
			'post_type' => SI_Expense::POST_TYPE,
			'post_status' => 'any',
			'posts_per_page' => -1,
			'fields' => 'ids',
		);
		$expense_types = get_posts( $args );
		self::load_addon_view( 'admin/sections/expense-admin', array(
				'expense' => $expense_types,
		), true );
		exit();
	}

	public static function expense_creation_view() {
		if ( ! current_user_can( 'edit_sprout_invoices' ) ) {
			self::ajax_fail( 'User cannot create new posts!' );
		}

		// Expense creation
		$fields = self::expense_creation_fields();
		self::load_addon_view( 'admin/sections/expense-creation-form', array(
				'fields' => $fields,
		), true );
		exit();
	}

	/**
	 * Meta box view
	 * Abstracted to be called via AJAX
	 * @param int $project_id
	 *
	 */
	public static function expense_tracker_view( $project_id = 0 ) {
		if ( ! current_user_can( 'edit_sprout_invoices' ) ) {
			self::ajax_fail( 'User cannot create new posts!' ); }

		if ( ! $project_id && isset( $_REQUEST['project_id'] ) ) {
			$project_id = $_REQUEST['project_id'];
		}

		$fields = self::expense_entry_fields( $project_id );
		self::load_addon_view( 'admin/sections/expense-tracker', array(
				'fields' => $fields,
		), true );
		exit();
	}

	/**
	 * Meta box view
	 * Abstracted to be called via AJAX
	 * @param int $project_id
	 *
	 */
	public static function front_end_expense_tracker_view( $project_id = 0 ) {
		if ( ! current_user_can( 'edit_sprout_invoices' ) ) {
			wp_login_form();
			exit();
		}

		if ( ! $project_id && isset( $_REQUEST['project_id'] ) ) {
			$project_id = $_REQUEST['project_id'];
		}
		$fields = self::expense_entry_fields( $project_id );
		unset( $fields['category_id']['description'] );
		unset( $fields['expense_cost']['description'] );
		$fields['date']['weight'] = 25;
		uasort( $fields, array( __CLASS__, 'sort_by_weight' ) );

		// enqueue
		wp_register_script( 'si_expense_tracking', SA_ADDON_EXPENSE_TRACKING_URL . '/resources/admin/js/expense_entry.js', array( 'jquery' ), self::SI_VERSION );
		wp_enqueue_script( 'si_expense_tracking' );
		add_filter( 'si_sprout_doc_scripts_localization',  array( 'SI_Expense_Tracking_Premium', 'ajax_l10n' ) );
		wp_register_style( 'sprout_doc_style', SI_URL . '/resources/front-end/css/sprout-invoices.style.css', array( 'open-sans' ), self::SI_VERSION );
		wp_enqueue_style( 'sprout_doc_style' );

		self::load_addon_view( 'public/expense-tracker', array(
				'fields' => $fields,
		), true );
		exit();
	}

	/**
	 * Meta box view
	 * Abstracted to be called via AJAX
	 * @param int $project_id
	 *
	 */
	public static function expense_entries_table_view( $project_id = 0 ) {
		if ( ! current_user_can( 'edit_sprout_invoices' ) ) {
			self::ajax_fail( 'User cannot create new posts!' ); }

		if ( isset( $_REQUEST['project_id'] ) ) {
			$project_id = $_REQUEST['project_id'];
		}

		if ( ! $project_id ) {
			self::ajax_fail( 'No project id' );
		}

		$project = SI_Project::get_instance( $project_id );
		$expenses = $project->get_associated_expenses();
		self::load_addon_view( 'admin/meta-boxes/projects/expense-entries', array(
				'expenses' => $expenses,
				'project_id' => $project->get_id(),
		), true );
		exit();
	}

	//////////////
	// Widgets //
	//////////////

	public static function add_dashboard_widgets( $context = 'dashboard' ) {

		add_meta_box(
			'expense_tracking',
			__( 'Expense Tracking Dashboard', 'sprout-invoices' ),
			array( __CLASS__, 'expense_tracking_dashboard' ),
			$context,
			'normal',
			'high'
		);

		if ( current_user_can( 'edit_sprout_invoices' ) ) {
			add_meta_box(
				'unbilled_expense_tracking',
				__( 'Unbilled Expense Dashboard', 'sprout-invoices' ),
				array( __CLASS__, 'unbilled_expense_tracking_dashboard' ),
				$context,
				'normal',
				'high'
			);
		}

		add_meta_box(
			'expense_tracker',
			__( 'Expense Tracker', 'sprout-invoices' ),
			array( __CLASS__, 'expense_tracker_dashboard' ),
			$context,
			'normal',
			'high'
		);

	}

	public static function expense_tracking_dashboard() {
		$args = array(
			'post_type' => SI_Project::POST_TYPE,
			'post_status' => 'any',
			'posts_per_page' => 5,
			'fields' => 'ids',
			'orderby' => 'modified',
		);
		$projects = get_posts( $args );
		if ( empty( $projects ) ) {
			_e( 'No new projects.', 'sprout-invoices' );
			return;
		}
		$something_shown = false;
		foreach ( $projects as $project_id ) {
			$project = SI_Project::get_instance( $project_id );
			$expenses = $project->get_associated_expenses();
			if ( empty( $expenses ) ) {
				continue;
			}
			self::load_addon_view( 'admin/dashboards/project-expense-tracking', array(
				'project' => $project,
				'expenses' => $expenses,
			), true );
			$something_shown = true;
		}
		if ( ! $something_shown ) {
			_e( 'No expense tracked from your most recent projects.', 'sprout-invoices' );
		}
	}

	public static function unbilled_expense_tracking_dashboard() {
		$args = array(
			'post_type' => SI_Project::POST_TYPE,
			'post_status' => 'any',
			'posts_per_page' => 3,
			'fields' => 'ids',
			'orderby' => 'modified',
		);
		$projects = get_posts( $args );
		if ( empty( $projects ) ) {
			_e( 'No new projects.', 'sprout-invoices' );
			return;
		}
		$something_shown = false;
		foreach ( $projects as $project_id ) {
			$project = SI_Project::get_instance( $project_id );
			$expenses = $project->get_associated_expenses();
			if ( empty( $expenses ) ) {
				continue;
			}
			self::load_addon_view( 'admin/dashboards/project-expense-tracking-unbilled', array(
				'project' => $project,
				'expenses' => $expenses,
			), true );
			$something_shown = true;
		}
		if ( ! $something_shown ) {
			_e( 'No unbilled expense from your most recent projects.', 'sprout-invoices' );
		}
	}

	public static function expense_tracker_dashboard() {
		$fields = self::expense_entry_fields( 0 );
		if ( ! current_user_can( 'edit_sprout_invoices' ) ) {
			unset( $fields['category_id']['description'] );
		}
		self::load_addon_view( 'admin/dashboards/expense-tracker', array(
				'fields' => $fields,
		), true );
	}


	////////////////////
	// Admin Columns //
	////////////////////

	/**
	 * Overload the columns for the invoice post type admin
	 *
	 * @param array   $columns
	 * @return array
	 */
	public static function register_columns( $columns ) {
		$columns['expenses'] = __( 'Expenses', 'sprout-invoices' );
		return $columns;
	}

	/**
	 * Display the content for the column
	 *
	 * @param string  $column_name
	 * @param int     $id          post_id
	 * @return string
	 */
	public static function column_display( $column_name, $id ) {
		$project = SI_Project::get_instance( $id );

		if ( ! is_a( $project, 'SI_Project' ) ) {
			return; // return for that temp post
		}
		switch ( $column_name ) {

			case 'expenses':
				$total_expenses = si_get_project_expenses_total( $id );
				if ( 0.00 < $total_expenses ) {
					printf( '<p><b>%s</b>: %s</p>', __( 'Total Billed', 'sprout-invoices' ), sa_get_formatted_money( si_get_project_expenses_total_billed( $id ) ) );

					printf( '<p><b>%s</b>: %s</p>', __( 'Total Not Invoiced', 'sprout-invoices' ), sa_get_formatted_money( si_get_project_expenses_total_not_invoiced( $id ) ) );
				}

				printf( '<p><b>%s</b>: %s</p>', __( 'Total Expenses', 'sprout-invoices' ), sa_get_formatted_money( $total_expenses ) );

			break;

			default:
				// code...
			break;
		}

	}

	//////////////
	// Utility //
	//////////////


	public static function delete_expense_entry( $expense_id = 0 ) {
		// records are normally deleted if their parent is deleted
		remove_action( 'deleted_post', array( 'SI_Internal_Records', 'attempt_associated_record_deletion' ) );
		// by removing this action the expense entries will be newly associated
		// with the default category instead via
		add_action( 'deleted_post', array( __CLASS__, 'attempt_reassign_entries_to_default' ) );

		wp_delete_post( $expense_id, true );
	}

	public static function attempt_reassign_entries_to_default( $post_id = 0 ) {
		// prevent looping and checking if a record has a record associated with it.
		if ( get_post_type( $post_id ) !== SI_Record::POST_TYPE ) {
			global $wpdb;
			$parent_update = array( 'post_parent' => SI_Expense::default_expense() );
			$parent_where = array( 'post_parent' => $post_id, 'post_type' => SI_Record::POST_TYPE );
			$wpdb->update( $wpdb->posts, $parent_update, $parent_where );
		}
	}

	public static function load_addon_view( $view, $args, $allow_theme_override = true ) {
		add_filter( 'si_views_path', array( __CLASS__, 'addons_view_path' ) );
		$view = self::load_view( $view, $args, $allow_theme_override );
		remove_filter( 'si_views_path', array( __CLASS__, 'addons_view_path' ) );
		return $view;
	}

	public static function addons_view_path() {
		return SA_ADDON_EXPENSE_TRACKING_PATH . '/views/';
	}


	public static function get_attachment_icon( $media_id = 0 ) {
		$file = basename( get_attached_file( $media_id ) );
		$filetype = wp_check_filetype( $file );
		switch ( $filetype['ext'] ) {
			case 'pdf':
				$img = SA_ADDON_EXPENSE_TRACKING_URL . '/resources/icons/pdf.png';
				break;
			case 'zip':
				$img = SA_ADDON_EXPENSE_TRACKING_URL . '/resources/icons/zip.png';
				break;
			case 'rar':
				$img = SA_ADDON_EXPENSE_TRACKING_URL . '/resources/icons/rar.png';
				break;
			case 'mp3':
			case 'swa':
			case 'wav':
			case 'wma':
			case 'm4a':
				$img = SA_ADDON_EXPENSE_TRACKING_URL . '/resources/icons/audio.png';
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
				$img = SA_ADDON_EXPENSE_TRACKING_URL . '/resources/icons/video.png';
				break;
			case 'csv':
			case 'xls':
				$img = SA_ADDON_EXPENSE_TRACKING_URL . '/resources/icons/csv.png';
				break;
			case 'ppt':
				$img = SA_ADDON_EXPENSE_TRACKING_URL . '/resources/icons/csv.png';
				break;
			case 'bmp':
			case 'gif':
			case 'jpeg':
			case 'jpg':
			case 'png':
			case 'tiff':
				$img = SA_ADDON_EXPENSE_TRACKING_URL . '/resources/icons/image.png';
				break;

			default:
				$img = SA_ADDON_EXPENSE_TRACKING_URL . '/resources/icons/default.png';
				break;
		}
		return $img;

	}
}
