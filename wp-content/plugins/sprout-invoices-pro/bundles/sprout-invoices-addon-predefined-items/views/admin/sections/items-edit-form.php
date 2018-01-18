<div id="item_creation_modal">
	<div id="tt_body" class="admin_fields clearfix">
		<?php sa_admin_fields( $fields, 'item' ); ?>
	</div><!-- #tt_body -->
	<div id="tt_save">
		<p>
			<a href="javascript:void(0)" id="edit_predefined_item" class="button button-large button-primary"><?php _e( 'Edit Item', 'sprout-invoices' ) ?></a>
			<a href="javascript:void(0)" id="manage_items" class="button" data-item-id="0"><?php _e( 'Manage Items', 'sprout-invoices' ) ?></a>
		</p>

	</div><!-- #tt_save -->

</div><!-- #item_creation_modal -->

<script type="text/javascript">
	jQuery(function() {
		jQuery('#sa_item_description').redactor();
	});
</script>