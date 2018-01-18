<section id="doc_line_items_wrap" class="si_clearfix">
					
	<div id="doc_line_items" class="si_clearfix">
		
		<ol id="items">

			<?php do_action( 'si_document_line_items' ) ?>

			<?php foreach ( $line_items as $position => $item_data ) : ?>

				<?php if ( is_int( $position ) ) : // is not a child ?>

					<?php
						$children = si_line_item_get_children( $position, $line_items );
						$has_children = ( ! empty( $children ) ) ? true : false ;
						$item_type = ( isset( $item_data['type'] ) ) ? $item_data['type'] : SI_Line_Items::get_default_type(); ?>

					<li class="item item_type_<?php echo esc_attr( $item_type ) ?> <?php if ( $has_children ) { echo esc_attr( 'line_item_has_children' ); } ?>" data-id="<?php echo (float) $position ?>">

						<?php si_front_end_line_item( $item_data, $position, $prev_type, $has_children ) ?>

						<?php if ( $has_children ) : ?>

							<ol class="items_list child_items">

								<?php foreach ( $children as $child_position => $item_data ) : ?>

									<li class="item sub_item item_type_<?php echo esc_attr( $item_type ) ?>" data-id="<?php echo (float) $child_position ?>">
										<?php si_front_end_line_item( $line_items[ $child_position ], $child_position, $prev_type, false ) ?>
									</li>
									
									<?php $prev_type = $item_type; ?>

								<?php endforeach ?>

							</ol>

						<?php endif ?>

						<?php $prev_type = $item_type; ?>

					</li>

				<?php endif ?>

			<?php endforeach ?>

		</ol>

		<footer id="line_items_footer" class="si_clearfix">
			
			<section id="doc_payment_wrap" class="si_clearfix">

				<?php if ( si_get_invoice_balance() && si_get_invoice_status() != 'write-off' ) : ?>
					
					<?php
						$payment_options = si_payment_options();
						$payment_string = ( si_has_invoice_deposit() ) ? __( 'Pay Deposit', 'sprout-invoices' ) : __( 'Pay Invoice', 'sprout-invoices' );
						?>
					<?php if ( count( $payment_options ) === 0 ) : ?>
						<!-- no payment options -->
					<?php elseif ( count( $payment_options ) === 1 ) : ?>
						<?php foreach ( $payment_options as $slug => $options ) : ?>
							<?php if ( isset( $options['purchase_button_callback'] ) ) : ?>
								<?php call_user_func_array( $options['purchase_button_callback'], array( get_the_ID() ) ) ?>
							<?php else : ?>
								<a href="<?php echo esc_url_raw( si_get_payment_link( get_the_ID(), $slug ) ) ?>" data-slug="<?php esc_attr_e( $slug ) ?>" data-id="<?php the_ID() ?>" data-nonce="<?php echo wp_create_nonce( SI_Controller::NONCE ) ?>" class="button primary_button payment_option <?php if ( si_is_cc_processor( $slug ) ) { echo 'cc_processor'; } ?> <?php echo esc_attr( $slug ); ?>"><?php echo esc_html( $payment_string ); ?></a>
							<?php endif ?>
						<?php endforeach ?>
					<?php else : ?>
						<div id="payment_selection" class="si_clearfix">
							<ul class="inline_payment_options">
								<?php foreach ( $payment_options as $slug => $options ) : ?>
									<li id="<?php esc_attr_e( $slug ) ?>" class="payment_option">
										<?php if ( isset( $options['purchase_button_callback'] ) ) : ?>
											<?php call_user_func_array( $options['purchase_button_callback'], array( get_the_ID() ) ) ?>
										<?php else : ?>
											<a href="<?php echo esc_url_raw( si_get_payment_link( get_the_ID(), $slug ) ) ?>" data-slug="<?php esc_attr_e( $slug ) ?>" data-id="<?php the_ID() ?>" data-nonce="<?php echo wp_create_nonce( SI_Controller::NONCE ) ?>" class="payment_option <?php if ( si_is_cc_processor( $slug ) ) { echo 'cc_processor'; } ?> <?php echo esc_attr( $slug ) ?>">
												<?php if ( isset( $options['icons'] ) ) : ?>
													<?php foreach ( $options['icons'] as $path ) : ?>
														<img src="<?php echo esc_url_raw( $path ) ?>" alt="<?php esc_attr_e( $options['label'], 'sprout-invoices' ) ?>" height="48" />
													<?php endforeach ?>
												<?php else : ?>
													<span class="process_label"><?php esc_attr_e( $options['label'], 'sprout-invoices' ) ?></span>
												<?php endif ?>
											</a>
										<?php endif ?>
									</li>
								<?php endforeach ?>
							</ul>
						</div>
					<?php endif ?>
					
				<?php endif ?>

			</section>

			<div id="line_items_totals">

				<?php foreach ( $totals as $slug => $items_total ) : ?>

					<?php if ( isset( $items_total['hide'] ) && $items_total['hide'] ) : ?>
						<?php continue; ?>
					<?php endif ?>


					<div id="line_<?php echo esc_attr( $slug ) ?>">
						
						<?php if ( isset( $items_total['helptip'] ) ) : ?>
							<b title="<?php echo esc_attr( $items_total['helptip'] ) ?>" class="helptip"><?php echo $items_total['label'] ?></b>
						<?php else : ?>
							<b><?php echo $items_total['label'] ?></b>
						<?php endif ?>

						<?php echo $items_total['formatted'] ?>

					</div>

				<?php endforeach ?>

			</div>

		</footer>

	</div><!-- #doc_line_items -->

</section>
