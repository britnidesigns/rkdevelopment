<?php do_action( 'pre_si_invoice_view' ); ?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
<meta charset="<?php bloginfo( 'charset' ); ?>" />
<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
<title><?php the_title() ?> &ndash; RK Development</title>
<link rel="profile" href="http://gmpg.org/xfn/11" />
<link rel="icon" href="<?php echo site_url() ?>/wp-content/uploads/favicon.png" />
<script type="text/javascript" src="<?php echo site_url() ?>/wp-includes/js/jquery/jquery.js"></script>
<script type="text/javascript" src="<?php echo site_url() ?>/wp-includes/js/jquery/jquery-migrate.min.js"></script>
<link rel="stylesheet" href="<?php echo get_template_directory_uri(); ?>/css/common.css">
<?php si_head( true ); ?>
<script defer src="https://use.fontawesome.com/releases/v5.0.13/js/all.js" integrity="sha384-xymdQtn1n3lH2wcu0qhcdaOpQwyoarkgLVxC/wZ5q7h9gHtxICrpcaSUfygqZGOe" crossorigin="anonymous"></script>
<meta name="robots" content="noindex, nofollow" />
<script type="text/javascript">
    console.log('<?php the_title() ?>');
    console.log('<?php printf( __( 'Invoice %1$s', 'sprout-invoices' ), si_get_invoice_id() ) ?>');
</script>
</head>

