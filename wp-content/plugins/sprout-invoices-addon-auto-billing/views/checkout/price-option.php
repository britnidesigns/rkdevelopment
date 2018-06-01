<style type="text/css">
	#billing_cc_fields #modify_deposit_amount.sa-fieldset {
    	float: right;	
	}
	#billing_cc_fields .sa-form-field-radio input#si_payment_amount_input_option {
	    width: 5em;
	}
</style>
<script type="text/javascript">
	jQuery('[name="si_payment_amount_change"]').live('change', function(e) {
		var selection = jQuery( this ).val(),
			payment_option = jQuery('#si_payment_amount_input_option');

		payment_option.attr( 'disabled', 'disabled' );
		if ( selection === '1' ) {
			payment_option.removeAttr( 'disabled' );
		}

	});
	jQuery('#si_payment_option_wrap').live('click', function(e) {
		var selection = jQuery('#si_payment_amount_option'),
			payment_option = jQuery('#si_payment_amount_input_option');

		selection.attr( 'checked', true );
		payment_option.removeAttr( 'disabled' );
	});
	jQuery('[name="si_payment_amount_option"]').live( 'keyup', function(e) {
		var payment = parseFloat( jQuery( this ).val() ),
			balance = parseFloat( '<?php echo $balance ?>' );

		if ( payment > balance ) {
			jQuery('[name="si_payment_amount_option"]').val( balance );
		};
	});

</script>
<fieldset id="modify_deposit_amount" class="sa-fieldset">
	<legend><?php _e( 'Payment Amount' , 'sprout-invoices' ) ?></legend>
	<div id="modify_deposit_amount" class="sa-control-group">

		<span class="input_wrap">
			<span class="sa-form-field sa-form-field-radios">
				<span class="sa-form-field-radio clearfix">
					
					<label for="si_payment_amount_full" style="white-space: nowrap;">
						<input type="radio" name="si_payment_amount_change" id="si_payment_amount_full" value="0" checked="checked"> <?php _e( 'Total Due:' , 'sprout-invoices' ) ?> <?php sa_formatted_money( $balance ) ?>
					</label>
				</span>
				<span class="sa-form-field-radio clearfix">
					<label for="si_payment_amount_option" style="white-space: nowrap;">
						<input type="radio" name="si_payment_amount_change" id="si_payment_amount_option" value="1"> <?php _e( 'Other Amount:' , 'sprout-invoices' ) ?> <span id="si_payment_option_wrap"><?php sa_currency_symbol() ?><input type="text" name="si_payment_amount_option" placeholder="<?php echo esc_attr( $deposit ) ?>" id="si_payment_amount_input_option" disabled="disabled"></span>
					</label>
				</span>
			</span>
		</span>
	</div>
</fieldset>
