;(function( $, si, undefined ) {

	si.docComments = {
		config: {
			"note_expand": ".expand_notes",
		},
	};

	
	si.docComments.createNote = function( $form ) {
		var values = {};
		$.each($form.serializeArray(), function(i, field) {
			values[field.name] = field.value;
		});
		var doc_id = values.doc_id,
			line_item = values.item_position,
			nonce = values.note_sec,
			note = values.comment,
			$button = $form.find(".submit");
		$button.prop( "disabled", true );
		$button.after("<span class='spinner' style='float: left; margin-top: 15px; display:block;'></span>");
		$.post( si_js_object.ajax_url, { action: "sa_create_doc_comment", id: doc_id, item_position: line_item,  comment: note, doc_comment_sec: nonce },
			function( data ) {
				if ( data.error ) {
					$button.after( "<p>" + data.response + "</p>" );
				}
				else {
					si.docComments.commentsView( doc_id, line_item )
				};
				$(".spinner").remove();
				$form.find( "textarea" ).val('');
				$button.prop( "disabled", false );
			}
		);
	};

	si.docComments.commentsView = function( id, item_position  ) {
		var position = item_position;
			sanposition = position.replace(".", "-");
		$.post( si_js_object.ajax_url, { action: "sa_comments_view", doc_id: id, li_position: item_position },
			function( response ) {
				var comments = $( response ).find( ".line_items_comments_list" ).html();
				$( "#line_item_comments_" + sanposition + " .line_items_comments_list" ).html( comments );
			}
		);
	};

	si.docComments.updateComments = function() {
		$('.line_item_comments').each(function() {
			if( $(this).is(":visible") ) {
				var doc_id = $(this).find( "[name='doc_id']").val(),
					position = $(this).find( "[name='item_position']").val();
				si.docComments.commentsView( doc_id, position );
				console.log('updated:' + position);
			}
		});
	};


	/**
	 * methods
	 */
	si.docComments.init = function() {
		// Create private note
		$(".submit_doc_note_form").on("submit", function(e) {
			e.stopPropagation();
			e.preventDefault();
			si.docComments.createNote( $( this ) );
		});

		$(".li_comments_toggle").on("click", function(e) {
			var position = $(this).data( "li_position" );
			$( '.line_item_comments' ).each( function() {
				if ( $( this ).is( "#line_item_comments_" + position ) && $( this ).is(":hidden") ) {
					$( this ).fadeIn();
				}
				else {
					$( this ).hide();
				};
			});
		});

		setInterval( 'si.docComments.updateComments()', 2000 );
	};
	
})( jQuery, window.si = window.si || {} );

// Init
jQuery(function() {
	si.docComments.init();
});
