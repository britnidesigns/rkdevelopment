<div id="si_dashboard">

	<?php
	if ( $show_estimates ) {
		print SI_Client_Dashboard::load_addon_view( 'section/dashboard-estimates', array(
			'client_id' => $client_id,
			'invoices' => $invoices,
			'estimates' => $estimates,
		), true );
	} ?>

	<?php if ( $show_invoices ) {
			print SI_Client_Dashboard::load_addon_view( 'section/dashboard-invoices', array(
				'client_id' => $client_id,
				'invoices' => $invoices,
				'estimates' => $estimates,
			), true );
} ?>

</div><!-- #si_dashboard -->
