;(function( $, si, undefined ) {
	
	si.expenseEntries = {
		config: {
			entries_table: '#expense_entries',
		},
	};

	/**
	 * Save expense entry
	 * @param  {array} data
	 */
	si.expenseEntries.save = function( data, $save_button ) {
		data.action = 'sa_save_expense';
		$save_button.after(si_js_object.inline_spinner);
		$.post( si_js_object.ajax_url, data,
			function( response ) {
				if ( response.error ) {
					$('.spinner').hide();
					$save_button.after('<span class="inline_error_message">' + response.response + '</span>');	
				}
				else {
					$('.spinner').hide();
					$save_button.after('<span class="inline_success_message">' + si_js_object.expense_tracker_success_message + '</span>');
					si.expenseEntries.RefreshTable();
				}
			}
		);
	};

	/**
	 * Delete the expense entry within the admin view of expense entries
	 */
	si.expenseEntries.deleteExpenseEntry = function( $button, data, to_remove ) {
		data.action = 'sa_remove_expense_entry';
		si.expenseEntries.deleteActionTableRemoval( $button, data, to_remove );
	};

	/**
	 * Delete the expense category within the admin view of expenses
	 */
	si.expenseEntries.deleteExpenseCategory = function( $button, data, to_remove ) {
		data.action = 'sa_remove_expense';
		si.expenseEntries.deleteActionTableRemoval( $button, data, to_remove );
	};

	si.expenseEntries.deleteActionTableRemoval = function( $button, data, to_remove ) {
		$button.hide();
		$button.after(si_js_object.inline_spinner);
		$.post( si_js_object.ajax_url, data,
			function( response ) {
				if ( response.error ) {
					$('.spinner').hide();
					$button.after('<span class="inline_error_message">' + response.response + '</span>');	
				}
				else {
					$('.spinner').hide();
					$('#'+to_remove).fadeOut();
				}
			}
		);
	};

	/**
	 * use AJAX function to get table view
	 */
	si.expenseEntries.RefreshTable = function() {
		var project_id = $('#expense_entries').data('project-id');
		$.post( si_js_object.ajax_url, { action: 'sa_expense_entries_table', project_id: project_id },
			function( response ) {
				$('#expense_entries').html( response );
				$('#sa_expense_expense_cost').val('');
				$('#sa_expense_note').val('');
			}
		);
	};

	/**
	 * Create expense
	 * @param  {array} data
	 */
	si.expenseEntries.saveCategory = function( data, $save_button ) {
		data.action = 'sa_create_category';
		$save_button.after(si_js_object.inline_spinner);
		$.post( si_js_object.ajax_url, data,
			function( response ) {
				si.expenseEntries.closeModalAfterExpenseSave( response, $save_button );
			}
		);
	};

	si.expenseEntries.closeModalAfterExpenseSave = function( response, $save_button ) {
		if ( response.error ) {
			$('.spinner').hide();
			$save_button.after('<span class="inline_error_message">' + response.response + '</span>');	
		}
		else {
			// close modal
			self.parent.tb_remove();

			// Clear inputs
			$('#sa_expense_name').val('');
			$('#sa_expense_rate').val('');
			$('#sa_expense_percentage').val('');

			// change option text
			$('[name="sa_expense_category_id"]').append($('<option/>', { 
					value: response.id,
					text : response.option_name 
				})).val(response.id);

		}
	};

	si.expenseEntries.expenseCreationModal = function( $drop ) {
		// thickbox
		tb_show( si_js_object.expense_creation_modal_title, si_js_object.expense_creation_modal_url );
	};

	si.expenseEntries.expenseTrackerModal = function( $drop ) {
		// Remove the dashboard widget if on the dashboard page
		if ( $('#expense_tracker.postbox' ).length ) {
			$('#expense_tracker.postbox' ).remove();
		};
		// thickbox
		tb_show( si_js_object.expense_tracker_modal_title, si_js_object.expense_tracker_modal_url );
	};


    si.expenseEntries.AddMediaInput = function( mediaID ) {
        $('<input>').attr({
            type: 'hidden',
            value: mediaID,
            name: 'expense_media[]'
        }).appendTo('#expense_attachments');
    }

    si.expenseEntries.AddMediaThumb = function( media ) {
        $('<a>', {
            href: media.editLink,
            class: media.type,
            text: media.filename,
            target: "_blank"
        }).appendTo('#expense_attachment_thumbnails');
    }

    si.expenseEntries.MediaWindow = function( element, expense_attachment_frame ) {
        // If the frame already exists, re-open it.
        if ( expense_attachment_frame ) {
            expense_attachment_frame.open();
            return;
        }
 
        // Sets up the media library frame
        expense_attachment_frame = wp.media.frames.expense_attachment_frame = wp.media({
            title: expense_upload.title,
            button: { text:  expense_upload.button },
        });
 
        // Runs when an image is selected.
        expense_attachment_frame.on('select', function(){
 
            // Grabs the attachment selection and creates a JSON representation of the model.
            var media_attachment = expense_attachment_frame.state().get('selection').first().toJSON();
            console.log(media_attachment);

            si.expenseEntries.AddMediaInput( media_attachment.id );
            si.expenseEntries.AddMediaThumb( media_attachment );
        });
 
        // Opens the media library frame.
        expense_attachment_frame.open();
    }

    si.expenseEntries.removeMedia = function( mediaID ) {
        $( 'input[value="' + mediaID + '"]' ).remove();
        $( 'a[data-id="' + mediaID + '"]' ).remove();
        $( 'span[data-id="' + mediaID + '"]' ).remove();
    }

	/**
	 * methods
	 */
	si.expenseEntries.init = function() {
		// Save expense entry
		$( '#create_expense_entry' ).live( 'click', function( e ) {
			e.stopPropagation();
			e.preventDefault();

			var attachments = new Array();
			$('input[name^="expense_media"]').each(function() {
				attachments.push( $(this).val() );
			});

			var data = {
				project_id: $('#sa_expense_project_id').val(),
				category_id: $('#sa_expense_category_id').val(),
				expense_val: $('#sa_expense_expense_cost').val(),
				title: $('#sa_expense_title').val(),
				note: $('#sa_expense_note').val(),
				date: $('#sa_expense_date').val(),
				attachments: attachments,
				nonce: $('#sa_expense_nonce').val()
			};
			$('span.inline_error_message').hide();
			$('span.inline_success_message').hide();
			si.expenseEntries.save( data, $( this ) );
		} );


		// Save expense category
		$( '#create_expense_category' ).live( 'click', function( e ) {
			e.stopPropagation();
			e.preventDefault();

			var data = {
				name: $('#sa_expense_name').val(),
				rate: $('#sa_expense_rate').val(),
				percentage: $('#sa_expense_percentage').val(),
				nonce: $('#sa_expense_nonce').val()
			};

			if ( $('#sa_expense_billable').is(':checked') ) {
				data.billable = true;
			};

			$('span.inline_error_message').hide();
			si.expenseEntries.saveCategory( data, $( this ) );

		} );


		// Remove expense entry
		$( '.expense_entry_deletion' ).live( 'click', function( e ) {
			var data = {
					project_id: $(this).data('project-id'),
					id: $(this).data('id'),
					nonce: $(this).data('nonce')
				};
			$('span.inline_error_message').hide();
			si.expenseEntries.deleteExpenseEntry( $(this), data, $(this).data('id') );
		} );

		// Dynamically insert the expense creation form.
		$( '#show_expense_creation_modal' ).live( 'click', function( e ) {
			si.expenseEntries.expenseCreationModal();
		} );

		// Dynamically add the expense tracking form.
		$( '.expense_tracker_popup' ).live( 'click', function( e ) {
			si.expenseEntries.expenseTrackerModal();
		} );


		// Remove expense category
		$( '.expense_category_deletion' ).live( 'click', function( e ) {
			var data = {
					id: $(this).data('id'),
					nonce: $(this).data('nonce')
				};
			$('span.inline_error_message').hide();
			si.expenseEntries.deleteExpenseCategory( $(this), data, $(this).data('id') );
		} );

		var expense_attachment_frame;

        $('#add-expense-atachments').live( 'click', function(e) {
            e.stopPropagation();
            e.preventDefault();
            si.expenseEntries.MediaWindow( this, expense_attachment_frame );
        });

         $('.remove_exp_media_item').live( 'click', function(e) {
            var $id = $(this).data('id')
            si.expenseEntries.removeMedia( $id );
        });
	};
	
})( jQuery, window.si = window.si || {} );

// Init
jQuery(function() {
	si.expenseEntries.init();
});
