<?php

/**
 * Time Model
 * A SI_Time is referred to an activity in many places. 
 * A time (entry/clocked) is an SI_Record associated with an SI_Time,
 * this will allow for reports and more with the separation.
 * 
 *
 * @package Sprout_Invoices
 * @subpackage Time
 */
class SI_Time extends SI_Post_Type {
	const POST_TYPE = 'sa_time';
	const REWRITE_SLUG = 'sprout-time'; // unused
	const TIME_RECORD = 'si_time_record';
	private static $instances = array();

	private static $meta_keys = array(
		'billable' => '_billable',
		'default_rate' => '_default_rate',
		'default_percentage' => '_default_percentage', // discount or tax
		'user_id' => '_user_id',
	); // A list of meta keys this class cares about. Try to keep them in alphabetical order.


	public static function init() {
		// register Time post type
		$post_type_args = array(
			'public' => false,
			'has_archive' => false,
			'show_ui' => false,
			'supports' => array( '' )
		);
		self::register_post_type( self::POST_TYPE, 'Time', 'Time Type', $post_type_args );

		add_action( 'si_plugin_activation_hook', array( __CLASS__, 'default_time' ) );
	}

	protected function __construct( $id ) {
		parent::__construct( $id );
	}

	/**
	 *
	 *
	 * @static
	 * @param int     $id
	 * @return Sprout_Invoices_Time
	 */
	public static function get_instance( $id = 0 ) {
		if ( !$id )
			return null;
		
		if ( get_post_type( $id ) != self::POST_TYPE ) {
			$id = self::default_time();
		}
		if ( !isset( self::$instances[$id] ) || !self::$instances[$id] instanceof self )
			self::$instances[$id] = new self( $id );

		if ( !isset( self::$instances[$id]->post->post_type ) )
			return null;
		
		if ( self::$instances[$id]->post->post_type != self::POST_TYPE )
			return null;
		
		return self::$instances[$id];
	}

	/**
	 * Create a time activity
	 * @param  array $args 
	 * @return int       
	 */
	public static function new_activity( $args = array() ) {
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
			'post_title' => $parsed_args['name']
		) );

		if ( is_wp_error( $id ) ) {
			return 0;
		}

		$time = self::get_instance( $id );
		$time->set_billable( $parsed_args['billable'] );
		$time->set_default_rate( $parsed_args['rate'] );
		$time->set_default_percentage( $parsed_args['percentage'] );

		do_action( 'sa_new_time', $time, $parsed_args );
		return $id;
	}

	public static function default_time() {
		$default_id = get_option( 'si_default_time' );
		if ( $default_id && self::POST_TYPE !== get_post_type( $default_id ) ) {
			$default_id = 0;
		}
		if ( ! $default_id ) {
			$activities = self::get_activities();
			if ( ! empty( $activities ) ) {
				reset( $activities );
				$default_id = key( $activities );
			}
			if ( self::POST_TYPE !== get_post_type( $default_id ) ) {
				$default_id = self::new_activity();
			}
			update_option( 'si_default_time', $default_id );
		}
		return $default_id;
	}

	///////////
	// Meta //
	///////////

	/**
	 * Create a time entry
	 * @param  array $args 
	 * @return int       
	 */
	public function new_time( $data ) {
		$id = SI_Internal_Records::new_record(
			$data,
			self::TIME_RECORD,
			$this->get_id(),
			$data['note'],
			0 );
		return $id;
	}

	public function delete_time( $time_id = 0 ) {
		$time = SI_Record::get_instance( $time_id );
		if ( is_a( $time, 'SI_Record' ) && $time->get_associate_id() == $this->get_id() ) {
			wp_delete_post( $time_id, true );
		}
	}

	public function get_time_entries( $args = array() ) {
		return SI_Record::get_records_by_association( $this->ID );
	}

	public static function get_time_entry( $time_id = 0 ) {
		$record = SI_Record::get_instance( $time_id );
		if ( ! is_a( $record, 'SI_Record' ) ) {
			$record = 0;
		}
		return $record;
	}

	public static function add_invoice_id( $time_id = 0, $invoice_id = 0 ) {
		$record = SI_Record::get_instance( $time_id );
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


	public function get_default_rate() {
		return $this->get_post_meta( self::$meta_keys['default_rate'] );
	}

	public function set_default_rate( $default_rate ) {
		return $this->save_post_meta( array( self::$meta_keys['default_rate'] => $default_rate ) );
	}


	public function get_default_percentage() {
		return $this->get_post_meta( self::$meta_keys['default_percentage'] );
	}

	public function set_default_percentage( $default_percentage ) {
		return $this->save_post_meta( array( self::$meta_keys['default_percentage'] => $default_percentage ) );
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
		if ( $this->is_billable() && $this->get_default_rate() ) {
			$title .= ': ' . sa_get_formatted_money( $this->get_default_rate() );
		}
		if ( $this->is_billable() && $this->get_default_percentage() ) {
			$title .= ' (' . $this->get_default_percentage() . '%)';
		}
		return apply_filters( 'si_get_time_title', $title, $this );
	}

	public static function get_activities( $time_types = array() ) {
		if ( empty( $time_types ) ) {
			$args = array(
				'post_type' => self::POST_TYPE,
				'post_status' => 'any',
				'posts_per_page' => -1,
				'fields' => 'ids',
			);
			$time_types = get_posts( $args );
		}
		$time_types_options = array();
		foreach ( $time_types as $time_id ) {
			$time = SI_Time::get_instance( $time_id );
			if ( is_a( $time, 'SI_Time' ) ) {
				$time_types_options[ $time_id ] = $time->get_title();
			}
		}
		return $time_types_options;
	}
}