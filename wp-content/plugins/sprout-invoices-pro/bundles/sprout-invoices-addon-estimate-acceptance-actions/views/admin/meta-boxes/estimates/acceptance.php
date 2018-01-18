<div id="estimate_acceptance_options_wrap" class="admin_fields clearfix">
	<?php sa_admin_fields( $fields, 'estimate_acceptance' ); ?>
</div>

<script type="text/javascript">

	// initial
	if ( jQuery('#sa_estimate_acceptance_dont_create_invoice').is(':checked') ) {
		jQuery('#estimate_acceptance_options_wrap .form-group:not(#si_admin_field_estimate_acceptance_dont_create_invoice)').fadeOut();
	}

	// live update
	jQuery('#sa_estimate_acceptance_dont_create_invoice').on( 'click', function() {
		if ( jQuery(this).is(':checked') ) {
			jQuery('#estimate_acceptance_options_wrap .form-group:not(#si_admin_field_estimate_acceptance_dont_create_invoice)').fadeOut();
		}
		else {
			jQuery('#estimate_acceptance_options_wrap .form-group:not(#si_admin_field_estimate_acceptance_dont_create_invoice)').fadeIn();
		};
	} );
</script>