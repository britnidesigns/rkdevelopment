<?php 
	$selected = ( isset( $departments[$default] ) ) ? $departments[$default] : __( 'No Department', 'sprout-invoices' ) ;
	 ?>
<div class="misc-pub-section" data-edit-id="department" data-edit-type="select">
	<span id="department" class="wp-media-buttons-icon"><b><?php echo esc_html__( $selected, 'sprout-invoices' ) ?></b> <span title="<?php _e( 'Select an invoicing department.', 'sprout-invoices' ) ?>" class="helptip"></span></span>

		<a href="#edit_department" class="edit-department hide-if-no-js edit_control" >
			<span aria-hidden="true"><?php _e( 'Edit', 'sprout-invoices' ) ?></span> <span class="screen-reader-text"><?php _e( 'Select different department', 'sprout-invoices' ) ?></span>
		</a>

		<div id="department_div" class="control_wrap hide-if-js">
			<div class="department-wrap">
				<?php if ( count( $departments ) > 1 ): ?>
					<select name="doc_department">
						<?php foreach ( $departments as $department_key => $department_name ): ?>
							<?php printf( '<option value="%s" %s>%s</option>', $department_key, selected( $department_key, $default, false ), $department_name ) ?>
						<?php endforeach ?>
					</select>
				<?php else: ?>
					<span><?php printf( __( 'No <a href="%s" target="_blank">Invoicing Departments</a> Found', 'sprout-invoices' ), admin_url( 'edit-tags.php?taxonomy=si_department&post_type=sa_invoice' ) ) ?></span>
					<input name="doc_department" value="<?php echo esc_attr( $default ) ?>" type="hidden" />
				<?php endif ?>
	 		</div>
			<p>
				<a href="#edit_department" class="save_control save-department hide-if-no-js button"><?php _e( 'OK', 'sprout-invoices' ) ?></a>
				<a href="#edit_department" class="cancel_control cancel-department hide-if-no-js button-cancel"><?php _e( 'Cancel', 'sprout-invoices' ) ?></a>
			</p>
	 	</div>
</div>