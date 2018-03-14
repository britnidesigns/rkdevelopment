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
$rent_output = '';

foreach ($invoices as $invoice) {
    //$invoice_i = SI_Invoice::get_instance( $invoice );
/*
[ID] => 306
[post_author] => 1
[post_date] => 2018-02-20 12:00:00
[post_date_gmt] => 2018-02-22 22:14:54
[post_content] =>
[post_title] => Recurring Daily
[post_excerpt] =>
[post_status] => complete
[comment_status] => closed
[ping_status] => closed
[post_password] =>
[post_name] => b49702699f951785709df04fcd6dd7dc
[to_ping] =>
[pinged] =>
[post_modified] => 2018-02-22 16:15:03
[post_modified_gmt] => 2018-02-22 22:15:03
[post_content_filtered] =>
[post_parent] => 0
[guid] => http://localhost/rkdevelopment/pay-rent/b49702699f951785709df04fcd6dd7dc/
[menu_order] => 0
[post_type] => sa_invoice
[post_mime_type] =>
[comment_count] => 0
[filter] => raw
*/
    $balance = si_get_invoice_balance($invoice);
    $amt_due+= $balance;

    if ($balance > 0) {
        $due_date = date('M j', si_get_invoice_due_date($invoice));

        $rent_output.= '<p>
            $'. $balance .' due '. $due_date .'
            <a href="'. esc_url( add_query_arg( array( 'dashboard' => 'pay' ), get_permalink( $invoice_id ) ) ) .'">Pay</a>
        </p>';
    }

    //echo si_get_invoice_status($invoice).' - ';
    //echo $balance.' - '.$due_date.'<br>';
}

$due_class = ($amt_due === 0) ? 'paid' : 'unpaid';

if ($amt_due === 0) {
    $rent_output = '<p>✔ All rent has been paid</p>';
}

else {
    //TODO: only show $rent_output if more than one invoice, change button link to specifc invoice if only one invoice

    $rent_output.= '<a href="'. get_site_url() .'/rent" class="btn">Pay Rent</a>'; // TODO: add invoice url
}
?>

<div class="dashboard">
	<section>
		<h2>Rent Due</h2>
        <p class="balance <?=$due_class?>">$<?=$amt_due?></p>
		<?=$rent_output?>
		<a href="<?=get_site_url()?>/rent">Rent History</a>
	</section>
	<section>
		<h2>Maintenance</h2>
		<p>TODO: Messages overview</p>
		<a href="<?=wpas_get_submission_page_url(); ?>" class="btn">New Request</a>
		<a href="<?=get_site_url()?>/maintenance">Maintenance History</a>
	</section>
</div>
