;(function( $, si, undefined ) {

    si.docAttachments = {
        config: {
        },
    };

    si.docAttachments.AddMediaInput = function( mediaID ) {
        $('<input>').attr({
            type: 'hidden',
            value: mediaID,
            name: 'doc_media[]'
        }).appendTo('#doc_attachments');
    }

    si.docAttachments.AddMediaThumb = function( media ) {
        $('<a>', {
            href: media.editLink,
            class: media.type,
            text: media.filename,
            target: "_blank"
        }).appendTo('#doc_attachment_thumbnails');
    }

    si.docAttachments.MediaWindow = function( element, doc_attachment_frame ) {
        // If the frame already exists, re-open it.
        if ( doc_attachment_frame ) {
            doc_attachment_frame.open();
            return;
        }
 
        // Sets up the media library frame
        doc_attachment_frame = wp.media.frames.doc_attachment_frame = wp.media({
            title: doc_upload.title,
            button: { text:  doc_upload.button },
        });
 
        // Runs when an image is selected.
        doc_attachment_frame.on('select', function(){
 
            // Grabs the attachment selection and creates a JSON representation of the model.
            var media_attachment = doc_attachment_frame.state().get('selection').first().toJSON();
            console.log(media_attachment);

            si.docAttachments.AddMediaInput( media_attachment.id );
            si.docAttachments.AddMediaThumb( media_attachment );
        });
 
        // Opens the media library frame.
        doc_attachment_frame.open();
    }

    si.docAttachments.removeMedia = function( mediaID ) {
        $( 'input[value="' + mediaID + '"]' ).remove();
        $( 'a[data-id="' + mediaID + '"]' ).remove();
        $( 'span[data-id="' + mediaID + '"]' ).remove();
    }

    /**
     * methods
     */
    si.docAttachments.init = function() {

        var doc_attachment_frame;

        $('#add-doc-atachments').live( 'click', function(e) {
            e.stopPropagation();
            e.preventDefault();
            si.docAttachments.MediaWindow( this, doc_attachment_frame );
        });

         $('.remove_media_item').live( 'click', function(e) {
            var $id = $(this).data('id')
            si.docAttachments.removeMedia( $id );
        });
       

    
    }; // end init

})( jQuery, window.si = window.si || {} );

// Init
jQuery(function() {
    si.docAttachments.init();
});