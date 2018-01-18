<div id="expense_table">
	<table class="table sa_table sa_table-hover">
		<thead>
			<tr>
				<th>&nbsp;</th>
				<th><?php _e( 'Category', 'sprout-invoices' ) ?></th>
				<th><?php _e( 'Billable', 'sprout-invoices' ) ?></th>
			</tr>
		</thead>
		<tbody>
			<?php if ( ! empty( $expense ) ) :  ?>
				<?php foreach ( $expense as $expense_id ) :  ?>
					<?php
						$expense = SI_Expense::get_instance( $expense_id );
					if ( ! is_a( $expense, 'SI_Expense' ) ) {
						continue;
					} ?>
					<tr id="<?php echo (int) $expense_id ?>">
						<td><span class="expense_category_deletion item_action item_delete" data-id="<?php echo (int) $expense_id ?>" data-nonce="<?php echo wp_create_nonce( SI_Expense_Tracking_Premium::SUBMISSION_NONCE ) ?>"></span></td>
						<td><?php echo esc_html( $expense->get_title() )  ?></td>
						<td><?php if ( $expense->is_billable() ) { echo '<b>x</b>'; }  ?></td>
					</tr>
				<?php endforeach ?>
			<?php else : ?>
				<tr colspan="4"><td><?php _e( 'No categories found.', 'sprout-invoices' ) ?></td></tr>
			<?php endif ?>
		</tbody>
	</table>
	<?php printf( __( '<a href="%s">Create New</a>', 'sprout-invoices' ), 'javascript:void(0)" id="show_expense_creation_modal" class="button button-primary' ) ?>
	
</div><!-- #expense_entries -->
