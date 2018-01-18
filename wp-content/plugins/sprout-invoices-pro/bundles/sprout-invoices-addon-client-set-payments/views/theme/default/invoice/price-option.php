<style type="text/css">
	#modify_deposit_amount_submit {
		display: none;
		scale: .5;
	}
</style>
<script type="text/javascript">
	//<![CDATA[
	jQuery(document).ready( function($) {
		$("#modify_deposit_amount_input").live( 'keyup', function(e) {
			var payment = parseFloat( $( this ).val() ),
				balance = parseFloat( '<?php echo $max_payment ?>' );

			$( '#sa_checks_amount' ).val( payment );
			$( '#sa_popayments_amount' ).val( payment );
			$( '#sa_bacs_amount' ).val( payment );

			if ( payment > balance ) {
				$( this ).val( balance );
				$( '#sa_checks_amount' ).val( balance );
				$( '#sa_popayments_amount' ).val( balance );
				$( '#sa_bacs_amount' ).val( balance );
			};

			$( '.row.toggles' ).hide();
			$( '#modify_deposit_amount_submit' ).show();

		});

		$( '#modify_deposit_amount_submit' ).on('click', function(event) {
			event.preventDefault();

			var $button = $(this),
				$input = $("#modify_deposit_amount_input"),
				payment_amount = $input.val(),
				invoice_id = $("[name='invoice_id']").val();

			if( $button.data('working') == 1 ) return;

			$button.data( 'working', 1 );

			$button.after(si_js_object.inline_spinner);
			$.post( si_js_object.ajax_url, { action: '<?php echo Client_Set_Price::AJAX_ACTION ?>', invoice_id: invoice_id, payment_amount: payment_amount, nonce: si_js_object.security },
				function( response ) {

					$button.hide();
					$('.spinner').hide();

					if ( response.success ) {
						console.log( response );
						//$button.after('<p class="inline_message inline_success_message">' + response.data.message + '</p>');
					}
					else {
						$button.after('<span class="inline_message inline_error_message">' + response.data.message + '</span>');
					};

					$button.data( 'working', 0 );
					$( '.row.toggles' ).fadeIn();
				}
			);
		});
	});
	//]]>

</script>
<div class="row description">
	
	<p id="modify_deposit_amount_wrap"><?php _e( 'You may change the payment amount:' , 'sprout-invoices' ) ?>&nbsp;<?php sa_currency_symbol() ?><input type="number" step="any" id="modify_deposit_amount_input" name="deposit_amount" value="<?php si_number_format( $payment_amount ) ?>" min="<?php echo $min_payment ?>" max="<?php echo $max_payment ?>" />
		<input type="hidden" name="invoice_id" value="<?php the_ID() ?>" />&nbsp;<button type="submit" id="modify_deposit_amount_submit" class="button"><?php _e( 'Save Payment Amount', 'sprout-invoices' ) ?></button>
	</p>
</div>
