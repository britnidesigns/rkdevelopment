<?php

/**
 * Controller
 * Adds meta boxes to client admin.
 */
class SI_Client_Type_Numbering extends SI_Client_Categories {
	const TERM_META = 'si_numbering_tag_value';
	const TERM_META_SEQ = 'si_numbering_sequence_value';
	const TERM_META_SEQ_PAD = 'si_numbering_sequence_value_pad';

	public static function init() {

		add_action( self::TAXONOMY . '_add_form_fields', array( __CLASS__, 'add_number_tag_value' ), 10, 2 );
		add_action( self::TAXONOMY . '_edit_form_fields', array( __CLASS__, 'edit_number_tag_value' ), 10, 2 );
		add_action( 'created_' . self::TAXONOMY, array( __CLASS__, 'save_tag_value' ), 10, 2 );
		add_action( 'edited_' . self::TAXONOMY, array( __CLASS__, 'update_tag_value' ), 10, 2 );

		add_filter( 'si_advanced_numbering_formatting_tags', array( __CLASS__, 'add_client_type_tag' ), 10, 1 );

		add_filter( 'load_view_args_admin/meta-boxes/invoices/information.php', array( __CLASS__, '_si_information_meta_box_args' ) );
		add_filter( 'load_view_args_admin/meta-boxes/estimates/information.php', array( __CLASS__, '_si_information_meta_box_args' ) );

		add_filter( 'the_title', array( __CLASS__, 'filter_title' ), 300, 2 );

		add_action( 'updated_postmeta', array( __CLASS__, 'update_id_on_post_meta_save' ), 200, 4 );
	}

	public static function add_number_tag_value() {
		?>
			<div class="form-field">
				<label for="si_<?php echo self::TERM_META ?>"><?php _e( 'Numbering Format Tag', 'sprout-invoices' ); ?></label>
				<input type="text" name="si_<?php echo self::TERM_META ?>" id="si_<?php echo self::TERM_META ?>" value="">
				<p class="description"><?php _e( 'If {client_type} is used within the numbering format, this value will be used.', 'sprout-invoices' ); ?></p>
			</div>
			<div class="form-field">
				<label for="si_<?php echo self::TERM_META_SEQ ?>"><?php _e( 'Sequence Format Tag', 'sprout-invoices' ); ?></label>
				<input type="text" name="si_<?php echo self::TERM_META_SEQ ?>" id="si_<?php echo self::TERM_META_SEQ ?>" value="">
				<p class="description"><?php _e( 'If {client_type_seq} is used within the numbering format, this value will be used and increment.', 'sprout-invoices' ); ?></p>
			</div>
			<div class="form-field">
				<label for="si_<?php echo self::TERM_META_SEQ_PAD ?>"><?php _e( 'Sequence Padding', 'sprout-invoices' ); ?></label>
				<input type="text" name="si_<?php echo self::TERM_META_SEQ_PAD ?>" id="si_<?php echo self::TERM_META_SEQ_PAD ?>" value="">
				<p class="description"><?php _e( 'The sequence length. So that the {client_type_seq} could be 00000123 and not lead to 123, this example uses a length of 8.', 'sprout-invoices' ); ?></p>
			</div>
		<?php
	}

	public static function edit_number_tag_value( $term, $taxonomy ) {
		$value = get_term_meta( $term->term_id, self::TERM_META, true );
		$seq = get_term_meta( $term->term_id, self::TERM_META_SEQ, true );
		$seq_pad = get_term_meta( $term->term_id, self::TERM_META_SEQ_PAD, true );
		?>
			<tr class="form-field term-group-wrap">
		        <th scope="row"><label for="si_<?php echo self::TERM_META ?>"><?php _e( 'Numbering Format Tag', 'sprout-invoices' ); ?></label></th>
		        <td>
		        	<input type="text" name="si_<?php echo self::TERM_META ?>" id="si_<?php echo self::TERM_META ?>" value="<?php echo $value ?>">
					<p class="description"><?php _e( 'If {client_type} is used within the numbering format, this value will be used.', 'sprout-invoices' ); ?></p>
		        </td>
		    </tr>
			<tr class="form-field term-group-wrap">
		        <th scope="row"><label for="si_<?php echo self::TERM_META_SEQ ?>"><?php _e( 'Sequence Format Tag', 'sprout-invoices' ); ?></label></th>
		        <td>
		        	<input type="text" name="si_<?php echo self::TERM_META_SEQ ?>" id="si_<?php echo self::TERM_META_SEQ ?>" value="<?php echo $seq ?>">
					<p class="description"><?php _e( 'If {client_type_seq} is used within the numbering format, this value will be used and increment.', 'sprout-invoices' ); ?></p>
		        </td>
		    </tr>
			<tr class="form-field term-group-wrap">
		        <th scope="row"><label for="si_<?php echo self::TERM_META_SEQ_PAD ?>"><?php _e( 'Sequence Padding', 'sprout-invoices' ); ?></label></th>
		        <td>
		        	<input type="text" name="si_<?php echo self::TERM_META_SEQ_PAD ?>" id="si_<?php echo self::TERM_META_SEQ_PAD ?>" value="<?php echo $seq_pad ?>">
					<p class="description"><?php _e( 'The sequence length. So that the {client_type_seq} could be 00000123 and not lead to 123, this example uses a length of 8.', 'sprout-invoices' ); ?></p>
		        </td>
		    </tr>
		<?php
	}

