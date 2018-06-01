<?php // Original template file: plugins/sprout-invoices-pro/bundles/sprout-invoices-addon-client-dash/views/section/dashboard-invoices.php ?>

<div class="rent-list">
    <?php if ( ! empty( $invoices ) ) {
        $total_balance_due = 0;

        foreach ( $invoices as $invoice_id ) {
            $invoice = SI_Invoice::get_instance( $invoice_id );

            if ( ! is_a( $invoice, 'SI_Invoice' ) )
                continue;

            if ( 'archived' === si_get_invoice_status( $invoice_id ) )
                continue;

            if ( 'write-off' === si_get_invoice_status( $invoice_id ) )
                continue;

            $total_balance_due += si_get_invoice_balance( $invoice_id );
            $status = si_get_invoice_status( $invoice_id );

            // Get a label class for the status.
            switch ( $status ) {
                case 'publish':
                    $label = 'primary';
                    break;
                case 'partial':
                    $label = 'primary';
                    break;
                case 'complete':
                    $label = 'success';
                    break;
                case 'write-off':
                    $label = 'warning';
                    break;
                case 'temp':
                default:
                    $label = 'default';
                    if ( si_get_invoice_due_date( $invoice_id ) < current_time( 'timestamp' ) ) {
                        $label = 'danger';
                    }

                    break;
            } ?>

            <div class="status-<?=esc_attr( $label );?>" data-status="<?=$status?>">
                <div class="dates">
                    <h3><?=date('F Y', si_get_invoice_due_date( $invoice_id ))?></h3>

                    <?php if ( si_get_invoice_due_date( $invoice_id ) ) {?>
                        <p class="due">Due
                            <time datetime="<?php si_invoice_due_date( $invoice_id ) ?>">
                            <?php echo date_i18n( apply_filters( 'si_client_dash_date_format', 'M. jS' ), si_get_invoice_due_date( $invoice_id ) ) ?></time>
                        </p>
                    <?php } ?>

                        <p class="issued">Issued
                            <time datetime="<?php si_invoice_issue_date( $invoice_id ) ?>">
                            <?php echo date_i18n( apply_filters( 'si_client_dash_date_format', 'M. jS' ), si_get_invoice_issue_date( $invoice_id ) ) ?></time></p>
                </div>
                <div class="payment"><?php
                    $balance = si_get_invoice_balance( $invoice_id );

                    // Balance
                    if ( $balance !== 0 ) echo '<p class="balance unpaid"><sup>$</sup>'.number_format($balance, 2).'</p>';
                    else echo '<p class="balance paid"><i class="fas fa-check"></i>Paid in full</p>';

                    // Button
                    if ( $status !== 'temp' ) {
                        $url = esc_url( add_query_arg( array( 'dashboard' => 1 ), get_permalink( $invoice_id ) ) );

                        if ($status === 'complete') echo '<a href="'.$url.'" class="btn-alt"><i class="fas fa-receipt"></i>View Details</a>';
                        else echo '<a href="'.$url.'" class="btn"><i class="fas fa-credit-card"></i>Pay</a>';
                    }

                    else echo '<p class="temp">Pending Review</p>'; ?>
                </div>
            </div>

        <?php }

        // sa_get_formatted_money( $total_balance_due, get_the_id() )
        echo '<p class="total-balance">Balance Due <span><sup>$</sup>'.number_format($total_balance_due, 2).'</span></p>';
    }

    else echo '<p class="empty-list">No rent history available</p>'; ?>
</div>
