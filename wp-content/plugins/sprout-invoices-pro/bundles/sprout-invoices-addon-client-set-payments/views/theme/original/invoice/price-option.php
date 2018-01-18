<style type="text/css">
	form#modify_deposit_amount {
	    display: block;
  		text-align: right;
  		margin-top: 20px;
	}
	#modify_deposit_amount input#modify_deposit_amount_input {
	    padding: 5px;
	    display: none;
	}
	#modify_deposit_amount button#modify_deposit_amount_submit {
		display: none;
	}
	#modify_deposit_amount span#min_payment_display {
	  padding: 8px;
	}
	button#modify_deposit_amount_submit {
		font-size: .8em;
		padding: 8px;
		float: right;
		margin-left: 8px;
	}
	#modify_deposit_amount .helptip:after {
		content: '\f464';
	}
	div#doc_header_wrap {
	    height: 110px;
	}
</style>
<script type="text/javascript">
	//<![CDATA[
	jQuery(document).ready( function($) {
		$("#min_payment_display").on('click', function(event) {
			$(this).hide();
			$(".purchase_button").hide();
			$("#modify_deposit_amount_input").fadeIn();
			$("#modify_deposit_amount_submit").fadeIn();
		});
		$("#modify_deposit_amount").on('submit', function(event) {
			event.preventDefault();
			var $input = $("#modify_deposit_amount_input"),
				payment_amount = $input.val(),
				invoice_id = $("[name='invoice_id']").val();
			$.post( si_js_object.ajax_url, { action: '<?php echo Client_Set_Price::AJAX_ACTION ?>', invoice_id: invoice_id, payment_amount: payment_amount, nonce: si_js_object.security },
				function( response ) {
					if ( response.success ) {

						$("#modify_deposit_amount_input").hide();
						$("#modify_deposit_amount_submit").hide();

						$(".purchase_button").fadeIn();
						$("#min_payment_display").before('<span class="inline_message inline_success_message">' + response.data.message + '</span>');
					}
					else {
						$("#modify_deposit_amount_submit").after('<span class="inline_message inline_error_message">' + response.data.message + '</span>');
					};

					
				}
			);
		});
	});
	//]]>

</script>
<form id="modify_deposit_amount" action="modify_deposit_amount" method="post">
	<span id="min_payment_display" class="helptip" title="<?php _e( 'Click to change the deposit amount.', 'sprout-invoices' ) ?>"><?php printf( __( 'Payment amount: %s', 'sprout-invoices' ), sa_get_formatted_money( $payment_amount, get_the_ID() ) ) ?></span>
	<input type="number" step="any" id="modify_deposit_amount_input" name="deposit_amount" value="<?php echo $min_payment ?>" min="<?php echo $min_payment ?>" max="<?php echo $max_payment ?>" />
	<input type="hidden" name="invoice_id" value="<?php the_ID() ?>" />
	<button type="submit" id="modify_deposit_amount_submit" class="button"><?php _e( 'Save Payment Amount', 'sprout-invoices' ) ?></button>
</form>
