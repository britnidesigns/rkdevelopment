<?php do_action( 'si_payment_terms_metabox_start', $doc_id ) ?>
<div id="payment_terms" data-doc-id="<?php echo (int) $doc_id ?>">
	<table class="table sa_table sa_table-hover">
		<thead>
			<tr>
				<th class="real_skinny_col">&nbsp;</th>
				<th class="real_skinny_col"><?php esc_html_e( 'Status', 'sprout-invoices' ) ?></th>
				<th class="skinny_col"><?php esc_html_e( 'Due Date', 'sprout-invoices' ) ?></th>
				<th class="skinny_col"><?php esc_html_e( 'Payment Due', 'sprout-invoices' ) ?></th>
				<th class="skinny_col"><?php esc_html_e( 'Fee', 'sprout-invoices' ) ?></th>
				<th><?php esc_html_e( 'Description', 'sprout-invoices' ) ?></th>
			</tr>
		</thead>
		<tbody>
			<?php
			// no tallys because of percentages.
			if ( ! empty( $sorted_payment_terms ) ) :

				foreach ( $sorted_payment_terms as $data ) :

					if ( ! isset( $data['fee'] ) ) {
						continue;
					}

					$payment_term_id = $data['term_id'];
					$doc_id = get_the_id();
					?>
						<tr id="<?php echo (int) $payment_term_id ?>">
							
							<td><span class="payment_term_entry_deletion item_action item_delete" data-id="<?php echo esc_attr( $payment_term_id ); ?>" data-doc-id="<?php echo esc_attr( $doc_id ); ?>" data-nonce="<?php echo wp_create_nonce( SI_Controller::NONCE ) ?>"></span></td>
							
							<?php if ( isset( $data['complete'] ) && 'true' == $data['complete'] ) :  ?>
								<td><span class="payment_term_completed"><span class="dashicons dashicons-yes"></span></span></td>
							<?php else : ?>

								<?php if ( current_time( 'timestamp' ) > $data['time'] ) :  ?>
									<td><span class="payment_term_not_completed"><span class="dashicons dashicons-flag"></span></span></span></td>
								<?php else : ?>
									<td><span class="payment_term_not_completed"><span class="dashicons dashicons-calendar-alt"></span></span></td>
								<?php endif ?>
								
							<?php endif ?>
							
							<td><span class="payment_term_due_time"><?php echo date_i18n( get_option( 'date_format' ), $data['time'] ) ?></td>
							
							<td><span class="payment_term_payment_due"><?php printf( '%s', sa_get_formatted_money( (float) $data['balance'], $doc_id ) ) ?></span></td>

							<?php if ( isset( $data['percentage'] ) && 'true' == $data['percentage'] ) :  ?>
								<td><?php printf( '%s%%', si_get_number_format( (float) $data['fee'] ) ) ?></td>
							<?php else : ?>
								<td><?php printf( '%s', sa_get_formatted_money( (float) $data['fee'], $doc_id ) ) ?></td>
							<?php endif ?>

							<td><?php echo $data['title'] ?></td>
						</tr>
				<?php endforeach ?>
			<?php else : ?>
				<tr><td colspan="7"><?php _e( 'No payment terms for this document.', 'sprout-invoices' ) ?></td></tr>
			<?php endif ?>
		</tbody>		
	</table>
</div><!-- #payment_terms -->
<?php do_action( 'si_payment_terms_metabox_end', $doc_id ) ?>
