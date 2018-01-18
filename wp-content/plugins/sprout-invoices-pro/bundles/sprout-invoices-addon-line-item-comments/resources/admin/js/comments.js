;(function( $, si, undefined ) {
	
	si.docComments = {
		config: {
		},
	};

	si.docComments.adminModal = function( data ) {
		// thickbox
		tb_show( si_js_object.doc_comments_modal_title, si_js_object.doc_comments_modal_url + '&doc_id=' +data.doc_id+ '&li_position=' + data.li_position );
	};

	si.docComments.createResponse = function( $button ) {
		var doc_id = $button.data( 'doc-id' ),
			li_position = $button.data( 'li-position' ),
			nonce = $button.data( 'sec' ),
			$response_ta = $( '#si_comment_response' ),
			$add_button_og_text = $button.text();
		$button.html( '' );
		$button.append(si_js_object.inline_spinner);
		
		$.post( ajaxurl, { action: 'sa_create_doc_comment', id: doc_id, item_position: li_position, comment: $response_ta.val(), doc_comment_sec: nonce },
			function( data ) {
				if ( data.error ) {
					$button.after( '<p><code>' + data.error + '</code></p>' );
				}
				else {
					console.log(data);
					$response_ta.val('');
					tb_show( si_js_object.doc_comments_modal_title, si_js_object.doc_comments_modal_url + '&doc_id=' +doc_id+ '&li_position=' + li_position );
				};
				
				$button.html( $add_button_og_text );
				return data;
			}
		);
	};

	/**
	 * methods
	 */
	si.docComments.init = function() {
		// Dynamically insert the time creation form.
		$( '.show_doc_comments_modal' ).live( 'click', function( e ) {
			var data = {
					doc_id: $(this).data('doc_id'),
					li_position: $(this).data('li_position')
				};
			si.docComments.adminModal( data );
		} );

		$(".submit_comment_response").live('click', function(e) {
			e.stopPropagation();
			e.preventDefault();
			si.docComments.createResponse( $( this ) );
		});
	};
	
})( jQuery, window.si = window.si || {} );

// Init
jQuery(function() {
	si.docComments.init();
});