<body id="invoice" <?php body_class( 'si_og_theme' ); ?>>
	<header class="site-header">
        <a href="<?php echo site_url() ?>/dashboard" class="custom-logo-link">
            <?php  $logo_url = ( get_theme_mod( 'si_logo' ) ) ? esc_url( get_theme_mod( 'si_logo', si_doc_header_logo_url() ) ) : esc_url( si_doc_header_logo_url() ); ?>
            <img src="<?php echo $logo_url ?>" alt="RK Development LC" class="custom-logo">
        </a>

        <nav class="main-navigation">
			<?php
                wp_nav_menu( array(
                    'theme_location' => 'tenant-nav',
                    'menu_id'        => 'primary-menu',
                    'container_class'=> 'primary-nav'
                ) );

                get_template_part('template-parts/user-menu');
            ?>
		</nav>
	</header>

    <div class="site-content">
        <h1><span><?php echo date('F Y', si_get_invoice_due_date( $invoice_id )) ?></span> Rent</h1>

        <div class="messages">
    		<?php si_display_messages(); ?>
    	</div>

        <div class="info">
            <section>
                <h3>Due</h3>
                <p class="due"><?php echo date('F j, Y', si_get_invoice_due_date( $invoice_id )) ?></p>
                <p class="issued">Issued <?php echo date('F j, Y', si_get_invoice_issue_date( $invoice_id )) ?></p>
            </section>

            <section class="status">
                <h3>Status</h3>

                <?php if ( 'write-off' === si_get_invoice_status() ) : ?>
                    <p><i class="fas fa-ban"></i><?php esc_html_e( 'Void', 'sprout-invoices' ) ?></p>
                <?php elseif ( ! si_get_invoice_balance() ) : ?>
                    <p class="paid"><i class="fas fa-check"></i><?php esc_html_e( 'Paid', 'sprout-invoices' ) ?></p>
                <?php elseif ( 'temp' === si_get_invoice_status() ) : ?>
                    <p><?php esc_html_e( 'Not Yet Published', 'sprout-invoices' ) ?></p>
                <?php elseif ( si_get_invoice_due_date( $invoice_id ) < current_time( 'timestamp' ) ) : ?>
                    <p class="overdue"><i class="fas fa-exclamation-triangle"></i><?php esc_html_e( 'Overdue', 'sprout-invoices' ) ?></p>
                <?php else : ?>
                    <p><?php esc_html_e( 'Balance Due', 'sprout-invoices' ) ?></p>
                <?php endif; ?>

                <?php if ( $last_updated = si_doc_last_updated() ) {
                    $days_since = si_get_days_ago( $last_updated );
                    $link_label = 2 > $days_since ? 'Recently Updated' : 'Updated %1$s Days Ago'; ?>

                    <a class="open text-link" href="#history">
                        <i class="fas fa-history"></i><?php printf( $link_label, $days_since ) ?>
                    </a>
                <?php } ?>
            </section>

            <section class="landlord">
                <h3>Landlord</h3>
                <?php si_doc_address() ?>
            </section>

            <?php if ( si_get_invoice_client_id() ) { ?>
                <section class="tenant">
                    <h3>Tenant</h3>
                    <p class="name"><?php echo get_the_title( si_get_invoice_client_id() ) ?></p>
                    <p><?php si_client_address( si_get_invoice_client_id() ) ?></p>
                </section>
            <?php } ?>
        </div>

        <?php do_action( 'si_doc_line_items', get_the_id() ) ?>

        <?php if ( si_get_invoice_balance() ) : ?>
    		<?php si_payment_options_view(); ?>
    	<?php else : ?>
    		<section class="action">
				<?php do_action( 'si_default_theme_inner_paybar' ) ?>

				<?php if ( 'complete' === si_get_invoice_status() ) :  ?>
					<?php printf( '<p>Total of <strong>%1$s</strong> has been <strong>paid</strong></p>', sa_get_formatted_money( si_get_invoice_total() ) ); ?>
				<?php else : ?>
					<?php printf( '<p>Total of <strong>%1$s</strong> has been <strong>reconciled</strong></p>', sa_get_formatted_money( si_get_invoice_total() ) ); ?>
				<?php endif ?>

				<?php do_action( 'si_default_theme_pre_no_payment_button' ) ?>
				<?php do_action( 'si_default_theme_no_payment_button' ) ?>
    		</section>
    	<?php endif ?>

        <section class="notes">
            <?php if ( strlen( si_get_invoice_notes() ) > 1 ) : ?>
				<h3><?php esc_html_e( 'Info &amp; Notes', 'sprout-invoices' ) ?></h3>
				<?php si_invoice_notes() ?>
			<?php endif ?>

            <?php if ( strlen( si_get_invoice_terms() ) > 1 ) : ?>
				<h3><?php esc_html_e( 'Terms &amp; Conditions', 'sprout-invoices' ) ?></h3>
				<?php si_invoice_terms() ?>
			<?php endif; ?>
        </section>
    </div>

	<?php if ( apply_filters( 'si_show_invoice_history', true ) ) : ?>
		 <section class="panel closed" id="history">
			<a class="close" href="#history">
			<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 512 512">
				<path d="M405 136.798L375.202 107 256 226.202 136.798 107 107 136.798 226.202 256 107 375.202 136.798 405 256 285.798 375.202 405 405 375.202 285.798 256z"/>
			</svg>
			</a>

			<div class="inner">
				<h2><?php _e( 'Invoice History', 'sprout-invoices' ) ?></h2>
				<div class="history">
					<?php foreach ( si_doc_history_records() as $item_id => $data ) : ?>
						<?php $days_since = (int) si_get_days_ago( strtotime( $data['post_date'] ) ); ?>
						<article class=" <?php echo esc_attr( $data['status_type'] ); ?>">
							<span class="posted">
								<?php
									$type = ( 'comment' === $data['status_type'] ) ? sprintf( __( 'Comment by %s ', 'sprout-invoices' ), $data['type'] ) : $data['type'] ;
										?>
								<?php if ( 0 === $days_since ) :  ?>
									<?php printf( '%1$s today', $type ) ?>
								<?php elseif ( 2 > $days_since ) :  ?>
									<?php printf( '%1$s %2$s day ago', $type, $days_since ) ?>
								<?php else : ?>
									<?php printf( '%1$s %2$s days ago', $type, $days_since ) ?>
								<?php endif ?>
							</span>

							<?php if ( SI_Notifications::RECORD === $data['status_type'] ) : ?>
								<p>
									<?php echo esc_html( $update_title ) ?>
									<br/><a href="#TB_inline?width=600&height=380&inlineId=notification_message_<?php echo (int) $item_id ?>" id="show_notification_tb_link_<?php echo (int) $item_id ?>" class="thickbox si_tooltip notification_message" title="<?php esc_html_e( 'View Message', 'sprout-invoices' ) ?>"><?php esc_html_e( 'View Message', 'sprout-invoices' ) ?></a>
								</p>
								<div id="notification_message_<?php echo (int) $item_id ?>" class="cloak">
									<?php echo wpautop( $data['content'] ) ?>
								</div>
							<?php elseif ( SI_Invoices::VIEWED_STATUS_UPDATE === $data['status_type'] ) : ?>
								<p>
									<?php echo $data['update_title']; ?>
								</p>
							<?php else : ?>
								<?php echo wpautop( $data['content'] ) ?>
							<?php endif ?>
						</article>
					<?php endforeach ?>
				</div>
			</div>
		</section>
	<?php endif ?>

	<div id="footer_credit">
		<?php do_action( 'si_document_footer_credit' ) ?>
		<!--<p><?php esc_attr_e( 'Powered by Sprout Invoices', 'sprout-invoices' ) ?></p>-->
	</div><!-- #footer_messaging -->

    <footer id="colophon" class="site-footer">
        <a href="https://www.facebook.com/apartmentsanddevelopment/" class="facebook">
            <i class="fab fa-facebook"></i>
            <span>Get updates about new properties</span>
        </a>
        <?php
            wp_nav_menu( array(
                'theme_location' => 'footer-nav',
                'container_class'=> 'nav'
            ) );
        ?>
		<p>&copy; 2018 RK Development LC</p>
	</footer>
</body>
<?php do_action( 'si_document_footer' ) ?>
<?php si_footer() ?>

<?php printf( '<!-- Template Version v%s -->', Sprout_Invoices::SI_VERSION ); ?>
</html>
<?php do_action( 'invoice_viewed' ) ?>