	public static function save_tag_value( $term_id = 0, $tt_id = 0 ) {
		if ( isset( $_POST[ 'si_' . self::TERM_META ] ) && '' !== $_POST[ 'si_' . self::TERM_META ] ) {
			$value = wp_unslash( sanitize_title( $_POST[ 'si_' . self::TERM_META ] ) );
			add_term_meta( $term_id, self::TERM_META, $value, true );
		}
		if ( isset( $_POST[ 'si_' . self::TERM_META_SEQ ] ) && '' !== $_POST[ 'si_' . self::TERM_META_SEQ ] ) {
			$value = wp_unslash( (int) $_POST[ 'si_' . self::TERM_META_SEQ ] );
			add_term_meta( $term_id, self::TERM_META_SEQ, $value, true );
		}
		if ( isset( $_POST[ 'si_' . self::TERM_META_SEQ_PAD ] ) && '' !== $_POST[ 'si_' . self::TERM_META_SEQ_PAD ] ) {
			$value = wp_unslash( (int) $_POST[ 'si_' . self::TERM_META_SEQ_PAD ] );
			add_term_meta( $term_id, self::TERM_META_SEQ_PAD, $value, true );
		}
	}

	public static function update_tag_value( $term_id = 0, $tt_id = 0 ) {
		if ( isset( $_POST[ 'si_' . self::TERM_META  ] ) && '' !== $_POST[ 'si_' . self::TERM_META ] ) {
			$value = wp_unslash( sanitize_title( $_POST[ 'si_' . self::TERM_META ] ) );
			update_term_meta( $term_id, self::TERM_META, $value );
		}
		if ( isset( $_POST[ 'si_' . self::TERM_META_SEQ  ] ) && '' !== $_POST[ 'si_' . self::TERM_META_SEQ ] ) {
			$value = wp_unslash( (int) $_POST[ 'si_' . self::TERM_META_SEQ ] );
			update_term_meta( $term_id, self::TERM_META_SEQ, $value );
		}
		if ( isset( $_POST[ 'si_' . self::TERM_META_SEQ_PAD  ] ) && '' !== $_POST[ 'si_' . self::TERM_META_SEQ_PAD ] ) {
			$value = wp_unslash( (int) $_POST[ 'si_' . self::TERM_META_SEQ_PAD ] );
			update_term_meta( $term_id, self::TERM_META_SEQ_PAD, $value );
		}
	}

	public static function add_client_type_tag( $tags = array() ) {
		$tags['client_type'] = __( 'Shows the value of the client type.', 'sprout-invoices' );
		$tags['client_type_seq'] = __( 'Shows the next integer within the client type sequence.', 'sprout-invoices' );
		return $tags;
	}

	public static function _si_information_meta_box_args( $args = '' ) {
		if ( 'auto-draft' === $args['post']->post_status ) { // only adjust drafts
			return $args;
		}
		$doc = si_get_doc_object( $args['post']->ID );
		if ( SI_Invoice::POST_TYPE === $args['post']->post_type ) {
				$args['invoice_id'] = self::filter_invoice_id( $args['invoice_id'], $args['post']->ID );
				$doc->set_invoice_id( $args['invoice_id'] );
		}
		if ( SI_Estimate::POST_TYPE === $args['post']->post_type ) {
			$args['estimate_id'] = self::filter_estimate_id( $args['estimate_id'], $args['post']->ID );
			$doc->set_estimate_id( $args['estimate_id'] );
		}
		return $args;
	}

	public static function update_id_on_post_meta_save( $meta_id, $post_id, $meta_key, $meta_value ) {

		if ( ! in_array( $meta_key, array( '_invoice_id', '_estimate_id', '_client_id' ) ) ) {
			return;
		}

		$doc = si_get_doc_object( $post_id );
		if ( '' === $doc ) {
			return;
		}

		$client_id = $doc->get_client_id();
		if ( ! $client_id ) {
			return;
		}

		$id = $meta_value;
		if ( $meta_key === '_client_id' ) {
			$id = ( is_a( $doc, 'SI_Invoice' ) ) ? $doc->get_invoice_id() : $doc->get_estimate_id();
		}

		if ( is_a( $doc, 'SI_Invoice' ) ) {
			$new_id = self::filter_invoice_id( $id, $post_id );
			$doc->set_invoice_id( $new_id );
		} elseif ( is_a( $doc, 'SI_Estimate' ) ) {
			$new_id = self::filter_estimate_id( $id, $post_id );
			$doc->set_estimate_id( $new_id );
		}
	}

