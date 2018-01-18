<?php if ( empty( $fields ) ) : ?>
	<b><?php _e( 'A client payment profile is either not setup or they have not yet agreed to the terms of automatic payments against their profile.' , 'sprout-invoices' ) ?></b>
<?php else : ?>
	<p><b><?php _e( 'A payment profile is setup and the client\'s payment profile can be charged.' , 'sprout-invoices' ) ?></b></p>
	<div id="invoice_billing_fields" class="admin_fields clearfix">
		<?php sa_admin_fields( $fields ); ?>
	</div>
<?php endif ?>
