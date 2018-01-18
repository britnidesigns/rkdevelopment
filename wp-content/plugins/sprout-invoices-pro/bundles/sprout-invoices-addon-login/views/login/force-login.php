<?php

/**
 * DO NOT EDIT THIS FILE! Instead customize it via a theme override.
 *
 * Any edit will not be saved when this plugin is upgraded. Not upgrading will prevent you from receiving new features,
 * limit our ability to support your site and potentially expose your site to security risk that an upgrade has fixed.
 *
 * https://sproutapps.co/support/knowledgebase/sprout-invoices/customizing-templates/
 *
 * You find something that you're not able to customize? We want your experience to be awesome so let support know and we'll be able to help you.
 *
 */

do_action( 'pre_si_invoice_view' ); ?><!DOCTYPE html>
<html <?php language_attributes(); ?>>
	<head>
		<meta charset="<?php bloginfo( 'charset' ); ?>" />
		<meta name="viewport" content="width=device-width, initial-scale=1">
		<link rel="profile" href="http://gmpg.org/xfn/11" />
		<?php si_head(); ?>
		<meta name="robots" content="noindex, nofollow" />
	</head>

	<body id="invoice" <?php body_class(); ?>>

		<div id="outer_doc_wrap" class="<?php if ( SI_Login::is_pass_protected() ) { echo 'pass_protected'; } ?>">
		
			<div id="login_wrap">

				<?php if ( SI_Login::is_pass_protected() ) : ?>
					<p><?php _e( '<b>Welcome!</b><br/>This document is password protected.', 'sprout-invoices' ) ?></p>
					<form id="password_protection" action="<?php echo get_the_permalink() ?>" method="post">
						<p>
							<input name="<?php echo SI_Login::PASSWORD_INPUT ?>" placeholder="<?php _e( 'Enter Password', 'sprout-invoices' ) ?>" id="doc_password" />
						</p>
						<p>
							<button type="submit" class="button-primary"><?php _e( 'Submit', 'sprout-invoices' ) ?></button>
						</p>
					</form>
				<?php else : ?>
					<div id="login_form">
						<p><?php _e( '<b>Welcome!</b><br/>Please sign-in to view your document.', 'sprout-invoices' ) ?></p>

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
					</div><!-- #login_form -->
				<?php endif ?>

			</div><!-- #login_wrap -->
		</div>

	</body>
	<?php si_footer() ?>
</html>