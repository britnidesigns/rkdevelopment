<?php

/**
 * Expense Model
 * A SI_Expense is referred to an category in many places.
 * A expense (entry/expensed) is an SI_Record associated with an SI_Expense,
 * this will allow for reports and more with the separation.
 *
 *
 * @package Sprout_Invoices
 * @subpackage Expense
 */
class SI_Expense extends SI_Post_Type {
	const POST_TYPE = 'sa_expense';
	const REWRITE_SLUG = 'sprout-expense'; // unused
	const EXPENSE_RECORD = 'si_expense_record';
	private static $instances = array();

	private static $meta_keys = array(
		'billable' => '_billable',
		'user_id' => '_user_id',
	); // A list of meta keys this class cares about. Try to keep them in alphabetical order.


	public static function init() {
		// register Expense post type
		$post_type_args = array(
			'public' => false,
			'has_archive' => false,
			'show_ui' => false,
			'supports' => array( '' ),
		);
		self::register_post_type( self::POST_TYPE, 'Expense', 'Expense Type', $post_type_args );

		add_action( 'si_plugin_activation_hook', array( __CLASS__, 'default_expense' ) );
	}

	protected function __construct( $id ) {
		parent::__construct( $id );
	}

	/**
	 *
	 *
	 * @static
	 * @param int     $id
	 * @return Sprout_Invoices_Expense
	 */
	public static function get_instance( $id = 0 ) {
		if ( ! $id ) {
			return null; }

		if ( get_post_type( $id ) != self::POST_TYPE ) {
			$id = self::default_expense();
		}
		if ( ! isset( self::$instances[ $id ] ) || ! self::$instances[ $id ] instanceof self ) {
			self::$instances[ $id ] = new self( $id ); }

		if ( ! isset( self::$instances[ $id ]->post->post_type ) ) {
			return null; }

		if ( self::$instances[ $id ]->post->post_type != self::POST_TYPE ) {
			return null; }

		return self::$instances[ $id ];
	}

	/**
	 * Create a expense category
	 * @param  array $args
	 * @return int
	 */
	public static function new_category( $args = array() ) {
		$defaults = array(
			'name' => __( 'Billable Work', 'sprout-invoices' ),
			'billable' => true,
			'rate' => 100,
			'percentage' => 0,
		);
		$parsed_args = wp_parse_args( $args, $defaults );

		$id = wp_insert_post( array(
			'post_status' => 'publish',
			'post_type' => self::POST_TYPE,
			'post_title' => $parsed_args['name'],
		) );

		if ( is_wp_error( $id ) ) {
			return 0;
		}

		$expense = self::get_instance( $id );
		$expense->set_billable( $parsed_args['billable'] );

		do_action( 'sa_new_expense', $expense, $parsed_args );
		return $id;
	}

	public static function default_expense() {
		$default_id = get_option( 'si_default_expense' );
		if ( $default_id && self::POST_TYPE !== get_post_type( $default_id ) ) {
			$default_id = 0;
		}
		if ( ! $default_id ) {
			$categories = self::get_categories();
			if ( ! empty( $categories ) ) {
				reset( $categories );
				$default_id = key( $categories );
			}
			if ( self::POST_TYPE !== get_post_type( $default_id ) ) {
				$default_id = self::new_category();
			}
			update_option( 'si_default_expense', $default_id );
		}
		return $default_id;
	}

	/**
	 * Create a expense entry
	 * @param  array $args
	 * @return int
	 */
	public function new_expense( $data ) {
		$id = SI_Internal_Records::new_record(
			$data,
			self::EXPENSE_RECORD,
			$this->get_id(),
			$data['title'],
		0 );
		return $id;
	}

	public function delete_expense( $expense_id = 0 ) {
		$expense = SI_Record::get_instance( $expense_id );
		if ( is_a( $expense, 'SI_Record' ) && $expense->get_associate_id() == $this->get_id() ) {
			wp_delete_post( $expense_id, true );
		}
	}

	public function get_expense_entries( $args = array() ) {
		return SI_Record::get_records_by_association( $this->ID );
	}

	public static function get_expense_entry( $expense_id = 0 ) {
		$record = SI_Record::get_instance( $expense_id );
		if ( ! is_a( $record, 'SI_Record' ) ) {
			$record = 0;
		}
		return $record;
	}

	///////////
	// Meta //
	///////////

	public static function add_invoice_id( $expense_id = 0, $invoice_id = 0 ) {
		$record = SI_Record::get_instance( $expense_id );
		if ( ! is_a( $record, 'SI_Record' ) ) {
			return 0;
		}
		$data = $record->get_data();
		$data['invoice_id'] = $invoice_id;
		$record->set_data( $data );
		return $record;
	}


	public function is_billable() {
		return (bool) $this->get_post_meta( self::$meta_keys['billable'] );
	}

	public function set_billable( $billable ) {
		return $this->save_post_meta( array( self::$meta_keys['billable'] => $billable ) );
	}


	public function get_user_id() {
		return $this->get_post_meta( self::$meta_keys['user_id'] );
	}

	public function set_user_id( $user_id ) {
		return $this->save_post_meta( array( self::$meta_keys['user_id'] => $user_id ) );
	}

	//////////////
	// Utility //
	//////////////

	public function get_title() {
		$title = get_the_title( $this->get_id() );
		return apply_filters( 'si_get_expense_title', $title, $this );
	}

	public static function get_categories( $expense_types = array() ) {
		if ( empty( $expense_types ) ) {
			$args = array(
				'post_type' => self::POST_TYPE,
				'post_status' => 'any',
				'posts_per_page' => -1,
				'fields' => 'ids',
			);
			$expense_types = get_posts( $args );
		}
		$expense_types_options = array();
		foreach ( $expense_types as $expense_id ) {
			$expense = SI_Expense::get_instance( $expense_id );
			if ( is_a( $expense, 'SI_Expense' ) ) {
				$expense_types_options[ $expense_id ] = $expense->get_title();
			}
		}
		return $expense_types_options;
	}
}
