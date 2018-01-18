<?php 
	$option = __( 'Not Restricted', 'sprout-invoices' );
	if ( $password != '' ) {
		$option = __( 'Password Required', 'sprout-invoices' );
	}
	elseif ( $force_login ) {
		$option = __( 'Login Required', 'sprout-invoices' );
	}
	 ?>
<div class="misc-pub-section" data-edit-id="force_login" data-edit-type="select">
	<span id="force_login" class="wp-media-buttons-icon"><b><?php echo esc_html( $option ) ?></b> <span title="<?php _e( 'Require a password or the client to login to view this document.', 'sprout-invoices' ) ?>" class="helptip"></span></span>

		<a href="#edit_force_login" class="edit-force_login hide-if-no-js edit_control" >
			<span aria-hidden="true"><?php _e( 'Edit', 'sprout-invoices' ) ?></span> <span class="screen-reader-text"><?php _e( 'Require a password or the client to login to view this document.', 'sprout-invoices' ) ?></span>
		</a>

		<div id="force_login_div" class="control_wrap hide-if-js">
			<div class="force_login-wrap">
				<?php sa_admin_fields( $fields, 'force_login' ); ?>
	 		</div>
			<p>
				<a href="#edit_force_login" class="save_control save-force_login hide-if-no-js button"><?php _e( 'OK', 'sprout-invoices' ) ?></a>
				<a href="#edit_force_login" class="cancel_control cancel-force_login hide-if-no-js button-cancel"><?php _e( 'Cancel', 'sprout-invoices' ) ?></a>
			</p>
	 	</div>
</div>