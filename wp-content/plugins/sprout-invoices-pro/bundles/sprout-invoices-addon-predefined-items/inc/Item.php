<?php

/**
 * Item Model
 *
 *
 * @package Sprout_Invoices
 * @subpackage Item
 */
class SI_Item extends SI_Post_Type {
	const POST_TYPE = 'sa_item';
	const DEP_LINE_ITEM_TAXONOMY = 'si_line_item_types'; // deprecated tax
	const UPDATED = 'si_line_items_migrated_v4'; // deprecated tax
	private static $instances = array();

	private static $meta_keys = array(
		'product' => '_product',
		'type' => '_type',
		'default_sku' => '_sku',
		'default_qty' => '_default_qty',
		'default_rate' => '_default_rate',
		'default_percentage' => '_default_percentage', // discount or tax
	); // A list of meta keys this class cares about. Try to keep them in alphabetical order.


	public static function init() {
		// register Item post type
		$post_type_args = array(
			'public' => false,
			'has_archive' => false,
			'show_ui' => false,
			'supports' => array( '' ),
		);
		self::register_post_type( self::POST_TYPE, 'Item', 'Item Type', $post_type_args );

		add_action( 'admin_init', array( __CLASS__, 'migrate_terms_to_items' ) );

		add_action( 'si_plugin_activation_hook', array( __CLASS__, 'default_item' ) );
	}

	protected function __construct( $id ) {
		parent::__construct( $id );
	}

	/**
	 *
	 *
	 * @static
	 * @param int     $id
	 * @return Sprout_Invoices_Item
	 */
	public static function get_instance( $id = 0 ) {
		if ( ! $id ) {
			return null; }

		if ( get_post_type( $id ) != self::POST_TYPE ) {
			$id = self::default_item();
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
	 * Create a item item
	 * @param  array $args
	 * @return int
	 */
	public static function new_item( $args = array() ) {
		$defaults = array(
			'name' => __( 'Pre-defined Example', 'sprout-invoices' ),
			'description' => __( "Amazing Spider-Man is the cornerstone of the Marvel Universe. This is where you'll find all the big-time action, major storylines and iconic Spider-Man magic you'd come to expect from the Wall-Crawler.", 'sprout-invoices' ),
			'product' => false,
			'qty' => 1,
			'rate' => 100,
			'sku' => '',
			'type' => SI_Line_Items::get_default_type(),
			'percentage' => 0,
		);
		$parsed_args = wp_parse_args( $args, $defaults );

		$id = wp_insert_post( array(
			'post_status' => 'publish',
			'post_type' => self::POST_TYPE,
			'post_title' => $parsed_args['name'],
			'post_content' => $parsed_args['description'],
		) );

		if ( is_wp_error( $id ) ) {
			return 0;
		}

		$item = self::get_instance( $id );
		$item->set_product( $parsed_args['product'] );
		$item->set_type( $parsed_args['type'] );
		$item->set_default_rate( $parsed_args['rate'] );
		$item->set_default_qty( $parsed_args['qty'] );
		$item->set_default_sku( $parsed_args['sku'] );
		$item->set_default_percentage( $parsed_args['percentage'] );

		do_action( 'sa_new_item', $item, $parsed_args );
		return $id;
	}

	public static function default_item() {
		$default_id = get_option( 'si_default_item' );
		if ( ! $default_id || get_post_type( $default_id ) !== self::POST_TYPE ) {
			$default_id = self::new_item();
			update_option( 'si_default_item', $default_id );
		}
		return $default_id;
	}

	///////////
	// Meta //
	///////////


	public function is_product() {
		return (bool) $this->get_post_meta( self::$meta_keys['product'] );
	}

	public function set_product( $product ) {
		return $this->save_post_meta( array( self::$meta_keys['product'] => $product ) );
	}


	public function get_type() {
		$type = $this->get_post_meta( self::$meta_keys['type'] );
		if ( '' === $type ) {
			$type = SI_Line_Items::get_default_type();
		}
		return $type;
	}

	public function set_type( $default_type ) {
		return $this->save_post_meta( array( self::$meta_keys['type'] => $default_type ) );
	}


	public function get_default_qty() {
		return $this->get_post_meta( self::$meta_keys['default_qty'] );
	}

	public function set_default_qty( $default_qty ) {
		return $this->save_post_meta( array( self::$meta_keys['default_qty'] => $default_qty ) );
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


	public function get_default_sku() {
		return $this->get_post_meta( self::$meta_keys['default_sku'] );
	}

	public function set_default_sku( $default_sku ) {
		return $this->save_post_meta( array( self::$meta_keys['default_sku'] => $default_sku ) );
	}

	//////////////
	// Utility //
	//////////////


	public function get_title() {
		$title = get_the_title( $this->get_id() );
		return $title;
	}

	//////////////
	// Migrate //
	//////////////

	public static function migrate_terms_to_items() {
		$update = get_option( self::UPDATED, false );

		if ( $update ) {
			return;
		}

		// Get all the terms
		$item_types = get_terms( array( SI_Estimate::LINE_ITEM_TAXONOMY ), array( 'hide_empty' => false, 'fields' => 'all' ) );

		// convert to item PT
		foreach ( $item_types as $item_type ) {
			$args = array(
				'name' => $item_type->name,
				'description' => $item_type->description,
				'product' => false,
				'qty' => 1,
				'rate' => 100,
				'percentage' => 0,
			);
			SI_Item::new_item( $args );

			wp_delete_term( $item_type->term_id, SI_Estimate::LINE_ITEM_TAXONOMY );
		}

		update_option( self::UPDATED, time() );
	}
}
