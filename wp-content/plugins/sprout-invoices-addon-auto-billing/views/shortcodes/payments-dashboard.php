<div id="client_billing_button_wrap">
	<button id="show_payment_sources_management" class="button"><?php _e( 'Manage Payment Sources' , 'sprout-invoices' ) ?></button>
</div>

<div id="client_billing_fields" class="admin_fields si_clearfix">
	<form id="payment_options_update_<?php echo $client_id ?>" class="sa-form sa-form-stacked payment_options_update" action="" method="post">

		<div class="payment_source_selection sa-control-group si_clearfix">
			<span class="input_wrap si_clearfix">
				<span class="sa-form-field sa-form-field-radios sa-form-field-required">
					<?php if ( ! empty( $payment_profiles ) ) : ?>
						<?php foreach ( $payment_profiles as $payment_profile_id => $name ) : ?>
							<div class="sa-form-field-radio si_clearfix">
								<label for="sa_credit_payment_method_<?php echo $payment_profile_id ?>">
									<input type="radio" name="sa_credit_payment_method" id="sa_credit_payment_method_<?php echo $payment_profile_id ?>" value="<?php echo $payment_profile_id ?>" <?php checked( $default_payment_profile_id, $payment_profile_id ) ?>>&nbsp;<?php printf( '%1$s <a href="javascript:void(0)" data-ref="%2$s" data-client-id="%4$s" class="cim_delete_card" title="%3$s"><span class="dashicons dashicons-trash"></span></a>', $name, $payment_profile_id, __( 'Remove this CC from your account.' , 'sprout-invoices' ), (int) $client_id ) ?>
								</label>
							</div>
						<?php endforeach ?>
						<div class="sa-form-field-radio si_clearfix">
							<label for="sa_credit_payment_method_0">
								<input type="radio" name="sa_credit_payment_method" id="sa_credit_payment_method_0" value="0" <?php checked( $default_payment_profile_id, 0 ) ?>>&nbsp;<?php _e( 'Auto Pay Disabled', 'sprout-invoices' ) ?>
							</label>
						</div>
					<?php endif ?>
					<div class="sa-form-field-radio si_clearfix">
						<label for="sa_credit_payment_method_credit">
						<input type="radio" name="sa_credit_payment_method" value="new_credit" id="sa_credit_payment_method_credit">&nbsp;<?php _e( 'New credit card' , 'sprout-invoices' ) ?></label>
					</div>
					<?php if ( SI_Auto_Payment_Processors::bank_supported() ) : ?>
						<div class="sa-form-field-radio si_clearfix">
							<label for="sa_credit_payment_method_bank">
							<input type="radio" name="sa_credit_payment_method" value="new_bank" id="sa_credit_payment_method_bank">&nbsp;<?php _e( 'New bank' , 'sprout-invoices' ) ?></label>
						</div>
					<?php endif; ?>
					
				</span>
			</span>
		</div>

		<div class="credit_card_fields">
			<?php
				$billing_fields = SI_Credit_Card_Processors::get_standard_address_fields();
				$cc_fields = SI_Credit_Card_Processors::default_credit_fields();
					?>
			<div class="billing_cc_fields si_clearfix">
				<fieldset class="billing_fields sa-fieldset si_clearfix">
					<legend><?php _e( 'Billing' , 'sprout-invoices' ) ?></legend>
					<?php sa_form_fields( $billing_fields, 'billing' ); ?>
				</fieldset>
				<fieldset class="credit_fields sa-fieldset si_clearfix">
					<legend><?php _e( 'Credit Card' , 'sprout-invoices' ) ?></legend>
					<?php sa_form_fields( $cc_fields, 'credit' ); ?>
				</fieldset>

				<?php if ( SI_Auto_Payment_Processors::bank_supported() ) : ?>
					<?php
						$bank_fields = SI_Sprout_Billings_Checkout::checking_account_fields();
						unset( $bank_fields['section_heading'] );
						unset( $bank_fields['store_payment_profile'] );
							?>
					<fieldset class="bank_fields sa-fieldset si_clearfix">
						<legend><?php _e( 'Bank Info' , 'sprout-invoices' ) ?></legend>
						<?php sa_form_fields( $bank_fields, 'credit' ); ?>
					</fieldset>
				<?php endif ?>

			</div><!-- #billing_cc_fields -->
		</div>
		<div id="manage_payment_source_submit_wrap">
			<input type="hidden" name="payments_action" value="save_payment_option" />
			<input type="hidden" name="client_id" value="<?php echo $client_id ?>" />
			<button type="submit" class="button button-primary credit_card_submit"><?php _e( 'Submit' , 'sprout-invoices' ) ?></button>
		</div>
	</form>
</div>
