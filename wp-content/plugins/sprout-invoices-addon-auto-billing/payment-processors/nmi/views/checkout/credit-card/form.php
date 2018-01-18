<div id="credit_card_checkout_wrap">
	<?php do_action( 'si_credit_card_checkout_wrap' ) ?>
	<form action="<?php echo si_get_credit_card_checkout_form_action() ?>" method="post" accept-charset="utf-8" class="sa-form sa-form-aligned" id="si_credit_card_form">
		<?php do_action( 'si_billing_credit_card_form', $checkout ) ?>
		<div id="billing_cc_fields" class="clearfix">
			<fieldset id="billing_fields" class="sa-fieldset">
				<legend><?php _e( 'Billing', 'sprout-invoices' ) ?></legend>
				<?php sa_form_fields( $billing_fields, 'billing' ); ?>
				<?php do_action( 'si_billing_payment_fields', $checkout ) ?>
			</fieldset>
			<fieldset id="credit_card_fields" class="sa-fieldset">

				<div class="sa-control-group">

					<?php
					if ( ! empty( $cards ) ) : ?>
						<?php foreach ( $cards as $payment_profile_id => $name ) : ?>
						<div class="input_wrap">
							<span class="sa-form-field sa-form-field-radios sa-form-field-required">
								<span class="sa-form-field-radio clearfix">
									<label for="sa_credit_payment_method_<?php echo $payment_profile_id ?>">
										<input type="radio" name="sa_credit_payment_method" id="sa_credit_payment_method_<?php echo $payment_profile_id ?>" value="vault"><?php printf( '%2$s', __( 'Previously used' , 'sprout-invoices' ), $name, $payment_profile_id, __( 'Remove this CC from your account.' , 'sprout-invoices' ), (int) $invoice_id ) ?>
									</label>
								</span>
								<?php do_action( 'si_vault_payment_fields', $checkout ) ?>
							</span>
						</div>
						<?php endforeach ?>
					<?php endif ?>
					<div class="input_wrap">
						<span class="sa-form-field sa-form-field-radios sa-form-field-required">
							<span class="sa-form-field-radio clearfix">
								<label for="sa_credit_payment_method_bank">
								<input type="radio" name="sa_credit_payment_method" id="sa_credit_payment_method_bank" value="bank" checked="checked"><b><?php _e( 'Pay with Checking Account' , 'sprout-invoices' ) ?></b></label>
							</span>
							<p class="bank_info info_message"><?php printf( __( ' Enter your bank details below:', 'sprout-invoices' ), sa_get_formatted_money( $payment_amount ) ) ?></p>
							<?php sa_form_fields( $bank_fields, 'bank' ); ?>
							<?php do_action( 'si_bank_payment_fields', $checkout ) ?>
						</span>
					</div>
					<div class="input_wrap">
						<span class="sa-form-field sa-form-field-radios sa-form-field-required">
							<span class="sa-form-field-radio clearfix">
								<label for="sa_credit_payment_method_credit">
								<input type="radio" name="sa_credit_payment_method" id="sa_credit_payment_method_credit" value="credit"><b><?php _e( 'Credit/Debit Card' , 'sprout-invoices' ) ?></b></label>
							</span>
							<p class="cc_info info_message"><?php printf( __( 'There is a %s convienence fee added to each credit/debit payment.', 'sprout-invoices' ), $convenience_percentage . '%' ) ?></p>
							<?php sa_form_fields( $cc_fields, 'credit' ); ?>
							<?php do_action( 'si_credit_card_payment_fields', $checkout ) ?>
						</span>
					</div>
					<div class="input_wrap">
						<span class="sa-form-field sa-form-field-radios sa-form-field-required">
							<span class="sa-form-field-radio clearfix">
								<label for="sa_credit_payment_method_check">
								<input type="radio" name="sa_credit_payment_method" id="sa_credit_payment_method_check" value="check"><b><?php _e( 'Pay by Check' , 'sprout-invoices' ) ?></b></label>
							</span>
							<p class="check_info info_message"><?php printf( __( 'Please give a check made out for %s to your Building Manager and they will process this payment for you.', 'sprout-invoices' ), sa_get_formatted_money( $payment_amount ) ) ?></p>
						</span>
					</div>
				</div>

				<div class="invoice_totals_wrap">
					<div class="subtotal cc_info">
						<b><?php _e( 'Subtotal', 'sprout-invoices' ) ?></b>&nbsp;<?php sa_formatted_money( $payment_amount, $invoice_id, '<span class="money_amount">%s</span>' )  ?>
					</div>
					<div class="service_fee cc_info">
						<b><?php _e( 'Convenience Fee', 'sprout-invoices' ) ?></b>&nbsp;<?php sa_formatted_money( $convenience_fee, $invoice_id, '<span class="money_amount">%s</span>' )  ?>
					</div>
					<div class="total_with_fee cc_info">
						<b><?php _e( 'Total', 'sprout-invoices' ) ?></b>&nbsp;<?php sa_formatted_money( $payment_amount + $convenience_fee, $invoice_id, '<span class="money_amount">%s</span>' )  ?>
					</div>
					<div class="total_without_fee bank_info">
						<b><?php _e( 'Total', 'sprout-invoices' ) ?></b>&nbsp;<?php sa_formatted_money( $payment_amount, $invoice_id, '<span class="money_amount">%s</span>' )  ?>
					</div>
				</div>


			</fieldset>
			<?php remove_action( 'si_credit_card_form_controls', array( 'SI_Auto_Billing_Client_Set_Payment', 'client_set_price_view' ) ); ?>
			<?php do_action( 'si_credit_card_form_controls', $checkout ) ?>

			<?php do_action( 'si_credit_card_payment_controls', $checkout ) ?>

			<input type="hidden" name="<?php echo SI_Checkouts::CHECKOUT_ACTION ?>" value="<?php echo SI_Checkouts::PAYMENT_PAGE ?>" />
			<button type="submit" class="button button-primary" id="credit_card_submit"><?php _e( 'Submit Payment', 'sprout-invoices' ) ?></button>

			
		</div><!-- #billing_cc_fields -->
		<?php do_action( 'si_billing_credit_card_form_bottom', $checkout ) ?>
	</form>
</div><!-- #credit_card_checkout_wrap -->
<?php do_action( 'si_credit_card_checkout_post_wrap' ) ?>