	public static function filter_title( $title = '', $post_id = '' ) {
		// the sequence is not filtered becuase it would increment incorrectly since the client id is saved after.
		// This will create a temp shortcode that will be filtered so tha the doc id can be used instead.
		if ( false !== strpos( $title, '{client_type_seq}' ) ) {
			return '{invoice_id after save w/ client}';
		}

		if ( false !== strpos( $title, '{invoice_id after save w/ client}' ) ) {
			$doc = si_get_doc_object( $post_id );
			$client_id = $doc->get_client_id();

			if ( ! $client_id ) {
				return $title; //temp shortcode
			}

			if ( is_a( $doc, 'SI_Invoice' ) ) {
				$id = $doc->get_invoice_id();
			} elseif ( is_a( $doc, 'SI_Estimate' ) ) {
				$id = $doc->get_estimate_id();
			} else {
				return 'something is wrong';
			}

			if ( false !== strpos( $id, '{client_type_seq}' ) ) {
				return '{invoice_id after save w/ client}';
			}

			return $id;
		}

		// nothing else to attempt to filter
		if ( false === strpos( $title, '{client_type}' ) ) {
			return $title; // nothing to filter
		}

		$doc = si_get_doc_object( $post_id );
		if ( is_a( $doc, 'SI_Invoice' ) ) {
			$title = self::filter_invoice_id( $title, $post_id );
		} elseif ( is_a( $doc, 'SI_Estimate' ) ) {
			$title = self::filter_estimate_id( $title, $post_id );
		}

		return $title;
	}

	public static function filter_invoice_id( $filtered_id, $invoice_id ) {
		$type = self::get_client_type_tag_from_invoice( $invoice_id );
		$filtered_id = str_replace( '{client_type}', $type, $filtered_id );
		if ( false !== strpos( $filtered_id, '{client_type_seq}' ) ) {
			$seq = self::get_client_type_sequence_from_invoice( $invoice_id, true );
			$filtered_id = str_replace( '{client_type_seq}', $seq, $filtered_id );
		}
		return $filtered_id;
	}

	public static function filter_estimate_id( $filtered_id, $estimate_id ) {
		$type = self::get_client_type_tag_from_estimate( $estimate_id );
		$filtered_id = str_replace( '{client_type}', $type, $filtered_id );
		if ( false !== strpos( $filtered_id, '{client_type_seq}' ) ) {
			$seq = self::get_client_type_sequence_from_estimate( $estimate_id, true );
			$filtered_id = str_replace( '{client_type_seq}', $seq, $filtered_id );
		}
		return $filtered_id;
	}

	public static function get_client_type_tag_from_invoice( $invoice_id = 0 ) {
		$client_id = si_get_invoice_client_id( $invoice_id );
		if ( ! $client_id ) {
			return '{client_type}';
		}
		$term_id = self::get_client_type_id( $client_id );
		$type_tag = get_term_meta( $term_id, self::TERM_META, true );
		return $type_tag;
	}

	public static function get_client_type_tag_from_estimate( $estimate_id = 0 ) {
		$client_id = si_get_estimate_client_id( $estimate_id );
		if ( ! $client_id ) {
			return '{client_type}';
		}
		$term_id = self::get_client_type_id( $client_id );
		$type_tag = get_term_meta( $term_id, self::TERM_META, true );
		return $type_tag;
	}

	public static function get_client_type_sequence_from_invoice( $invoice_id = 0, $increment = false ) {
		$client_id = si_get_invoice_client_id( $invoice_id );
		if ( ! $client_id ) {
			return '{client_type_seq}';
		}
		$term_id = self::get_client_type_id( $client_id );
		$sequence = (int) get_term_meta( $term_id, self::TERM_META_SEQ, true );
		$sequence_padding = (int) get_term_meta( $term_id, self::TERM_META_SEQ_PAD, true );
		if ( $increment ) {
			$int = $sequence + 1;
			update_term_meta( $term_id, self::TERM_META_SEQ, (int) $int );
		}
		if ( $sequence_padding > 0 ) {
			$sequence = str_pad( $sequence, $sequence_padding, '0', STR_PAD_LEFT );
		}
		return $sequence;
	}

	public static function get_client_type_sequence_from_estimate( $estimate_id = 0, $increment = false ) {
		$client_id = si_get_estimate_client_id( $estimate_id );
		if ( ! $client_id ) {
			return '{client_type_seq}';
		}
		$term_id = self::get_client_type_id( $client_id );
		$sequence = (int) get_term_meta( $term_id, self::TERM_META_SEQ, true );
		$sequence_padding = (int) get_term_meta( $term_id, self::TERM_META_SEQ_PAD, true );
		if ( $increment ) {
			update_term_meta( $term_id, self::TERM_META_SEQ, $sequence++ );
		}
		if ( $sequence_padding > 0 ) {
			$sequence = str_pad( $sequence, $sequence_padding, '0', STR_PAD_LEFT );
		}
		return $sequence;
	}
}
SI_Client_Type_Numbering::init();
