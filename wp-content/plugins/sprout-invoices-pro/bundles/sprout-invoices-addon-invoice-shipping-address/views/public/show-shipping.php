<dl id="doc_address_info">
		<dl class="client_addy">
			<dt>
				<span class="dt_heading"><?php _e( 'Shipping' , 'sprout-invoices' ) ?></span>
			</dt>
			<dd>
				<?php if ( ! empty( $shipping_address ) ) :  ?>
					<?php echo si_address( $shipping_address ) ?>
					<a href="javascript:void(0)" class="button edit_shipping_button"><?php _e( 'Edit', 'sprout-invoices' ) ?></a>
				<?php else : ?>	
						<?php _e( 'N/A', 'sprout-invoices' ) ?>
						<a href="javascript:void(0)" class="button edit_shipping_button"><?php _e( 'Add', 'sprout-invoices' ) ?></a>
				<?php endif ?>
			</dd>
		</dl>
</dl><!-- #doc_address_info -->
<style type="text/css">
	.edit_shipping_button {
		font-size: 8px;
		padding: 1px 5px;
		background-color: #999;
		/* font-weight: 100; */
		float: none;
		display: inline-block;
	}
</style>
<script type="text/javascript">
	jQuery("#doc_address_info a.edit_shipping_button").on('click', function(e) {
		e.preventDefault();
		$('#edit_shipping_checkout_wrap').slideDown();
	});
</script>
