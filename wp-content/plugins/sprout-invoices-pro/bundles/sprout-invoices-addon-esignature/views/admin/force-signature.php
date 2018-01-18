<?php
$option = __( 'No Signatured Required', 'sprout-invoices' );
if ( $force_signature ) {
	$option = __( 'Signature Required', 'sprout-invoices' );
} ?>
<div class="misc-pub-section" data-edit-id="force_signature" data-edit-type="select">
	<span id="force_signature" class="wp-media-buttons-icon"><b><?php echo esc_html( $option ) ?></b> <span title="<?php _e( 'Require a signature for this document.', 'sprout-invoices' ) ?>" class="helptip"></span></span>

		<a href="#edit_force_signature" class="edit-force_signature hide-if-no-js edit_control" >
			<span aria-hidden="true"><?php _e( 'Edit', 'sprout-invoices' ) ?></span> <span class="screen-reader-text"><?php _e( 'Require a password or the client to signature to view this document.', 'sprout-invoices' ) ?></span>
		</a>

		<div id="force_signature_div" class="control_wrap hide-if-js">
			<div class="force_signature-wrap">
				<?php sa_admin_fields( $fields, 'force_signature' ); ?>
	 		</div>
			<p>
				<a href="#edit_force_signature" class="save_control save-force_signature hide-if-no-js button"><?php _e( 'OK', 'sprout-invoices' ) ?></a>
				<a href="#edit_force_signature" class="cancel_control cancel-force_signature hide-if-no-js button-cancel"><?php _e( 'Cancel', 'sprout-invoices' ) ?></a>
			</p>
	 	</div>
</div>
