<?php

$user_id = get_current_user_id();
$invoices = array();

$client_ids = SI_Client::get_clients_by_user( $user_id );

if ( !empty( $client_ids ) ) {
	$invoices = array();

	foreach ( $client_ids as $client_id ) {
		$client = SI_Client::get_instance( $client_id );
		$invoices = array_merge( $client->get_invoices(), $invoices );
	}
}

$amt_due = 0;
$unpaid_count = 0;
$rent_output = '';
$invoice_link = '';

foreach ( $invoices as $invoice ) {
    $balance = si_get_invoice_balance( $invoice );
    $amt_due+= $balance;

    if ( $balance > 0 ) {
        $unpaid_count++;

        $due_date = date( 'M j', si_get_invoice_due_date( $invoice ) );
        $invoice_link = get_permalink( $invoice );
        //$invoice_link = esc_url( add_query_arg( array( 'dashboard' => 'pay' ), get_permalink( $invoice ) ) );

        $rent_output.= '<div class="invoice">
            <p><span>$'. $balance .'</span> due '. $due_date .'</p>
            <a href="'. $invoice_link .'" class="btn-alt">Pay</a>
        </div>';
    }
}

$due_class = ( $amt_due > 0 ) ? 'unpaid' : 'paid';

if ( $amt_due <= 0 )
    $rent_output = '<p><span class="check">✔</span> All rent due has been paid</p>';

elseif ( $unpaid_count === 1 )
    $rent_output = '<a href="'. $invoice_link .'" class="btn">Pay Rent</a>';

else
    $rent_output.= '<a href="'. get_site_url() .'/rent" class="btn">Pay Rent</a>';
?>

<div class="dashboard">
	<section>
		<h2>Rent Due</h2>
        <p class="balance <?=$due_class?>"><sup>$</sup><?=number_format( $amt_due, 2 )?></p>
		<?=$rent_output?>
		<a href="<?=get_site_url()?>/rent" class="history">Rent History</a>
	</section>
	<section>
		<h2>Maintenance</h2>
		<p>TODO: Messages overview</p>
		<a href="<?=wpas_get_submission_page_url(); ?>" class="btn">New Request</a>
		<a href="<?=get_site_url()?>/maintenance" class="history">Maintenance History</a>
	</section>
</div>
