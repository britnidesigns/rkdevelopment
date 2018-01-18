<?php

foreach ( $times as $time_id ) {
	$time = SI_Time::get_time_entry( $time_id );
	if ( ! is_a( $time, 'SI_Record' ) ) {
		continue;
	}
	$time_records[ $time_id ] = $time->get_data();
}

if ( empty( $time_records ) ) {
	return;
}

$unbilled_time = array();
uasort( $time_records, array( 'SI_Controller', 'sort_by_date' ) );
foreach ( $time_records as $time_id => $data ) {
	if ( isset( $data['invoice_id'] ) ) { // invoiced
		continue;
	}
	$time = SI_Time::get_time_entry( $time_id );
	$activity = SI_Time::get_instance( $time->get_associate_id() );
	if ( ! $activity->is_billable() ) { // activity is unbillable
		continue;
	}
	if ( ! current_user_can( 'edit_sprout_invoices' ) ) {
		$post = $time->get_post();
		if ( (int) $post->post_author !== get_current_user_id() ) {
			continue;
		}
	}
	$unbilled_time[ $time_id ] = $data;
}
// Don't continue if there's no unbilled time
if ( empty( $unbilled_time ) ) {
	return;
}

	$unbilled_total_time = 0;
	$unbilled_total_cost = 0; ?>

<div id="time_tracking_wrap">
	<h3 class="dashboard_widget_title"><a href="<?php echo get_edit_post_link( $project->get_id() ) ?>"><?php echo $project->get_title() ?></a></h3>
	<table class="table sa_table sa_table-hover">
		<thead>
			<tr>
				<th><?php esc_html_e( 'Info' , 'sprout-invoices' ) ?></th>
				<th><?php esc_html_e( 'Activity' , 'sprout-invoices' ) ?></th>
				<th><?php esc_html_e( 'Note' , 'sprout-invoices' ) ?></th>
				<th><?php esc_html_e( 'Time' , 'sprout-invoices' ) ?></th>
				<th><?php echo sa_get_currency_symbol() ?></th>
			</tr>
		</thead>
		<tbody>
			<?php
			$shown_item = 0;
			krsort( $unbilled_time );
			foreach ( $unbilled_time as $time_id => $data ) :
				$shown_item++;
				// only show 5
				if ( $shown_item > 5 ) {
					break;
				}

				$time = SI_Time::get_time_entry( $time_id );
				$activity = SI_Time::get_instance( $time->get_associate_id() );

				if ( ! isset( $data['time_val'] ) ) {
					continue;
				}

				$cost = ( is_a( $activity, 'SI_Time' ) ) ? $data['time_val'] * $activity->get_default_rate()  : 0 ;

				$unbilled_total_time += (float) $data['time_val'];
				$unbilled_total_cost += $cost;
				if ( ! isset( $data['user_id'] ) ) {
					continue;
				}
				$user = get_userdata( $data['user_id'] );
				if ( ! is_a( $user, 'WP_User' ) ) {
					continue;
				}
				$date = ( isset( $data['date'] ) ) ? $data['date'] : get_post_time( $time_id );
				?>
				<tr id="<?php echo (int) $time_id ?>">
					<td><span class="time_submitted_by"><a href="<?php echo get_edit_user_link( $user->ID ) ?>"><?php echo $user->data->display_name ?></a></span> @&nbsp;<?php echo date_i18n( get_option( 'date_format' ), $date ) ?></td>
					<td><?php
					$activity_title = ( is_a( $activity, 'SI_Time' ) ) ? $activity->get_title() : '&nbsp;' ;
					echo esc_html( $activity_title );  ?></td>
					<td><?php echo wpautop( sa_get_truncate( $time->get_title(), 10 ) ) ?></td>
					<td><?php si_number_format( (float) $data['time_val'] ) ?></td>
					<td><?php sa_formatted_money( $cost ); ?></td>
				</tr>

				<?php if ( 5 === $shown_item && count( $unbilled_time ) > 5 ) : ?>
					<tr id="continued">
						<td colspan="5">
							<?php printf( __( '5 of %s unbilled time entries shown for <a href="%s">%s</a>', 'sprout-invoices' ), count( $unbilled_time ), get_edit_post_link( $project->get_id() ), $project->get_title() ) ?>
						</td>
					</tr>
				<?php endif ?>

			<?php endforeach ?>
		</tbody>
		<tfoot>
			<tr>
				<td colspan="3" class="tfoot_total_desc">
					<?php
					$create_invoice = add_query_arg( array( 'post_type' => SI_Invoice::POST_TYPE, SI_Time_Tracking_Premium::IMPORT_QUERY_VAR => (int) $project->get_id() ), admin_url( 'post-new.php' ) ); ?>
					<a href="<?php echo esc_url( $create_invoice ) ?>" title="<?php _e( 'Import Time into an Invoice', 'sprout-invoices' ) ?>" class="si_tooltip"><span class="icon-sproutapps-invoices"></span></a>
				</td>
				<td><?php si_number_format( (float) $unbilled_total_time ); ?></td>
				<td><b><?php sa_formatted_money( $unbilled_total_cost ); ?></b></td>
			</tr>
		</tfoot>
	</table>
</div><!-- #time_tracker_wrap -->
