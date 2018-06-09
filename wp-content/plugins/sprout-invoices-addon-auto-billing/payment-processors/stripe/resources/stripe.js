var $ = jQuery.noConflict();
jQuery(function($) {
	$(document).ready(function($) {

		// init stripe with pub key
		Stripe.setPublishableKey( si_stripe_js_object.pub_key );

		// non ajaxed
		$('body').on('submit', '#si_credit_card_form', function(event) {
			// If there's no charge token than use stripe api to get one
			if ( $('[name="'+si_stripe_js_object.token_input+'"]').val().length === 0 ) {
				$payment_method = $('input[name=sa_credit_payment_method]:checked').val();
				if ( 'new_credit' == $payment_method || 'new_bank' == $payment_method ) {
					event.preventDefault();
					si_stripe_process_card();
				}
			};
		});

	});

	function si_stripe_process_card() {

		// disable the submit button to prevent repeated clicks
		$('#credit_card_checkout_wrap #credit_card_submit').attr('disabled', 'disabled');

		var year = $('#sa_credit_cc_expiration_year').val();

		// createToken returns immediately - the supplied callback submits the form if there are no errors
		Stripe.createToken({
			number: 	     $('#sa_credit_cc_number').val(),
			name: 		     $('#sa_credit_cc_name').val(),
			cvc: 		     $('#sa_credit_cc_cvv').val(),
			exp_month:       $('#sa_credit_cc_expiration_month').val(),
			exp_year: 	     year.substr( year.length - 2 ), // truncate to last two
			address_line1: 	 $('#sa_billing_street').val(),
			address_line2: 	 '',
			address_city: 	 $('#sa_billing_city').val(),
			address_zip: 	 $('#sa_billing_postal_code').val(),
			address_state: 	 $('#sa_billing_zone').val(),
			address_country: $('#billing_country').val()
		}, si_stripe_response_handler);

		return false; // submit form callback
	}

	function si_stripe_response_handler(status, response) {
		
		if (response.error) {
			// re-enable the submit button
			$('#credit_card_checkout_wrap #credit_card_submit').attr("disabled", false);

			var error = '<p class="si_error">' + response.error.message + '</p>';

			$('#stripe_errors').addClass('sa-message error');

			// show the errors on the form
			$('#stripe_errors').html(error);

		} else {
			var $form = $("#si_credit_card_form"),			
				$token = response['id']; // token contains id, last4, and card type

			// insert the token into the form so it gets submitted to the server
			$('[name="'+si_stripe_js_object.token_input+'"]').val($token);

			// Clear out CC input fields just in case the name attribute was added
			$('#sa_credit_cc_number').val('');
			$('#sa_credit_cc_name').val('');
			$('#sa_credit_cc_cvv').val('');
			$('#sa_credit_cc_expiration_month').val('');

			// and submit
			$form.submit();
		}
	}

});
