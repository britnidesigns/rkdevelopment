<div id="si_dashboard">
	<p><?php _e( 'Welcome. Please sign-in to view your client dashboard.', 'sprout-invoices' ) ?></p>
	<div id="si_dashboard_form" class="form_wrap">
		<?php
			$args = array(
				'form_id'        => 'si_login_form',
				'label_username' => __( 'E-Mail', 'sprout-invoices' ),
				'label_password' => __( 'Password', 'sprout-invoices' ),
				'label_log_in'   => __( 'Sign-in', 'sprout-invoices' ),
				'id_submit'      => 'login_button',
				'value_username' => ( isset( $_GET['u'] ) && $_GET['u'] != '' ) ? $_GET['u'] : '',
				'remember'       => false,
			);
			wp_login_form( $args ); ?>
	</div>
</div><!-- #si_dashboard -->

