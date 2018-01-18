<?php do_action( 'si_payment_terms_option_start', $doc_id ) ?>
<div id="fees_tracker_wrap">
	<div id="tt_body" class="admin_fields clearfix">
		<?php sa_admin_fields( $fields, 'fees' ); ?>
	</div><!-- #tt_body -->
	<div id="tt_save">
		<p>
			<button href="javascript:void(0)" id="create_fees_entry" class="button button-large button-primary"><?php _e( 'Log fees', 'sprout-invoices' ) ?></button>
		</p>
	</div><!-- #tt_save -->
</div><!-- #fees_tracker_wrap -->
<?php do_action( 'si_payment_terms_option_end', $doc_id ) ?>
