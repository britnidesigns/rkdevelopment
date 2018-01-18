;(function( $, si, undefined ) {
	
	si.timeToggl = {
		config: {
		},
	};

	si.timeToggl.showHideDefaultActivity = function( $checkbox ) {
		// thickbox
		if ( $checkbox.is(':checked') ) {
			$('#si_admin_field_toggl_default_activity').fadeIn('fast');
		}
		else {
			$('#si_admin_field_toggl_default_activity').hide();
		}
	};

	/**
	 * methods
	 */
	si.timeToggl.init = function() {
		$('#si_admin_field_toggl_default_activity').hide();

		$('#sa_toggl_pulldown_time').live( 'click', function( e ) {
			si.timeToggl.showHideDefaultActivity( $(this) );
		} );
	};
	
})( jQuery, window.si = window.si || {} );

// Init
jQuery(function() {
	si.timeToggl.init();
});
