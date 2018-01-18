<?php

/**
 * Shipping Fees
 *
 * @package Sprout_Invoice
 * @subpackage SI_Shipping_Fee
 */
class SI_Shipping_Fee extends SI_Controller {

	public static function init() {
		add_action( 'si_delayed_load', array( __CLASS__, '_add_filter' ) );

		// Shipping option
		add_action( 'doc_information_meta_box_last', array( __CLASS__, 'add_shipping_option' ) );
		add_action( 'invoice_meta_saved', array( __CLASS__, 'save_shipping_option' ) );
		add_action( 'estimate_meta_saved', array( __CLASS__, 'save_shipping_option' ) );

	}

	public static function _add_filter() {
		// Shipping fee, great example of a fee
		add_filter( 'si_doc_fees', array( __CLASS__, 'add_shipping_as_fee' ), 10, 2 );
	}

	//////////////
	// Shipping //
	//////////////

	public static function add_shipping_as_fee( $fees, $doc ) {
		$fees['shipping'] = array(
				'label' => __( 'Shipping', 'sprout-invoices' ),
				'always_show' => false,
				'total_callback' => array( __CLASS__, 'calculate_shipping_fee' ),
				'weight' => 21,
			);
		return $fees;
	}

	public static function calculate_shipping_fee( $doc, $data = array() ) {
		$fee_total = $doc->get_shipping();
		return $fee_total;
	}

	public static function save_shipping_option( $doc ) {
		$shipping = ( isset( $_POST['shipping'] ) && $_POST['shipping'] != '' ) ? $_POST['shipping'] : '' ;
		$doc->set_shipping( $shipping );
	}

	public static function add_shipping_option( $doc ) {
		$shipping = $doc->get_shipping();
		?>
<!-- Shipping -->
<div class="misc-pub-section update-total" data-edit-id="shipping">
	<span id="shipping" class="wp-media-buttons-icon"><?php _e( 'Shipping', 'sprout-invoices' ) ?> <b><?php sa_formatted_money( $shipping, $doc->get_id() ) ?></b></span>

	<a href="#edit_shipping" class="edit-shipping hide-if-no-js edit_control" >
		<span aria-hidden="true"><?php _e( 'Edit', 'sprout-invoices' ) ?></span> <span class="screen-reader-text"><?php _e( 'Edit tax', 'sprout-invoices' ) ?></span>
	</a>
	<span title="<?php _e( 'Shipping is applied before the discount.', 'sprout-invoices' ) ?>" class="si_tooltip"></span>

	<div id="shipping_div" class="control_wrap hide-if-js">
		<div class="shipping-wrap">
			<input type="text" name="shipping" value="<?php echo (float) $shipping ?>" size="3">
 		</div>
 		
		<p>
			<a href="#edit_shipping" class="save_control save-shipping hide-if-no-js button"><?php _e( 'OK', 'sprout-invoices' ) ?></a>
			<a href="#edit_shipping" class="cancel_control cancel-shipping hide-if-no-js button-cancel"><?php _e( 'Cancel', 'sprout-invoices' ) ?></a>
		</p>
 	</div>
</div>
		<?php
	}
}
