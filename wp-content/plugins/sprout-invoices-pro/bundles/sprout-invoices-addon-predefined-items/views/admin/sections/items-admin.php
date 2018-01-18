<div id="time_table">
	<table class="table sa_table sa_table-hover">
		<thead>
			<tr>
				<th>&nbsp;</th>
				<th><?php esc_html_e( 'Type' , 'sprout-invoices' ) ?></th>
				<th><?php esc_html_e( 'Label' , 'sprout-invoices' ) ?></th>
				<th><?php esc_html_e( 'Description' , 'sprout-invoices' ) ?></th>
				<th><?php esc_html_e( 'SKU' , 'sprout-invoices' ) ?></th>
				<th><?php esc_html_e( 'Rate' , 'sprout-invoices' ) ?></th>
				<th><?php esc_html_e( 'Qty' , 'sprout-invoices' ) ?></th>
				<th><?php esc_html_e( '%' , 'sprout-invoices' ) ?></th>
				<th>&nbsp;</th>
			</tr>
		</thead>
		<tbody>
			<?php if ( ! empty( $items ) ) : ?>
				<?php foreach ( $items as $types => $items ) : ?>
					<?php foreach ( $items as $item_id => $item ) : ?>
						<tr id="<?php echo esc_attr( $item_id ) ?>">
							<td><span class="predefined_item_edit item_action item_edit dashicons dashicons-edit" data-id="<?php echo esc_attr( $item_id ) ?>" data-nonce="<?php echo wp_create_nonce( SI_Controller::NONCE ) ?>"></span></td>
							<td><?php echo esc_html( $item['type'] );  ?></td>
							<td><b style="white-space: nowrap;"><?php echo esc_html( $item['title'] )  ?></b></td>
							<td><?php echo sa_get_truncate( strip_tags( $item['content'] ), 100 ) . '...';  ?></td>
							<td><?php echo esc_html( $item['sku'] );  ?></td>
							<td><?php echo esc_html( $item['rate'] );  ?></td>
							<td><?php echo esc_html( $item['qty'] );  ?></td>
							<td><?php echo esc_html( $item['percentage'] );  ?></td>
							<td><span class="predefined_item_deletion item_action item_delete dashicons dashicons-trash" data-id="<?php echo esc_attr( $item_id ) ?>" data-nonce="<?php echo wp_create_nonce( SI_Controller::NONCE ) ?>"></span></td>
						</tr>
					<?php endforeach ?>
				<?php endforeach ?>
			<?php else : ?>
				<tr colspan="6"><td><?php esc_html_e( 'No items found.' , 'sprout-invoices' ) ?></td></tr>
			<?php endif ?>
		</tbody>
	</table>
	<?php printf( __( '<a href="%s">Create New</a>', 'sprout-invoices' ), 'javascript:void(0)" id="create_new_item" class="button' ) ?>
	
</div><!-- #time_entries -->