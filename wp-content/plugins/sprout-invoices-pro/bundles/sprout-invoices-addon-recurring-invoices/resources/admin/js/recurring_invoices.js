;(function( $, si, undefined ) {

	si.recurringInvoices = {
		config: {
			'freq_select': '#sa_recurring_invoice_frequency',
			'recurring_select': '#sa_recurring_invoice_is_recurring',
			'subscription_payments_option': '#sa_recurring_payments_is_recurring_payment',
		},
	};

	si.recurringInvoices.maybeDisableRecurringPayment = function( option ) {
		var $option = $(option),
			is_selected = $option.is(':checked');

		if ( is_selected ) {
			
			$('#si_subscription_payments').fadeOut('fast');

			if ( $(si.recurringInvoices.config.subscription_payments_option).is(':checked') ) {
				alert( 'Recurring Invoice Payments Disabled (only one recurring feature can be enabled)' );
			}

			$( si.recurringInvoices.config.subscription_payments_option ).prop('checked', false);
			
		}
		else {
			$('#si_subscription_payments').fadeIn('fast');
		};
	};

	si.recurringInvoices.maybeDisableRecurringInvoices = function( option ) {
		var $option = $(option),
			is_selected = $option.is(':checked');

		if ( is_selected ) {
			$('#si_recurring_invoices').fadeOut('fast');

			if ( $(si.recurringInvoices.config.recurring_select).is(':checked') ) {
				alert( 'Recurring Invoices Disabled (only one recurring feature can be enabled)' );
			}

			$( si.recurringInvoices.config.recurring_select ).prop('checked', false);
		}
		else {
			$('#si_recurring_invoices').fadeIn('fast');
		};
	};

	si.recurringInvoices.maybeToggleDayInput = function( select ) {
		var $select = $(select),
			frequency = $select.val();

		if ( frequency === 'custom' ) {
			$('#sa_recurring_invoice_custom_freq').closest('.form-group').slideDown('fast');
		}
		else {
			$('#sa_recurring_invoice_custom_freq').closest('.form-group').hide();
		};
	};


	/**
	 * methods
	 */
	si.recurringInvoices.init = function() {

		si.recurringInvoices.maybeToggleDayInput( $( si.recurringInvoices.config.freq_select ) );
		si.recurringInvoices.maybeDisableRecurringPayment( $( si.recurringInvoices.config.recurring_select ) );
		si.recurringInvoices.maybeDisableRecurringInvoices( $( si.recurringInvoices.config.subscription_payments_option ) );
		
		$( si.recurringInvoices.config.recurring_select ).live( 'change', function( e ) {
			si.recurringInvoices.maybeDisableRecurringPayment( this );
		} );

		$( si.recurringInvoices.config.subscription_payments_option ).live( 'change', function( e ) {
			si.recurringInvoices.maybeDisableRecurringInvoices( this );
		} );

		$( si.recurringInvoices.config.freq_select ).live( 'change', function( e ) {
			si.recurringInvoices.maybeToggleDayInput( this );
		} );
	};
	
})( jQuery, window.si = window.si || {} );

// Init
jQuery(function() {
	si.recurringInvoices.init();
});
