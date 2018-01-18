<div id="expense_entries" data-project-id="<?php echo (int) $project_id ?>">
	<table class="table sa_table sa_table-hover">
		<thead>
			<tr>
				<th>&nbsp;</th>
				<th><?php esc_html_e( 'Info', 'sprout-invoices' ) ?></th>
				<th><?php esc_html_e( 'Category', 'sprout-invoices' ) ?></th>
				<th><?php esc_html_e( 'Expense', 'sprout-invoices' ) ?></th>
				<th><?php esc_html_e( 'Note', 'sprout-invoices' ) ?></th>
				<th><?php esc_html_e( 'Attachments', 'sprout-invoices' ) ?></th>
				<th><?php esc_html_e( 'Cost', 'sprout-invoices' ) ?></th>
				<th>&nbsp;</th>
			</tr>
		</thead>
		<tbody>
			<?php
				$total_expense = 0;
				$total_cost = 0;
				$billed_total_expense = 0;
				$billed_total_cost = 0;
				$unbilled_total_expense = 0;
				$unbilled_total_cost = 0;

			if ( ! empty( $expenses ) ) :

				$expense_records = array();
				foreach ( $expenses as $expense_id ) {
					$expense = SI_Expense::get_expense_entry( $expense_id );
					if ( ! is_a( $expense, 'SI_Record' ) ) {
						continue;
					}
					$expense_records[ $expense_id ] = $expense->get_data();
				}

				uasort( $expense_records, array( 'SI_Controller', 'sort_by_date' ) );

				foreach ( $expense_records as $expense_id => $data ) :
					if ( ! isset( $data['expense_val'] ) ) {
						continue;
					}
					$expense = SI_Expense::get_expense_entry( $expense_id );
					$category = SI_Expense::get_instance( $expense->get_associate_id() );
					$user_id = ( isset( $data['user_id'] ) ) ? $data['user_id']: get_current_user_id();
					$user = get_userdata( $user_id );

					$cost = ( is_a( $category, 'SI_Expense' ) ) ? $data['expense_val'] : 0 ;
					$total_expense += (float) $data['expense_val'];
					$total_cost += $cost;

					if ( isset( $data['invoice_id'] ) ) {
						$billed_total_expense += (float) $data['expense_val'];
						$billed_total_cost += $cost;
					} elseif ( $category->is_billable() ) {
						$unbilled_total_expense += (float) $data['expense_val'];
						$unbilled_total_cost += $cost;
					} ?>
						<tr id="<?php echo (int) $expense_id ?>">
							<td><span class="expense_entry_deletion item_action item_delete" data-id="<?php echo esc_attr( $expense_id ); ?>" data-project-id="<?php echo esc_attr( $project_id ); ?>" data-nonce="<?php echo wp_create_nonce( SI_Expense_Tracking_Premium::SUBMISSION_NONCE ) ?>"></span></td>
							<td><span class="expense_submitted_by"><a href="<?php echo get_edit_user_link( $user->ID ) ?>"><?php echo $user->data->display_name ?></a></span><br/>@&nbsp;<?php echo date_i18n( get_option( 'date_format' ), $data['date'] ) ?></td>
							<td><?php
							$category_title = ( is_a( $category, 'SI_Expense' ) ) ? $category->get_title() : '&nbsp;' ;
							echo esc_html( $category_title );  ?></td>
							<td><?php echo $expense->get_title() ?></td>
							<td><?php echo wpautop( $data['note'] ) ?></td>
							<td>
								<?php if ( ! empty( $data['attachments'] ) ) :  ?>
									<p class="expense_attachments">
										<?php foreach ( $data['attachments'] as $media_id ) : ?>

											<?php
												$file = basename( get_attached_file( $media_id ) );
												$filetype = wp_check_filetype( $file );
												$thumb_url = wp_get_attachment_thumb_url( $media_id );
												$icon = SI_Expense_Tracking_Premium::get_attachment_icon( $media_id ); ?>
											<a href="<?php echo wp_get_attachment_url( $media_id ) ?>">
												<img src="<?php echo esc_url_raw( $icon ) ?>" title="<?php echo get_the_title( $media_id ) ?>" class="doc_attachment attachment_type_<?php echo esc_attr( $filetype['ext'] ) ?>"></a>
											</a>
										<?php endforeach ?>
									</p>
								<?php else : ?>
									<?php _e( 'No Attachments', 'sprout-invoices' ) ?>
								<?php endif ?>

							</td>
							<td><?php si_number_format( (float) $data['expense_val'] ) ?></td>
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
				<?php endforeach ?>
			<?php else : ?>
				<tr><td colspan="7"><?php _e( 'No expense entries for this project.', 'sprout-invoices' ) ?></td></tr>
			<?php endif ?>
		</tbody>

		<?php if ( $total_expense > 0.00 ) : ?>
			<tfoot>
				<tr>
					<td colspan="5" class="tfoot_total_desc"><?php _e( 'Billed', 'sprout-invoices' ) ?></td>
					<td><?php si_number_format( (float) $billed_total_expense ); ?></td>
					<td colspan="2"><b><?php sa_formatted_money( $billed_total_cost ); ?></b></td>
				</tr>
				<?php if ( (float) $unbilled_total_expense > 0.00 ) : ?>
					<tr>
						<td colspan="5" class="tfoot_total_desc"><?php _e( 'Not Invoiced', 'sprout-invoices' ) ?></td>
						<td><?php si_number_format( (float) $unbilled_total_expense ); ?></td>
						<td><b><?php sa_formatted_money( $unbilled_total_cost ); ?></b></td>
						<?php
							$create_invoice = add_query_arg( array( 'post_type' => SI_Invoice::POST_TYPE, SI_Expense_Tracking_Premium::IMPORT_QUERY_VAR => (int) $project_id ), admin_url( 'post-new.php' ) ); ?>
						<td><a href="<?php echo esc_url( $create_invoice ) ?>"><span class="icon-sproutapps-invoices"></span></a></td>
					</tr>
				<?php endif ?>
				<tr>
					<td colspan="5" class="tfoot_total_desc"><?php _e( 'Totals', 'sprout-invoices' ) ?></td>
					<td><?php si_number_format( (float) $total_expense ); ?></td>
					<td colspan="2"><b><?php sa_formatted_money( $total_cost ); ?></b></td>
				</tr>
			</tfoot>
		<?php endif ?>
		
	</table>
</div><!-- #expense_entries -->
