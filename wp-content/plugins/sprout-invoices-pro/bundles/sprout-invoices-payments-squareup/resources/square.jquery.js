;(function( $, si, undefined ) {
	
	si.squareup = {
		config: {
		},
	};



		// Initializes the payment form. See the documentation for descriptions of
		// each of these parameters.
		si.squareup.paymentForm = new SqPaymentForm({
			applicationId: squareAppID,
			inputClass: 'sq-input',
			inputStyles: [
				{
					padding: '0.5em 0.6em',
				},
			],
			cardNumber: {
				elementId: 'sa_credit_cc_number',
				placeholder: '•••• •••• •••• ••••'
			},
			cvv: {
				elementId: 'sa_credit_cc_cvv',
				placeholder: 'CVV'
			},
			expirationDate: {
				elementId: 'sa_credit_cc_month_year',
				placeholder: 'MM/YY'
			},
			postalCode: {
				elementId: 'sa_credit_cc_zip'
			},
			callbacks: {

				// Called when the SqPaymentForm completes a request to generate a card
				// nonce, even if the request failed because of an error.
				cardNonceResponseReceived: function(errors, nonce, cardData) {
					if (errors) {
						console.log("Encountered errors:");
						$('#square_errors').show();
						// This logs all errors encountered during nonce generation to the
						// Javascript console.
						errors.forEach(function(error) {
							var error = '<p class="si_error">' + error.message + '</p>';

							// show the errors on the form
							$('#square_errors').html(error);
						});

					// No errors occurred. Extract the card nonce.
					} else {

						document.getElementById('square_charge_token').value = nonce;
						document.getElementById('si_credit_card_form').submit();

					}
				},

				unsupportedBrowserDetected: function() {
					alert('Apologies, your browser will not support our credit card processor.');
				},

				// Fill in these cases to respond to various events that can occur while a
				// buyer is using the payment form.
				inputEventReceived: function(inputEvent) {
					switch (inputEvent.eventType) {
						case 'focusClassAdded':
						// Handle as desired
						break;
						case 'focusClassRemoved':
						// Handle as desired
						break;
						case 'errorClassAdded':
						// Handle as desired
						break;
						case 'errorClassRemoved':
						// Handle as desired
						break;
						case 'cardBrandChanged':
						// Handle as desired
						break;
						case 'postalCodeChanged':
						// Handle as desired
						break;
					}
				},

				paymentFormLoaded: function() {
					// Fill in this callback to perform actions after the payment form is
					// done loading (such as setting the postal code field programmatically).
					// paymentForm.setPostalCode('94103');
				}
			}
		});

	/**
	 * methods
	 */
	si.squareup.init = function() {

		if ( squareAppID == '' ) {
			alert('Looks like you need to configure Square.');
		}
		console.log(squareAppID);
		$('body').on('submit', '#si_credit_card_form', function(event) {

			// If there's no charge token than use square api to get one
			if ( $('[name="square_charge_token"]').val().length === 0 ) {
				event.preventDefault();
				si.squareup.paymentForm.requestCardNonce( squareAppID );
			};
		});
		
	};
	
})( jQuery, window.si = window.si || {} );

// Init
jQuery(function() {
	si.squareup.init();
});