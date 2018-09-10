var $ = jQuery.noConflict();
jQuery(function($) {

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

	function sbStoreAccountID( auth ) {
		console.log( auth );
		
		var $plaid_auth_button = $('#plaid_auth');

		$plaid_auth_button.hide();
		$plaid_auth_button.after( si_js_object.inline_spinner );
		console.log( ''+si_stripe_js_object.callback_action+'' );
		$.post( si_js_object.ajax_url, { action: ''+si_stripe_js_object.callback_action+'', public_token: auth.public_token, account_id: auth.account_id, nonce: si_js_object.security, invoice_id: si_js_object.invoice_id },
			function( response ) {

				if ( response.success ) {

					// insert the token into the form so it gets submitted to the server
					$('[name="'+si_stripe_js_object.token_input+'"]').val(response.data);
					// submit
					$("#si_credit_card_form").submit();

				}
				else {
					
					$('.si_inline_spinner').hide();
					$plaid_auth_button.show();
					
					$plaid_auth_button.after('<span class="inline_message inline_error_message">' + response.data.message + '</span>');
				};

			}
		);
	};

	if ( typeof Plaid !== 'undefined' ) {
		var plaidHandler = Plaid.create({
			env: ''+si_stripe_js_object.plaid_env+'',
			clientName: ''+si_stripe_js_object.clientName+'',
			key: ''+si_stripe_js_object.plaid_pub_key+'',
			product: 'auth',
			selectAccount: true,
			onLoad: function() {
				// finished loading button
			},
			onSuccess: function(public_token, metadata) {
				console.log('public_token: ' + public_token);
				console.log('account ID: ' + metadata.account_id);
				sbStoreAccountID( {
					public_token: public_token,
					account_id: metadata.account_id
				} );
			},
			onExit: function(err, metadata) {
				// The user exited the Link flow.
				if (err != null) {
					  // The user encountered a Plaid API error prior to exiting.
				}
				// metadata contains information about the institution
				// that the user selected and the most recent API request IDs.
			},
		});
	}

	$(document).ready(function($) {

		// init stripe with pub key
		Stripe.setPublishableKey( si_stripe_js_object.pub_key );

		// non ajaxed
		$('body').on('submit', '#si_credit_card_form', function(event) {
			// If there's no charge token than use stripe api to get one
			if ( $('[name="'+si_stripe_js_object.token_input+'"]').val().length === 0 ) {
				event.preventDefault();
				si_stripe_process_card();
			};
		});

		// open up the authorization
		$('#plaid_auth').on( 'click', function(event){
			event.preventDefault();
			plaidHandler.open();
		});

	});

});
