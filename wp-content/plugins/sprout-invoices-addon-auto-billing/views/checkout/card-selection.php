<div class="sa-control-group ">
	<span class="label_wrap">
		<label for="sa_credit_payment_method"><?php _e( 'Payment Method' , 'sprout-invoices' ) ?></label> <span class="required">*</span>
	</span>
	<span class="input_wrap">
		<span class="sa-form-field sa-form-field-radios sa-form-field-required">
			<?php
			if ( ! empty( $cards ) ) : ?>
				<?php foreach ( $cards as $payment_profile_id => $name ) : ?>
					<span class="sa-form-field-radio clearfix">
						<label for="sa_credit_payment_method_<?php echo $payment_profile_id ?>">
							<input type="radio" name="sa_credit_payment_method" id="sa_credit_payment_method_<?php echo $payment_profile_id ?>" value="<?php echo $payment_profile_id ?>"><?php printf( '%2$s <a href="javascript:void(0)" data-ref="%3$s" data-invoice-id="%5$s" class="cim_delete_card" title="%4$s"><span class="dashicons dashicons-trash"></span></a>', __( 'Previously used' , 'sprout-invoices' ), $name, $payment_profile_id, __( 'Remove this CC from your account.' , 'sprout-invoices' ), (int) $invoice_id ) ?>
						</label>
					</span>
				<?php endforeach ?>
			<?php endif ?>
			<span class="sa-form-field-radio clearfix">
				<label for="sa_credit_payment_method_credit">
				<input type="radio" name="sa_credit_payment_method" id="sa_credit_payment_method_credit" value="new_credit" checked="checked"><b><?php _e( 'New Credit/Debit Card' , 'sprout-invoices' ) ?></b></label>
			</span>
		</span>
	</span>
</div>
