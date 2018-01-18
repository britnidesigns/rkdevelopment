<?php

function si_wc_get_client_vat( $client_id = 0 ) {
	if ( ! $client_id ) {
		$doc = si_get_doc_object();
		$client_id = $doc->get_client_id();
	}
	$client = SI_Client::get_instance( $client_id );
	if ( ! is_a( $client, 'SI_Client' ) ) {
		return;
	}
	$vat_number = Woo_Tools::get_vat( $client );
	return apply_filters( 'si_wc_get_client_vat', $vat_number, $client );
}

function si_wc_client_vat( $client_id = 0 ) {
	if ( ! $client_id ) {
		$doc = si_get_doc_object();
		$client_id = $doc->get_client_id();
	}
	echo si_wc_get_client_vat( $client_id );
}
