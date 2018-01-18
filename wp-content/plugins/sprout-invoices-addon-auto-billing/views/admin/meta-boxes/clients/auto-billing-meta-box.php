<script type="text/javascript" charset="utf-8">
	jQuery(document).ready(function() {
		jQuery('.cim_delete_card').on( 'click', function(event){
			event.preventDefault();
			var $remove_card = jQuery( this );
			var $payment_profile = $remove_card.data( 'ref' );
			var $client_id = $remove_card.data( 'client-id' );
			jQuery.post( si_js_object.ajax_url, { action: '<?php echo Sprout_Billings_Profiles::AJAX_ACTION_MANAGE ?>', cim_action: 'remove_payment_profile', remove_profile: $payment_profile, invoice_id: $client_id }, // sending client id instead of invoice_id; CIM will figure it out.
				function( data ) {
					$remove_card.parent().parent().fadeOut();
					jQuery('[value="new_credit"]').prop( 'checked', true );
				}
			);
		});
	});
</script>
<?php do_action( 'sb_client_meta_pre' ); ?>
<div id="client_billing_fields" class="admin_fields clearfix">
	<?php do_action( 'sb_client_meta' ); ?>
	<h4><?php _e( 'Saved Payment Profiles' , 'sprout-invoices' ) ?></h4>
	<div class="sa-control-group clearfix">
		<span class="label_wrap">
			<label for="sa_credit_payment_method">&nbsp;</label>
		</span>
		<span class="input_wrap">
			<span class="sa-form-field sa-form-field-radios sa-form-field-required">	
				<?php foreach ( $payment_profiles as $payment_profile_id => $name ) : ?>
					<span class="sa-form-field-radio clearfix">
						<label for="sa_credit_payment_method_<?php echo $payment_profile_id ?>">
							<input type="radio" name="sa_credit_payment_method" id="sa_credit_payment_method_<?php echo $payment_profile_id ?>" value="<?php echo $payment_profile_id ?>" <?php checked( $default_payment_profile_id, $payment_profile_id ) ?>><?php printf( '%1$s <a href="javascript:void(0)" data-ref="%2$s" data-client-id="%4$s" class="cim_delete_card sa_icon_link" title="%3$s"><span class="dashicons dashicons-trash"></span></a>', $name, $payment_profile_id, __( 'Remove this CC from your account.' , 'sprout-invoices' ), (int) $client_id ) ?>
						</label>
					</span>
				<?php endforeach ?>
				<span class="sa-form-field-radio clearfix">
					<label for="sa_credit_payment_method_credit">
					<input type="radio" name="sa_credit_payment_method" id="sa_credit_payment_method_credit" value="0" <?php checked( $default_payment_profile_id, '0' ) ?>><?php _e( 'None' , 'sprout-invoices' ) ?></label>
				</span>
				<?php if ( 0 !== (int) $default_payment_profile_id ) :  ?>
					<?php if ( ! in_array( $default_payment_profile_id, array_keys( $payment_profiles ) ) ) : ?>
						<p class="description help_block"><?php printf( __( 'A payment profile the client had approved to charge has since been removed. ID: <code>%s</code>' , 'sprout-invoices' ), $default_payment_profile_id ) ?></p>
					<?php endif ?>
				<?php endif ?>
				<p class="description help_block"><?php _e( 'A selected profile not only shows which payment the client wants to use for auto billing &mdash; it shows the client agreed to the terms at payment.' , 'sprout-invoices' ) ?></p>
			</span>
		</span>
	</div>
	<h4><?php _e( 'Auto Bill Options' , 'sprout-invoices' ) ?></h4>
	<?php sa_admin_fields( $fields ); ?>
	<?php do_action( 'sb_client_meta_after_fields' ); ?>
</div>
<?php do_action( 'sb_client_meta_end' ); ?>
