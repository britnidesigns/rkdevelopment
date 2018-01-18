<style type="text/css">
	.wp-core-ui .current_status.ready {
		color: #46c4f9!important;
    	border-color: #73e5ff!important;
	}
</style>
<?php printf( '<span class="si_status %s si_tooltip button current_status" title="%s"><span>%s</span>%s</span>', 'ready', __( 'Payment Due', 'sprout-invoices' ), __( 'Payment Due', 'sprout-invoices' ), '&nbsp;<div class="dashicons dashicons-arrow-down"></div>' ); ?>
