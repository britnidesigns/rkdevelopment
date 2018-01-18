<span id="predefined_line_items">

	<select id="predefined-items" class="button">

		<option selected="selected"></option>

		<?php foreach ( $types as $slug => $label ) : ?>
			<?php
			if ( ! isset( $items[ $slug ] ) || empty( $items[ $slug ] ) ) {
				continue;
			} ?>
			
			<optgroup label="<?php echo esc_attr( $label ) ?>">
				<?php foreach ( $items[ $slug ] as $item_id => $item ) : ?>
					<option value="<?php echo (int) esc_attr( $item_id ) ?>"><?php echo esc_html( $item['title'] ) ?></option>
				<?php endforeach ?>
			</optgroup>
			
		<?php endforeach ?>

		<optgroup label="<?php _e( 'Admin', 'sprout-invoices' ) ?>">
			
			<option value="manage_items">&rsaquo;&nbsp;<?php _e( 'Manage Items', 'sprout-invoices' ) ?></option>
			<option value="create_new_item">&rsaquo;&nbsp;<?php _e( 'Create Item', 'sprout-invoices' ) ?></option>

		</optgroup>

	</select>

</span>