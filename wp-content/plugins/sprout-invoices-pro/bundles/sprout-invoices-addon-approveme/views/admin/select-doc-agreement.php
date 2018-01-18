<?php
	$selected = ( isset( $agreements[$default] ) ) ? $agreements[$default] : __( 'No Agreement', 'sprout-invoices' );
		?>
<div class="misc-pub-section" data-edit-id="agreement" data-edit-type="select">
	
	<span id="agreement" class="wp-media-buttons-icon"><b><?php echo esc_html__( $selected, 'sprout-invoices' ) ?></b> <span title="<?php _e( 'Select an agreement.', 'sprout-invoices' ) ?>" class="helptip"></span></span>

		<a href="#edit_agreement" class="edit-agreement hide-if-no-js edit_control" >
			<span aria-hidden="true"><?php _e( 'Edit', 'sprout-invoices' ) ?></span> <span class="screen-reader-text"><?php _e( 'Select different agreement', 'sprout-invoices' ) ?></span>
		</a>

		<div id="agreement_div" class="control_wrap hide-if-js">
			<div class="agreement-wrap">
				<?php if ( count( $agreements ) > 1 ) : ?>
					<select name="doc_agreement">
						<?php foreach ( $agreements as $agreement_key => $agreement_name ) : ?>
							<?php printf( '<option value="%s" %s>%s</option>', $agreement_key, selected( $agreement_key, $default, false ), $agreement_name ) ?>
						<?php endforeach ?>
					</select>
				<?php else : ?>
					<span><?php printf( __( 'No <a href="%s" target="_blank">Agreements</a> Found', 'sprout-invoices' ), admin_url( 'admin.php?page=esign-view-document' ) ) ?></span>
					<input name="doc_agreement" value="<?php echo esc_attr( $default ) ?>" type="hidden" />
				<?php endif ?>
	 		</div>
			<p>
				<a href="#edit_agreement" class="save_control save-agreement hide-if-no-js button"><?php _e( 'OK', 'sprout-invoices' ) ?></a>
				<a href="#edit_agreement" class="cancel_control cancel-agreement hide-if-no-js button-cancel"><?php _e( 'Cancel', 'sprout-invoices' ) ?></a>
			</p>
	 	</div>
</div>