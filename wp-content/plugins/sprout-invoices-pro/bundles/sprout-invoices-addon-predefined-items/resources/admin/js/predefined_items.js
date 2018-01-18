;(function( $, si, undefined ) {
	
	si.predefinedItems = {
		config: {
			inline_spinner: '<span class="spinner si_inline_spinner" style="visibility:visible;display:inline-block;"></span>'	
		},
	};

	si.predefinedItems.addLineItem = function( item_id ) {
		var $type_list = $('ol#line_item_list'),
			$type_header = $('#line_items_header');

		$type_list.after(si_js_object.inline_spinner);
		$.post( ajaxurl, { action: 'sa_get_pd_item', item_id: item_id },
			function( response ) {
				if ( response.success ) {
					var $row = $(response.data.option);

					$('.spinner').hide();
					
					// append the row to the list.
					$type_list.append($row);

					// update key
					si.lineItems.modifyInputKey();
					si.lineItems.calculateEachLineItemTotal();
					si.lineItems.calculateLineItemsTotals();
					
					// Add the redactor
					if ( si_js_object.redactor ) {
						$row.find('.column_desc [name="line_item_desc[]"]').redactor();
					};
				}
				else {
					$type_list.append('<span class="inline_message inline_error_message">' + response.data.message + '</span>');
				}
			}
		);
	};

	si.predefinedItems.getItem = function( item_id ) {
		$.post( ajaxurl, { action: 'sa_get_pd_item', item_id: item_id },
			function( response ) {
				return response;
			}
		);
	};

	si.predefinedItems.itemCreationModal = function() {
		// thickbox
		tb_show( si_js_object.item_creation_modal_title, si_js_object.item_creation_modal_url );
	};

	si.predefinedItems.itemEditModal = function( item_id ) {
		// thickbox
		tb_show( si_js_object.item_edit_modal_title, si_js_object.item_edit_modal_url + '&item_id=' + item_id );
	};


	si.predefinedItems.itemManagementModal = function() {
		// thickbox
		tb_show( si_js_object.item_mngt_modal_title, si_js_object.item_mngt_modal_url );
	};


	/**
	 * Create item
	 * @param  {array} data
	 */
	si.predefinedItems.saveItem = function( data, $save_button ) {
		data.action = 'sa_create_pd_item';
		$save_button.after( si_js_object.inline_spinner );
		$.post( ajaxurl, data,
			function( response ) {
				si.predefinedItems.closeModalAfterItemSave( response, $save_button );
			}
		);
	};

	/**
	 * Edit item
	 * @param  {array} data
	 */
	si.predefinedItems.editItem = function( data, $save_button ) {
		data.action = 'sa_edit_pd_item';
		$save_button.after( si_js_object.inline_spinner );
		$.post( ajaxurl, data,
			function( response ) {
				console.log(response);
				si.predefinedItems.itemManagementModal();
			}
		);
	};

	si.predefinedItems.closeModalAfterItemSave = function( response, $save_button ) {
		console.log(response);
		if ( response.success ) {
			// Add line item
			si.predefinedItems.addLineItem( response.data.id );

			// Refresh option
			si.predefinedItems.refreshAddItemButton();

			// close modal
			self.parent.tb_remove();

			// Clear inputs
			$('#sa_item_name').val('');
			$('#sa_item_description').val('');
			$('#sa_item_qty').val('');
			$('#sa_item_rate').val('');
			$('#sa_item_percentage').val('');
		}
		else {
			$('.spinner').hide();
			$save_button.after('<span class="inline_error_message">' + response.data.message + '</span>');
		}
	};

	si.predefinedItems.refreshAddItemButton = function() {
		$.post( ajaxurl, { action: 'sa_pd_items_options_view' },
			function( response ) {
				$('#predefined_line_items').html( response );
			}
		);
	};

	/**
	 * Delete the item entry within the admin view of item entries
	 */
	si.predefinedItems.deleteItem = function( $button, data, to_remove ) {
		data.action = 'sa_delete_pd_item';
		si.predefinedItems.deleteActionTableRemoval( $button, data, to_remove );
	};

	si.predefinedItems.deleteActionTableRemoval = function( $button, data, to_remove ) {
		$button.hide();
		$button.after(si_js_object.inline_spinner);
		$.post( ajaxurl, data,
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
	 * methods
	 */
	si.predefinedItems.init = function() {
		
		$( '#predefined-items' ).select2({
			placeholder: "Saved Items",
			// Support for optgroup searching
			matcher: function modelMatcher (params, data) {
				data.parentText = data.parentText || "";

				// Always return the object if there is nothing to compare
				if ($.trim(params.term) === '') {
					return data;
				}

				// Do a recursive check for options with children
				if (data.children && data.children.length > 0) {
					// Clone the data object if there are children
					// This is required as we modify the object to remove any non-matches
					var match = $.extend(true, {}, data);

					// Check each child of the option
					for (var c = data.children.length - 1; c >= 0; c--) {
						var child = data.children[c];
						child.parentText += data.parentText + " " + data.text;

						var matches = modelMatcher(params, child);

						// If there wasn't a match, remove the object in the array
						if (matches == null) {
							match.children.splice(c, 1);
						}
					}

					// If any children matched, return the new object
					if (match.children.length > 0) {
						return match;
					}

					// If there were no matching children, check just the plain object
					return modelMatcher(params, match);
				}

				// If the typed-in term matches the text of this term, or the text from any
				// parent term, then it's a match.
				var original = (data.parentText + ' ' + data.text).toUpperCase();
				var term = params.term.toUpperCase();


				// Check if the text contains the term
				if (original.indexOf(term) > -1) {
					return data;
				}

				// If it doesn't contain the term, don't return anything
				return null;
			}
		});

		// Popup to create new items
		$( '#create_new_item' ).live( 'click', function( e ) {
			si.predefinedItems.itemCreationModal();
		} );

		// Popup to manage items
		$( '#manage_items' ).live( 'click', function( e ) {
			si.predefinedItems.itemManagementModal();
		} );

		// Delete item
		$( '.predefined_item_deletion' ).live( 'click', function( e ) {
			var data = {
					item_id: $(this).data('id'),
					nonce: $(this).data('nonce')
				};
			$('span.inline_error_message').hide();
			si.predefinedItems.deleteItem( $(this), data, $(this).data('id') );
		} );

		// Popup to edit new items
		$( '.predefined_item_edit' ).live( 'click', function( e ) {
			var data = {
					item_id: $(this).data('id'),
					nonce: $(this).data('nonce')
				};
			si.predefinedItems.itemEditModal( data.item_id );
		} );

		// Add line item
		$( '#predefined-items' ).live( 'change', function( e ) {
			var id = jQuery( this ).val();
			if ( $.isNumeric( id ) ) {
				si.predefinedItems.addLineItem( id );
			}
			else {
				if ( 'create_new_item' === id ) {
					si.predefinedItems.itemCreationModal();
				}
				if ( 'manage_items' === id ) {
					si.predefinedItems.itemManagementModal();
				}
			}
			return;
		} );

		// Submit form to create item
		$( '#create_predefined_item' ).live( 'click', function( e ) {
			e.stopPropagation();
			e.preventDefault();

			var data = {
				type: $('#sa_item_id').val(),
				type: $('#sa_item_type').val(),
				name: $('#sa_item_name').val(),
				description: $('#sa_item_description').val(),
				qty: $('#sa_item_qty').val(),
				rate: $('#sa_item_rate').val(),
				percentage: $('#sa_item_percentage').val(),
				sku: $('#sa_item_sku').val(),
				nonce: $('#sa_item_nonce').val()
			};

			$('span.inline_error_message').hide();
			si.predefinedItems.saveItem( data, $( this ) );

		} );

		$( '#edit_predefined_item' ).live( 'click', function( e ) {
			e.stopPropagation();
			e.preventDefault();

			var data = {
				id: $('#sa_item_id').val(),
				type: $('#sa_item_type').val(),
				name: $('#sa_item_name').val(),
				description: $('#sa_item_description').val(),
				qty: $('#sa_item_qty').val(),
				rate: $('#sa_item_rate').val(),
				percentage: $('#sa_item_percentage').val(),
				sku: $('#sa_item_sku').val(),
				nonce: $('#sa_item_nonce').val()
			};

			$('span.inline_error_message').hide();
			si.predefinedItems.editItem( data, $( this ) );

		} );

	};


	/**
	 * TODO refactor est_and_invoices.js and use those methods instead.
	 */
	

	/**
	 * Store the line item index 
	 * @return {} 
	 */
	function modify_input_key() {
		$('ol.items_list').each(function(i, ol) {
			ol = $(ol);
			level1 = ol.closest('li').index() + 1;

			ol.children('li').each(function(i, li) {
				li = $(li);
				$index = ( level1 === 0 ) ? li.index() + 1 : level1 + '.' + (li.index() + 1);
				li.find('.line_item_index').val($index);
			});
		});
	}

	/**
	 * Hide the inputs for parent line items
	 * @return {} 
	 */
	function handle_parents() {
		$('ol.items_list .item').each(function(i, li) {
			// If has children
			if ( $(li).children('ol').length > 0 ) {
				// hide the parent input fields
				$(li).find('.column.parent_hide input').attr( "type", "hidden" );
			}
			else {
				$(li).find('.column.parent_hide input').attr( "type", "text" );
			}
		});
		$('ol.items_list .has_children').each(function(i, parent) {
			$(parent).find('.column.parent_hide input').val('');
		});
	}
	
})( jQuery, window.si = window.si || {} );

// Init
jQuery(function() {
	si.predefinedItems.init();
});
