<?php

/**
 *
 * @package SI_Sprout_Billings_AJAX
 */
class SI_Sprout_Billings_AJAX extends SI_Sprout_Billings {
	const AJAX_ACTION = 'si_attempt_auto_charge';

	public static function init() {

		// admin ajax
		add_action( 'admin_head', array( __CLASS__, 'print_admin_js' ) );

		add_action( 'wp_ajax_' . self::AJAX_ACTION, array( get_class(), 'attempt_charge' ) );
		add_action( 'wp_ajax_nopriv_' . self::AJAX_ACTION, array( get_class(), 'attempt_charge' ) );

	}

	public static function print_admin_js() {
		$screen = get_current_screen();
		if ( $screen->id === 'edit-sa_invoice' || $screen->id === 'sa_invoice' ) {
			?>
				<script type="text/javascript">
					jQuery(document).ready(function() {
						jQuery('.payment_capture').on( 'click', function(event){
							event.preventDefault();
							var $payment_button = jQuery( this ),
								invoice_id = $payment_button.data( 'invoice_id' ),
								client_id = $payment_button.data( 'client_id' ),
								nonce = si_js_object.security;

							$payment_button.after(si_js_object.inline_spinner);
							$payment_button.attr('disabled', true);
							jQuery.post( si_js_object.ajax_url, { action: '<?php echo self::AJAX_ACTION ?>', client_id: client_id, invoice_id: invoice_id, nonce: nonce },
								function( response ) {
									$payment_button.hide();
									jQuery('.spinner').hide();
									$payment_button.after( response.data.message );
								}
							);
						});
					});
				</script>
			<?php
		}
	}


	/////////////
	// Actions //
	/////////////

	public static function attempt_charge() {

		$nonce = $_REQUEST['nonce'];
		if ( ! wp_verify_nonce( $nonce, self::NONCE ) ) {
			wp_send_json_error( array( 'message' => __( 'Not going to fall for it!' , 'sprout-invoices' ) ) );
		}

		if ( ! isset( $_REQUEST['invoice_id'] ) || ! isset( $_REQUEST['client_id'] ) ) {
			wp_send_json_error( array( 'message' => __( 'Missing critical info!' , 'sprout-invoices' ) ) );
		}

		// attempt the charge
		$invoice_id = $_REQUEST['invoice_id'];
		$client_id = $_REQUEST['client_id'];
		$response = self::charge_invoice_balance( $invoice_id, $client_id );
		if ( ! $response || is_string( $response ) ) {
			wp_send_json_error( array( 'message' => $response ) );
		}

		wp_send_json_success( array( 'message' => sprintf( __( 'Payment Successful: #%s' , 'sprout-invoices' ), $response ) ) );
	}
}
