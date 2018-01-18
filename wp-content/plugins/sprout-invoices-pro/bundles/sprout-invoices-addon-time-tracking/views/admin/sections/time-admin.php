<div id="time_table">
	<table class="table sa_table sa_table-hover">
		<thead>
			<tr>
				<th>&nbsp;</th>
				<th><?php _e( 'Activity', 'sprout-invoices' ) ?></th>
				<th><?php _e( 'Billable', 'sprout-invoices' ) ?></th>
			</tr>
		</thead>
		<tbody>
			<?php if ( ! empty( $time ) ): ?>
				<?php foreach ( $time as $time_id ): ?>
					<?php 
						$time = SI_Time::get_instance( $time_id );
						if ( !is_a( $time, 'SI_Time' ) ) {
							continue;
						} ?>
					<tr id="<?php echo (int) $time_id ?>">
						<td><span class="time_activity_deletion item_action item_delete" data-id="<?php echo (int) $time_id ?>" data-nonce="<?php echo wp_create_nonce( SI_Time_Tracking_Premium::SUBMISSION_NONCE ) ?>"></span></td>
						<td><?php echo esc_html( $time->get_title() )  ?></td>
						<td><?php if ( $time->is_billable() ) echo '<b>x</b>';  ?></td>
					</tr>
				<?php endforeach ?>
			<?php else: ?>
				<tr colspan="4"><td><?php _e( 'No activities found.', 'sprout-invoices' ) ?></td></tr>
			<?php endif ?>
		</tbody>
	</table>
	<?php printf( __( '<a href="%s">Create New</a>', 'sprout-invoices' ), 'javascript:void(0)" id="show_time_creation_modal" class="button button-primary' ) ?>
	
</div><!-- #time_entries -->