<?php
	$total_expense = 0;
	$total_cost = 0;
	$billed_total_expense = 0;
	$billed_total_cost = 0;
	$unbilled_total_expense = 0;
	$unbilled_total_cost = 0;

	$expense_records = array();
foreach ( $expenses as $expense_id ) {
	$expense = SI_Expense::get_expense_entry( $expense_id );
	if ( ! is_a( $expense, 'SI_Record' ) ) {
		continue;
	}
	if ( ! current_user_can( 'edit_sprout_invoices' ) ) {
		$post = $expense->get_post();
		if ( (int) $post->post_author !== get_current_user_id() ) {
			continue;
		}
	}
	$expense_records[ $expense_id ] = $expense->get_data();
}
if ( empty( $expense_records ) ) {
	return;
}

	uasort( $expense_records, array( 'SI_Controller', 'sort_by_date' ) ); ?>

<div id="expense_tracking_wrap">
	<h3 class="dashboard_widget_title"><a href="<?php echo get_edit_post_link( $project->get_id() ) ?>"><?php echo $project->get_title() ?></a></h3>
	<table class="table sa_table sa_table-hover">
		<thead>
			<tr>
				<th><?php esc_html_e( 'Info' , 'sprout-invoices' ) ?></th>
				<th><?php esc_html_e( 'Category' , 'sprout-invoices' ) ?></th>
				<th><?php esc_html_e( 'Note' , 'sprout-invoices' ) ?></th>
				<th><?php esc_html_e( 'Expense' , 'sprout-invoices' ) ?></th>
				<th><?php echo sa_get_currency_symbol() ?></th>
				<th>&nbsp;</th>
			</tr>
		</thead>
		<tbody>
			<?php
			$shown_item = 0;
			krsort( $expense_records );
			foreach ( $expense_records as $expense_id => $data ) :
				$shown_item++;
				// only show 5
				if ( $shown_item > 5 ) {
					break;
				}
				$expense = SI_Expense::get_expense_entry( $expense_id );
				$category = SI_Expense::get_instance( $expense->get_associate_id() );

				if ( ! isset( $data['expense_val'] ) ) {
					continue;
				}

				$cost = ( is_a( $category, 'SI_Expense' ) ) ? $data['expense_val']  : 0 ;
				$total_expense += (float) $data['expense_val'];
				$total_cost += $cost;
				$user = ( isset( $data['user_id'] ) ) ? get_userdata( $data['user_id'] ) : get_userdata( get_current_user_id() );

				if ( isset( $data['invoice_id'] ) ) {
					$billed_total_expense += (float) $data['expense_val'];
					$billed_total_cost += $cost;
				} elseif ( $category->is_billable() ) {
					$unbilled_total_expense += (float) $data['expense_val'];
					$unbilled_total_cost += $cost;
				} ?>
				<tr id="<?php echo (int) $expense_id ?>">
					<td><span class="expense_submitted_by"><a href="<?php echo get_edit_user_link( $user->ID ) ?>"><?php echo $user->data->display_name ?></a></span> @&nbsp;<?php echo date_i18n( get_option( 'date_format' ), $data['date'] ) ?></td>
					<td><?php
					$category_title = ( is_a( $category, 'SI_Expense' ) ) ? $category->get_title() : '&nbsp;' ;
					echo esc_html( $category_title );  ?></td>
					<td><?php echo wpautop( sa_get_truncate( $expense->get_title(), 10 ) ) ?></td>
					<td><?php si_number_format( (float) $data['expense_val'] ) ?></td>
					<td><?php sa_formatted_money( $cost ); ?></td>
					<td>
						<?php if ( isset( $data['invoice_id'] ) ) : ?>
							<a href="<?php echo get_edit_post_link( $data['invoice_id'] ) ?>" class="si_status <?php echo si_get_invoice_status( $data['invoice_id'] ); ?>"><span class="icon-sproutapps-invoices"></span></a>
						<?php else : ?>
							<?php if ( is_a( $category, 'SI_Expense' ) && $category->is_billable() ) : ?>
								<span class="icon-sproutapps-invoices si_tooltip" title="<?php _e( 'Expense is yet to be billed.', 'sprout-invoices' ) ?>"></span>
							<?php endif ?>
						<?php endif ?>
						
					</td>
				</tr>

				<?php if ( 5 === $shown_item && count( $expense_records ) > 5 ) : ?>
					<tr id="continued">
						<td colspan="5">
							<?php printf( __( '5 of %s unbilled expense entries shown for <a href="%s">%s</a>', 'sprout-invoices' ), count( $expense_records ), get_edit_post_link( $project->get_id() ), $project->get_title() ) ?>
						</td>
					</tr>
				<?php endif ?>
			<?php endforeach ?>
		</tbody>
		<?php if ( $total_expense > 0.00 ) : ?>
			<tfoot>
				<tr>
					<td colspan="3" class="tfoot_total_desc"><?php _e( 'Billed', 'sprout-invoices' ) ?></td>
					<td><?php si_number_format( (float) $billed_total_expense ); ?></td>
					<td colspan="2"><b><?php sa_formatted_money( $billed_total_cost ); ?></b></td>
				</tr>
				<?php if ( (float) $unbilled_total_expense > 0.00 ) : ?>
					<tr>
						<td colspan="3" class="tfoot_total_desc"><?php _e( 'Not Invoiced', 'sprout-invoices' ) ?></td>
						<td><?php si_number_format( (float) $unbilled_total_expense ); ?></td>
						<td><b><?php sa_formatted_money( $unbilled_total_cost ); ?></b></td>
						<?php
							$create_invoice = add_query_arg( array( 'post_type' => SI_Invoice::POST_TYPE, SI_Expense_Tracking_Premium::IMPORT_QUERY_VAR => $project->get_id() ), admin_url( 'post-new.php' ) ); ?>
						<td><a href="<?php echo esc_url( $create_invoice ) ?>"><span class="icon-sproutapps-invoices"></span></a></td>
					</tr>
				<?php endif ?>
				<tr>
					<td colspan="3" class="tfoot_total_desc"><?php _e( 'Totals', 'sprout-invoices' ) ?></td>
					<td><?php si_number_format( (float) $total_expense ); ?></td>
					<td colspan="2"><b><?php sa_formatted_money( $total_cost ); ?></b></td>
				</tr>
			</tfoot>
		<?php endif ?>
	</table>
</div><!-- #expense_tracker_wrap -->
