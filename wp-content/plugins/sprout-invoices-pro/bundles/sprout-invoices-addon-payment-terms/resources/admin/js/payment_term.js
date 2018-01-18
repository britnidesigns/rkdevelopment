;(function( $, si, undefined ) {
	
	si.paymentTerms = {
		config: {
			entries_table: '#payment_terms',
		},
	};

	/**
	 * Save payment term
	 * @param  {array} data
	 */
	si.paymentTerms.save = function( data, $save_button ) {
		data.action = 'si_payment_term_callback';
		data.maybe = 'add_term';
		$save_button.after(si_js_object.inline_spinner);
		$.post( si_js_object.ajax_url, data,
			function( response ) {
				console.log(response);
				if ( ! response.success ) {
					$('.spinner').hide();
					$save_button.after('<span class="inline_error_message">' + response.data.message + '</span>');	
				}
				else {
					$('.spinner').hide();
					$save_button.after('<span class="inline_success_message">' + response.data.message + '</span>');
					si.paymentTerms.RefreshTable();
				}
			}
		);
	};

	/**
	 * Delete the payment term within the admin view of payment terms
	 */
	si.paymentTerms.delete = function( $button, data, to_remove ) {
		data.action = 'si_payment_term_callback';
		data.maybe = 'delete_term';
		console.log(data);
		$button.after(si_js_object.inline_spinner);
		$.post( si_js_object.ajax_url, data,
			function( response ) {
				if ( ! response.success ) {
					$('.spinner').hide();
					$button.after('<span class="inline_error_message">' + response.data.message + '</span>');	
				}
				else {
					//$button.after('<span class="inline_success_message">' + response.data.message + '</span>');
					si.paymentTerms.RefreshTable();
				}
			}
		);
	};

	/**
	 * use AJAX function to get table view
	 */
	si.paymentTerms.RefreshTable = function() {
		var doc_id = $('#payment_terms').data('doc-id');
		$.post( si_js_object.ajax_url, { action: 'si_payment_term_callback', view: 'table', doc_id: doc_id, nonce: $('#sa_fees_nonce').val() },
			function( response ) {
				$('#payment_terms').html( response.data.view );
				$('#sa_fees_fee').val('');
				$('#sa_fees_title').val('');
			}
		);
	};

	/**
	 * methods
	 */
	si.paymentTerms.init = function() {
		// Save payment term
		$( '#create_fees_entry' ).live( 'click', function( e ) {
			e.stopPropagation();
			e.preventDefault();

			var complete = false,
				percentage = false;
			if ( $('#sa_fees_complete').is(':checked') ) {
				complete = true;
			}
			if ( $('#sa_fees_percentage').is(':checked') ) {
				percentage = true;
			}
			var data = {
				doc_id: $('#sa_fees_invoice_id').val(),
				complete: complete,
				title: $('#sa_fees_title').val(),
				fee: $('#sa_fees_fee').val(),
				balance: $('#sa_fees_balance').val(),
				percentage: percentage,
				time: $('#sa_fees_time').val(),
				nonce: $('#sa_fees_nonce').val(),
			};
			$('span.inline_error_message').hide();
			$('span.inline_success_message').hide();
			si.paymentTerms.save( data, $( this ) );
		} );


		// Remove payment term
		$( '.payment_term_entry_deletion' ).live( 'click', function( e ) {
			var data = {
					doc_id: $(this).data('doc-id'),
					id: $(this).data('id'),
					nonce: $(this).data('nonce')
				};
			$('span.inline_error_message').hide();
			si.paymentTerms.delete( $(this), data, $(this).data('id') );
		} );
	};
	
})( jQuery, window.si = window.si || {} );

// Init
jQuery(function() {
	si.paymentTerms.init();
});
