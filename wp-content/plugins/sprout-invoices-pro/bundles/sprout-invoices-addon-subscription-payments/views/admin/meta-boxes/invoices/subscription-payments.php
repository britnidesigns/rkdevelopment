<?php if ( is_a( $recurring_payment, 'SI_Payment' ) ) :  ?>

	<?php do_action( 'recurring_payments_profile_info', $recurring_payment ) ?>

	<p><?php printf( '<a class="payments_link button" title="%s" href="%s&s=%s">%s</a>', __( 'Review Payment', 'sprout-invoices' ), get_admin_url( '','/edit.php?post_type=sa_invoice&page=sprout-apps/invoice_payments' ), $recurring_payment->get_id(), __( 'Recurring Payment', 'sprout-invoices' ) ); ?></p>

<?php else : ?>
	<div id="recurring_invoice_options_wrap" class="admin_fields clearfix">
		<?php sa_admin_fields( $fields, 'recurring_payments' ); ?>
	</div>
<?php endif ?>

<?php if ( ! empty( $children ) ) :  ?>
	<b><?php _e( 'Generation History', 'sprout-invoices' ) ?></b>
	<ul>
		<?php foreach ( $children as $c_invoice_id ) :  ?>
			<li><?php printf( '%s &mdash; <a href="%s">%s</a>', get_post_time( get_option( 'date_format' ) . ' @ ' . get_option( 'time_format' ), false, $c_invoice_id ), get_edit_post_link( $c_invoice_id ), get_the_title( $c_invoice_id ) ); ?></li>
		<?php endforeach ?>
	</ul>
<?php elseif ( strtotime( $next_time ) > current_time( 'timestamp' ) ) : ?>
	<b><?php _e( 'Generation History', 'sprout-invoices' ) ?></b>
	<p><?php printf( 'The first invoice reciept will be generated <em>%s</em>.', date_i18n( get_option( 'date_format' ), strtotime( $next_time ) ) ) ?></p>
<?php endif ?>
