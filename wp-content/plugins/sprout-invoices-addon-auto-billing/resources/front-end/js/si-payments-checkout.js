var $ = jQuery.noConflict();
;(function( $, si, undefined ) {

	si.siPaymentProfiles = {
		config: {
			failed_save: false
		},
	};

	si.siPaymentProfiles.hideBillingFields = function() {
		jQuery('#billing_cc_fields .sa-form-field-required').find('input, select, textarea').each( function() {
			var field_name = jQuery(this).attr('name');
			if ( 'sa_credit_payment_method' !== field_name  ) {
				jQuery(this).removeAttr( 'required' );
				jQuery(this).attr( 'disabled', true );
			}
		});
		si.siPaymentProfiles.enablePaymentMethods();
		return true;
	};

	si.siPaymentProfiles.showBillingFields = function() {
		jQuery('#billing_cc_fields .sa-form-field-required').find('input, select, textarea').each( function() {
			var field_name = jQuery(this).attr('name');
			if ( 'sa_credit_payment_method' !== field_name  ) {
				jQuery(this).attr( 'required', true );
				jQuery(this).removeAttr( 'disabled' );
			}
		});
		si.siPaymentProfiles.enablePaymentMethods();
		return true;
	};

	si.siPaymentProfiles.enablePaymentMethods = function() {
		jQuery('#credit_card_fields [name="sa_credit_payment_method"]').each( function() {
			jQuery(this).removeAttr( 'disabled' );
		});
		return true;
	};

	si.siPaymentProfiles.hideBankFields = function() {
		jQuery('label[for="sa_credit_store_payment_profile"]').show().attr( 'required', true );
		jQuery('#sa_credit_cc_number').show().attr( 'required', true );
		jQuery('#sa_credit_cc_name').show().attr( 'required', true );
		jQuery('#sa_credit_cc_expiration_month').show().attr( 'required', true );
		jQuery('#sa_credit_cc_expiration_year').show().attr( 'required', true );
		jQuery('#sa_credit_cc_cvv').show().attr( 'required', true );
		jQuery('#sa_bank_bank_routing').hide().removeAttr( 'required' );
		jQuery('#sa_bank_bank_account').hide().removeAttr( 'required' );
		jQuery('label[for="sa_bank_store_payment_profile"]').hide().removeAttr( 'required' );
		return true;
	};

	si.siPaymentProfiles.hideCCFields = function() {
		jQuery('label[for="sa_bank_store_payment_profile"]').show().attr( 'required', true );
		jQuery('#sa_bank_bank_routing').show().attr( 'required', true );
		jQuery('#sa_bank_bank_account').show().attr( 'required', true );
		jQuery('#sa_credit_cc_number').hide().removeAttr( 'required' );
		jQuery('#sa_credit_cc_name').hide().removeAttr( 'required' );
		jQuery('#sa_credit_cc_expiration_month').hide().removeAttr( 'required' );
		jQuery('#sa_credit_cc_expiration_year').hide().removeAttr( 'required' );
		jQuery('#sa_credit_cc_cvv').hide().removeAttr( 'required' );
		jQuery('label[for="sa_credit_store_payment_profile"]').hide().removeAttr( 'required' );
		return true;
	};

	si.siPaymentProfiles.removeCard = function( $remove_card ) {
		var $payment_profile = $remove_card.data( 'ref' );
		var $invoice_id = $remove_card.data( 'invoice-id' );
		jQuery.post( si_js_object.ajax_url, { action: 'si_ab_card_mngt', cim_action: 'remove_payment_profile', remove_profile: $payment_profile, invoice_id: $invoice_id },
			function( data ) {
				$remove_card.parent().parent().fadeOut();
				jQuery('[value="new_credit"]').prop( 'checked', true );
			}
		);
	};

	si.siPaymentProfiles.Init = function() {

		$('.cim_delete_card').on( 'click', function(event){
			event.preventDefault();
			var $remove_card = jQuery( this );
			si.siPaymentProfiles.removeCard( $remove_card );
		});

		$('[name="sa_billing_allow_to_autobill"]').live('change', function(e) {
			if ( $(this).is(':checked') ) {
				jQuery('[name="sa_credit_store_payment_profile"]').prop( 'checked', true );
			}
		});

		$('[name="sa_credit_store_payment_profile"]').live('change', function(e) {
			if ( ! $(this).is(':checked') ) {
				jQuery('[name="sa_billing_allow_to_autobill"]').removeAttr( 'checked' );
			}
		});

		si.siPaymentProfiles.hideBankFields();
		$('[name="sa_credit_payment_method"]').live('change', function(e) {
			var selection = jQuery( this ).val();

			if ( selection === 'new_credit' ) {
				si.siPaymentProfiles.showBillingFields();
				si.siPaymentProfiles.hideBankFields();
			}
			else if ( selection === 'new_bank' ) {
				si.siPaymentProfiles.showBillingFields();
				si.siPaymentProfiles.hideCCFields();
			}
			else {
				si.siPaymentProfiles.hideBillingFields();
			};

		});
	};
	
})( jQuery, window.si = window.si || {} );

// Init
jQuery(function() {
	si.siPaymentProfiles.Init();
});