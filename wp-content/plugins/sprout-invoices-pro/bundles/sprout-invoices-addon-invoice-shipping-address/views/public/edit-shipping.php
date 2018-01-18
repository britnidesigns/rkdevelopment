<style type="text/css">
	#edit_shipping_checkout_wrap {
		background-color: #FFF;
		margin: 0px -1000px 50px -1000px;
		padding: 50px 1000px;
		display: none;
		border-bottom: 1px solid #FFF;
		border-top: 1px solid #FFF;
		-moz-box-shadow: inset 0 0 5px #ccc;
		-webkit-box-shadow: inset 0 0 5px #ccc;
		box-shadow: inset 0 0 5px #CCC;
		position: relative;
		z-index: 10;
	}
</style>
<div id="edit_shipping_checkout_wrap">
	<?php do_action( 'si_edit_shipping_checkout_wrap' ) ?>
	<form action="" method="post" accept-charset="utf-8" class="sa-form sa-form-aligned" id="si_edit_shipping">
		<div id="shipping_address_fields" class="clearfix">
			<fieldset id="shipping_address_fields" class="sa-fieldset">
				<legend><?php _e( 'Edit Shipping Address', 'sprout-invoices' ) ?></legend>
				<?php sa_form_fields( $shipping_address, 'billing' ); ?>
			</fieldset>
			<label for="update_client"><input type="checkbox" name="update_client" value="1" id="update_client" />&nbsp;<?php _e( 'Update Your Default Address', 'sprout-invoices' ) ?></label>
			<input type="hidden" name="edit_shipping" value="<?php echo wp_create_nonce( SI_Shipping_Addy::NONCE ) ?>" />
			<button type="submit" class="button button-primary" id="edit_shipping"><?php _e( 'Submit', 'sprout-invoices' ) ?></button>
		</div><!-- #shipping_address_fields -->
	</form>
</div><!-- #edit_shipping_checkout_wrap -->
<?php do_action( 'si_edit_shipping_checkout_post_wrap' ) ?>
