<div id="time_tracker_wrap">
	<div id="tt_body" class="admin_fields clearfix">
		<?php sa_admin_fields( $fields, 'time' ); ?>
	</div><!-- #tt_body -->
	<div id="tt_save">
		<p>
			<button href="javascript:void(0)" id="create_time_entry" class="button button-large button-primary"><?php _e( 'Log Time', 'sprout-invoices' ) ?></button>
		</p>
	</div><!-- #tt_save -->
</div><!-- #time_tracker_wrap -->