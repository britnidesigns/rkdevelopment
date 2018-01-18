<?php


function si_is_invoice_recurring( SI_Invoice $invoice ) {
	return SI_Subscription_Payments::has_subscription_payment( $invoice->get_id() );
}