<div id="time_creation_modal">
	<div id="tt_body" class="admin_fields clearfix">
		<?php sa_admin_fields( $fields, 'item' ); ?>
	</div><!-- #tt_body -->
	<div id="tt_save">
		<p>
			<a href="javascript:void(0)" id="create_predefined_item" class="si_admin_button"><?php _e( 'Create Item', 'sprout-invoices' ) ?></a>
			<a href="javascript:void(0)" id="manage_items" class="button" data-item-id="0"><?php _e( 'Manage Items', 'sprout-invoices' ) ?></a>
		</p>

	</div><!-- #tt_save -->

</div><!-- #time_creation_modal -->

<script type="text/javascript">
	jQuery(function() {
		jQuery('#sa_item_description').redactor();
		jQuery('#sa_item_type').change(function() {
			jQuery('#sa_item_qty').prop('disabled', false);
			var $val = jQuery('#sa_item_type').val();
			if ( 'product' === $val ) {
				jQuery('#sa_item_qty').val(1);
				jQuery('#sa_item_qty').prop('disabled', true);
			}
		});
	});
</script>
