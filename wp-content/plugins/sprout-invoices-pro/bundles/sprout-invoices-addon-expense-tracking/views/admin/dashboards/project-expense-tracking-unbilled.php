<?php

foreach ( $expenses as $expense_id ) {
	$expense = SI_Expense::get_expense_entry( $expense_id );
	if ( ! is_a( $expense, 'SI_Record' ) ) {
		continue;
	}
	$expense_records[ $expense_id ] = $expense->get_data();
}

if ( empty( $expense_records ) ) {
	return;
}

$unbilled_expense = array();
uasort( $expense_records, array( 'SI_Controller', 'sort_by_date' ) );
foreach ( $expense_records as $expense_id => $data ) {
	if ( isset( $data['invoice_id'] ) ) { // invoiced
		continue;
	}
	$expense = SI_Expense::get_expense_entry( $expense_id );
	$category = SI_Expense::get_instance( $expense->get_associate_id() );
	if ( ! $category->is_billable() ) { // category is unbillable
		continue;
	}
	if ( ! current_user_can( 'edit_sprout_invoices' ) ) {
		$post = $expense->get_post();
		if ( (int) $post->post_author !== get_current_user_id() ) {
			continue;
		}
	}
	$unbilled_expense[ $expense_id ] = $data;
}
// Don't continue if there's no unbilled expense
if ( empty( $unbilled_expense ) ) {
	return;
}

	$unbilled_total_expense = 0;
	$unbilled_total_cost = 0; ?>

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
			</tr>
		</thead>
		<tbody>
			<?php
			$shown_item = 0;
			krsort( $unbilled_expense );
			foreach ( $unbilled_expense as $expense_id => $data ) :
				$shown_item++;
				// only show 5
				if ( $shown_item > 5 ) {
					break;
				}
				$expense = SI_Expense::get_expense_entry( $expense_id );
				$category = SI_Expense::get_instance( $expense->get_associate_id() );

				$cost = ( is_a( $category, 'SI_Expense' ) ) ? $data['expense_val']  : 0 ;

				$unbilled_total_expense += (float) $data['expense_val'];
				$unbilled_total_cost += $cost;
				$user = get_userdata( $data['user_id'] ); ?>
				<tr id="<?php echo (int) $expense_id ?>">
					<td><span class="expense_submitted_by"><a href="<?php echo get_edit_user_link( $user->ID ) ?>"><?php echo $user->data->display_name ?></a></span> @&nbsp;<?php echo date_i18n( get_option( 'date_format' ), $data['date'] ) ?></td>
					<td><?php
					$category_title = ( is_a( $category, 'SI_Expense' ) ) ? $category->get_title() : '&nbsp;' ;
					echo esc_html( $category_title );  ?></td>
					<td><?php echo wpautop( sa_get_truncate( $expense->get_title(), 10 ) ) ?></td>
					<td><?php si_number_format( (float) $data['expense_val'] ) ?></td>
					<td><?php sa_formatted_money( $cost ); ?></td>
				</tr>

				<?php if ( 5 === $shown_item && count( $unbilled_expense ) > 5 ) : ?>
					<tr id="continued">
						<td colspan="5">
							<?php printf( __( '5 of %s unbilled expense entries shown for <a href="%s">%s</a>', 'sprout-invoices' ), count( $unbilled_expense ), get_edit_post_link( $project->get_id() ), $project->get_title() ) ?>
						</td>
					</tr>
				<?php endif ?>

			<?php endforeach ?>
		</tbody>
		<tfoot>
			<tr>
				<td colspan="3" class="tfoot_total_desc">
					<?php
					$create_invoice = add_query_arg( array( 'post_type' => SI_Invoice::POST_TYPE, SI_Expense_Tracking_Premium::IMPORT_QUERY_VAR => (int) $project->get_id() ), admin_url( 'post-new.php' ) ); ?>
					<a href="<?php echo esc_url( $create_invoice ) ?>" title="<?php _e( 'Import Expense into an Invoice', 'sprout-invoices' ) ?>" class="si_tooltip"><span class="icon-sproutapps-invoices"></span></a>
				</td>
				<td><?php si_number_format( (float) $unbilled_total_expense ); ?></td>
				<td><b><?php sa_formatted_money( $unbilled_total_cost ); ?></b></td>
			</tr>
		</tfoot>
	</table>
</div><!-- #expense_tracker_wrap -->
