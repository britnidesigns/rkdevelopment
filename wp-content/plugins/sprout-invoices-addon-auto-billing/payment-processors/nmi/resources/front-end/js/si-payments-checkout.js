jQuery.noConflict();

;(function( $, si, undefined ) {

	si.siPaymentProfiles = {
		config: {
			failed_save: false
		},
	};

	si.siPaymentProfiles.hideBillingFields = function() {
		jQuery('#billing_cc_fields .sa-form-field-required').find('input, select, textarea').each( function() {
			jQuery(this).removeAttr( 'required' );
			jQuery(this).attr( 'disabled', true );
		});
		si.siPaymentProfiles.enablePaymentMethods();
		return true;
	};

	si.siPaymentProfiles.showBillingFields = function() {
		jQuery('#billing_cc_fields .sa-form-field-required').find('input, select, textarea').each( function() {
			jQuery(this).attr( 'required', true );
			jQuery(this).removeAttr( 'disabled' );
			jQuery(this).show();
		});
		si.siPaymentProfiles.enablePaymentMethods();
		return true;
	};

	si.siPaymentProfiles.showCheckFields = function() {
		jQuery('.check_info').show();
		return true;
	};

	si.siPaymentProfiles.enablePaymentMethods = function() {
		jQuery('#credit_card_fields [name="sa_credit_payment_method"]').each( function() {
			jQuery(this).removeAttr( 'disabled' );
		});
		return true;
	};

	si.siPaymentProfiles.hideBankFields = function() {
		jQuery('.bank_info').hide();
		jQuery('#sa_bank_bank_name').hide().removeAttr( 'required' );
		jQuery('#sa_bank_bank_routing').hide().removeAttr( 'required' );
		jQuery('#sa_bank_bank_account').hide().removeAttr( 'required' );
		jQuery('label[for="sa_bank_store_payment_profile"]').hide().removeAttr( 'required' );
		return true;
	};

	si.siPaymentProfiles.hideCCFields = function() {
		jQuery('.cc_info').hide();
		jQuery('#sa_credit_cc_number').hide().removeAttr( 'required' );
		jQuery('#sa_credit_cc_name').hide().removeAttr( 'required' );
		jQuery('#sa_credit_cc_expiration_month').hide().removeAttr( 'required' );
		jQuery('#sa_credit_cc_expiration_year').hide().removeAttr( 'required' );
		jQuery('#sa_credit_cc_cvv').hide().removeAttr( 'required' );
		jQuery('label[for="sa_credit_store_payment_profile"]').hide().removeAttr( 'required' );
		return true;
	};

	si.siPaymentProfiles.hideCheckFields = function() {
		jQuery('.check_info').hide();
		return true;
	};

	si.siPaymentProfiles.removeCard = function( $remove_card ) {
		var $payment_profile = $remove_card.data( 'ref' );
		var $invoice_id = $remove_card.data( 'invoice-id' );
		jQuery.post( si_js_object.ajax_url, { action: 'si_ab_card_mngt', cim_action: 'remove_payment_profile', remove_profile: $payment_profile, invoice_id: $invoice_id },
			function( data ) {
				$remove_card.parent().parent().fadeOut();
				jQuery('[value="credit"]').prop( 'checked', true );
			}
		);
	};

	si.siPaymentProfiles.Init = function() {

		jQuery('[name="si_payment_amount_change"]').live('change', function(e) {
			var selection = jQuery( this ).val(),
				payment_option = jQuery('#si_payment_amount_input_option');

			payment_option.attr( 'disabled', 'disabled' );
			if ( selection === '1' ) {
				payment_option.removeAttr( 'disabled' );
			}

		});
		jQuery('#si_payment_option_wrap').live('click', function(e) {
			var selection = jQuery('#si_payment_amount_option');

			selection.attr( 'checked', true ).trigger('change');
		});
		jQuery('[name="si_payment_amount_option"]').live( 'keyup', function(e) {
			var payment = parseFloat( jQuery( this ).val() ),
				balance = parseFloat( '<?php echo $balance ?>' );

			if ( payment > balance ) {
				jQuery('[name="si_payment_amount_option"]').val( balance );
			};
		});
				
		$('.cim_delete_card').on( 'click', function(event){
			event.preventDefault();
			var $remove_card = jQuery( this );
			si.siPaymentProfiles.removeCard( $remove_card );
		});


		si.siPaymentProfiles.hideCCFields();
		si.siPaymentProfiles.hideCheckFields();
		$('[name="sa_credit_payment_method"]').live('change', function(e) {
			var selection = jQuery( this ).val();

			jQuery('#modify_deposit_amount').show();
			jQuery('#modify_invoice_start_date_wrap').show();

			jQuery('.cc_info').hide();
			if ( selection === 'credit' ) {
				jQuery('#credit_card_submit').show();
				jQuery('.cc_info').show();
				jQuery('.billingallow_to_autobill').show();
				si.siPaymentProfiles.showBillingFields();
				si.siPaymentProfiles.hideBankFields();
				si.siPaymentProfiles.hideCheckFields();
			}
			else if ( selection === 'vault' ) {
				jQuery('#credit_card_submit').show();
				si.siPaymentProfiles.hideBillingFields();
				jQuery('.billingallow_to_autobill').hide();
				si.siPaymentProfiles.hideCCFields();
				si.siPaymentProfiles.hideBankFields();
				si.siPaymentProfiles.hideCheckFields();
			}
			else if ( selection === 'bank' ) {
				jQuery('#credit_card_submit').show();
				jQuery('.bank_info').show();
				jQuery('.billingallow_to_autobill').show();
				si.siPaymentProfiles.showBillingFields();
				si.siPaymentProfiles.hideCCFields();
				si.siPaymentProfiles.hideCheckFields();
			}
			else if ( selection === 'check' ) {
				jQuery('.check_info').show();
				jQuery('#credit_card_submit').hide();
				jQuery('.billingallow_to_autobill').hide();
				jQuery('#modify_deposit_amount').hide();
				jQuery('#modify_invoice_start_date_wrap').hide();
				si.siPaymentProfiles.hideBillingFields();
				si.siPaymentProfiles.showCheckFields();
				si.siPaymentProfiles.hideBankFields();
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