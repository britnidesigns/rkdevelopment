<?php

/**
 * Controller
 * Adds meta boxes to client admin.
 */
class SI_Client_Type_Color extends SI_Client_Categories {
	const TERM_META = 'si_client_type_color';

	public static function init() {
		if ( is_admin() ) {
			register_meta( 'term', self::TERM_META, array( __CLASS__, 'sanitize_hex' ) );

			add_action( 'admin_enqueue_scripts', array( __CLASS__, 'admin_enqueue_scripts' ) );
			add_action( 'admin_head', array( __CLASS__, 'term_colors_print_styles' ) );

			add_action( self::TAXONOMY . '_add_form_fields', array( __CLASS__, 'add_number_tag_value' ), 10, 2 );
			add_action( self::TAXONOMY . '_edit_form_fields', array( __CLASS__, 'edit_number_tag_value' ), 10, 2 );
			add_action( 'created_' . self::TAXONOMY, array( __CLASS__, 'save_tag_value' ), 10, 2 );
			add_action( 'edited_' . self::TAXONOMY, array( __CLASS__, 'update_tag_value' ), 10, 2 );

			// Admin columns
			add_filter( 'si_client_columns', array( __CLASS__, 'register_columns' ) );
			add_filter( 'manage_'.SI_Client::POST_TYPE.'_posts_custom_column', array( __CLASS__, 'column_display' ), 10, 2 );
		}
	}

	public static function admin_enqueue_scripts( $hook_suffix ) {
		if ( 'edit-tags.php' !== $hook_suffix && self::TAXONOMY !== get_current_screen()->taxonomy ) {
			return;
		}
		wp_enqueue_style( 'wp-color-picker' );
		wp_enqueue_script( 'wp-color-picker' );

		add_action( 'admin_footer', array( __CLASS__, 'term_colors_print_scripts' ) );

	}

	public static function term_colors_print_styles() {
		?>
			<style type="text/css">
				.column-type { width: 30px; }
        		.column-type .color-block { display: inline-block; width: 28px; height: 28px; border: 1px solid #ddd; }
			</style>
		<?php
	}

	public static function term_colors_print_scripts() {
		?>
			<script type="text/javascript">
				jQuery( document ).ready( function( $ ) {
		            $( '.si-color-field' ).wpColorPicker();
		        } );
			</script>
		<?php
	}

	public static function sanitize_hex( $color ) {
	    $color = ltrim( $color, '#' );
	    return preg_match( '/([A-Fa-f0-9]{3}){1,2}$/', $color ) ? $color : '';
	}

	public static function add_number_tag_value() {
		?>
			<div class="form-field">
				<label for="si_client_type_color"><?php _e( 'Type Color', 'sprout-invoices' ); ?></label>
				<input type="text" name="si_client_type_color" id="si_client_type_color" class="si-color-field" value="" data-default-color="#ffffff">
				<p class="description"><?php _e( 'Shows a color next to your clients within the admin.', 'sprout-invoices' ); ?></p>
			</div>
		<?php
	}

	public static function edit_number_tag_value( $term, $taxonomy ) {
		$value = self::get_term_color( $term->term_id, true );
		?>
			<tr class="form-field term-group-wrap">
		        <th scope="row"><label for="si_client_type_color"><?php _e( 'Type Color', 'sprout-invoices' ); ?></label></th>
		        <td>
		        	<input type="text" name="si_client_type_color" id="si_client_type_color" class="si-color-field"  value="<?php echo esc_attr( $value ) ?>" data-default-color="#ffffff">
					<p class="description"><?php _e( 'Shows a color next to your clients within the admin.', 'sprout-invoices' ); ?></p>
		        </td>
		    </tr>
		<?php
	}

	public static function get_term_color( $term_id, $hash = false ) {
	    $color = get_term_meta( $term_id, self::TERM_META, true );
	    $color = self::sanitize_hex( $color );
	    return ( $hash && $color ) ? "#{$color}" : $color ;
	}

	public static function save_tag_value( $term_id = 0, $tt_id = 0 ) {
		if ( isset( $_POST['si_client_type_color'] ) && '' !== $_POST['si_client_type_color'] ) {
			$value = self::sanitize_hex( $_POST['si_client_type_color'] );
			add_term_meta( $term_id, self::TERM_META, $value, true );
		}
	}

	public static function get_client_type_color( $client_id = 0 ) {
		$term_id = self::get_client_type_id( $client_id );
		$meta = get_term_meta( $term_id, self::TERM_META, true );
		$color = self::sanitize_hex( $meta );
		return $color;
	}

	public static function update_tag_value( $term_id = 0, $tt_id = 0 ) {
		$old_color = self::get_term_color( $term_id );
		$new_color = isset( $_POST['si_client_type_color'] ) ? self::sanitize_hex( $_POST['si_client_type_color'] ) : '';
		if ( $old_color && '' === $new_color ) {
			delete_term_meta( $term_id, self::TERM_META );
		} elseif ( $old_color !== $new_color ) {
			update_term_meta( $term_id, self::TERM_META, $new_color );
		}
	}


	/**
	 * Overload the columns for the invoice post type admin
	 *
	 * @param array   $columns
	 * @return array
	 */
	public static function register_columns( $columns ) {
		foreach ( $columns as $key => $label ) {
			if ( isset( $new_columns['cb'] ) && ! isset( $new_columns['type'] ) ) {
				$new_columns['type'] = '';
			}
			$new_columns[ $key ] = $label;
		}
		return $new_columns;
	}

	/**
	 * Display the content for the column
	 *
	 * @param string  $column_name
	 * @param int     $id          post_id
	 * @return string
	 */
	public static function column_display( $column_name, $id ) {
		$client = SI_Client::get_instance( $id );
		if ( ! is_a( $client, 'SI_Client' ) ) {
			return; // return for that temp post
		}
		switch ( $column_name ) {

			case 'type':
				$color = self::get_client_type_color( $id );
				printf( '<span class="color-block" style="background:#%s;">&nbsp;</span>', esc_attr( $color ) );
			break;

			default:
				// code...
			break;
		}

	}
}
SI_Client_Type_Color::init();
