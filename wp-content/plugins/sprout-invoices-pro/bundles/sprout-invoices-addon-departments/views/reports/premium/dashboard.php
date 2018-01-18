<?php
	// Modification for Departments add-on
	$dept = ( isset( $_GET['dept_filter'] ) ) ? $_GET['dept_filter'] : '' ;
		?>
<div id="reports_dashboard" class="wrap">

	<?php screen_icon(); ?>
	<h2 class="nav-tab-wrapper">
		<?php do_action( 'sprout_settings_tabs' ); ?>
	</h2>
	<div class="clearfix">
		<?php do_action( 'si_settings_page_sub_heading_'.$_GET['page'] ); ?>
	</div>
	
	<script type="text/javascript">
		// chart defaults
		Chart.defaults.global.responsive = true;
		Chart.defaults.global.maintainAspectRatio = true;
		// default to currency formatted
		Chart.defaults.global.multiTooltipTemplate = function(label){
			return label.datasetLabel + ': ' + si_format_money( label.value );
		};
	</script>
	<div id="dashboard-widgets-wrap">
		<div id="dashboard-widgets" class="metabox-holder">
			<div id="postbox-container-1" class="postbox-container">
				<div id="normal-sortables" class="meta-box-sortables ui-sortable">
					<div class="postbox">
						<h3>
							<span><?php _e( 'Invoice Dashboard', 'sprout-invoices' ) ?></span>
						</h3>
						<div class="dashboard_widget inside">
							<div class="main">
								<?php
									$invoice_data = SI_Reporting::total_invoice_data();

									$week_payment_data = SI_Reporting::total_payment_data( 'week' );

									$last_week_payment_data = SI_Reporting::total_payment_data( 'lastweek' );
									$month_payment_data = SI_Reporting::total_payment_data( 'month' );
									$last_month_payment_data = SI_Reporting::total_payment_data( 'lastmonth' );
									$year_payment_data = SI_Reporting::total_payment_data( 'year' );
									$last_year_payment_data = SI_Reporting::total_payment_data( 'lastyear' ); ?>

								<dl>
									<dt><?php _e( 'Outstanding', 'sprout-invoices' ) ?></dt>
									<dd><?php sa_formatted_money( $invoice_data['balance'] )  ?></dd>

									<dt><?php _e( 'Paid (this week)', 'sprout-invoices' ) ?></dt>
									<dd><?php sa_formatted_money( $week_payment_data['totals'] )  ?></dd>

									<dt><?php _e( 'Paid (last week)', 'sprout-invoices' ) ?></dt>
									<dd><?php sa_formatted_money( $last_week_payment_data['totals'] )  ?></dd>

									<dt><?php _e( 'Paid (month to date)', 'sprout-invoices' ) ?></dt>
									<dd><?php sa_formatted_money( $month_payment_data['totals'] )  ?></dd>

									<dt><?php _e( 'Paid (last month)', 'sprout-invoices' ) ?></dt>
									<dd><?php sa_formatted_money( $last_month_payment_data['totals'] )  ?></dd>

									<dt><?php _e( 'Paid (year to date)', 'sprout-invoices' ) ?></dt>
									<dd><?php sa_formatted_money( $year_payment_data['totals'] )  ?></dd>

									<dt><?php _e( 'Paid (last year)', 'sprout-invoices' ) ?></dt>
									<dd><?php sa_formatted_money( $last_year_payment_data['totals'] )  ?></dd>
								</dl>

								<?php
									$args = array(
										'orderby' => 'modified',
										'post_type' => SI_Invoice::POST_TYPE,
										'post_status' => 'any', // Not Written-off?
										'posts_per_page' => 5,
										'fields' => 'ids',
										);
									$invoices = new WP_Query( $args ); ?>

								<?php if ( ! empty( $invoices->posts ) ) : ?>
									<b><?php _e( 'Latest Updates', 'sprout-invoices' ) ?></b> 
									<ul>
										<?php foreach ( $invoices->posts as $invoice_id ) : ?>
											<li><a href="<?php echo get_edit_post_link( $invoice_id ) ?>"><?php echo get_the_title( $invoice_id ) ?></a> &mdash; <?php echo date( get_option( 'date_format' ), get_post_modified_time( 'U', false, $invoice_id ) ) ?></li>
										<?php endforeach ?>
									</ul>
								<?php else : ?>
									<p>
										<b><?php _e( 'Latest Updates', 'sprout-invoices' ) ?></b><br/>
										<?php _e( 'No invoices found.', 'sprout-invoices' ) ?>
									</p>
								<?php endif ?>

								<?php
									$invoices = SI_Invoice::get_overdue_invoices( apply_filters( 'si_dashboard_get_overdue_invoices_from', current_time( 'timestamp' ) - ( DAY_IN_SECONDS * 14 ) ), apply_filters( 'si_dashboard_get_overdue_invoices_to', current_time( 'timestamp' ) ) ); ?>

								<?php if ( ! empty( $invoices ) ) : ?>
									<b><?php _e( 'Recently Overdue &amp; Unpaid', 'sprout-invoices' ) ?></b> 
									<ul>
										<?php foreach ( $invoices as $invoice_id ) : ?>
											<li><a href="<?php echo get_edit_post_link( $invoice_id ) ?>"><?php echo get_the_title( $invoice_id ) ?></a> &mdash; <?php printf( __( 'Due: %s', 'sprout-invoices' ), date_i18n( get_option( 'date_format' ), si_get_invoice_due_date( $invoice_id ) ) ) ?></li>
										<?php endforeach ?>
									</ul>
								<?php else : ?>
									<p>
										<b><?php _e( 'Overdue &amp; Unpaid', 'sprout-invoices' ) ?></b><br/>
										<?php _e( 'No overdue or unpaid invoices.', 'sprout-invoices' ) ?>
									</p>
								<?php endif ?>
							</div>
						</div>
					</div>
					<div class="postbox">
						<h3>
							<span><?php _e( 'Invoiced &amp; Payments', 'sprout-invoices' ) ?></span>
						</h3>
						<div class="dashboard_widget inside">
							<div class="main">
								<canvas id="invoice_payments_chart" min-height="300" max-height="500"></canvas>
								<script type="text/javascript" charset="utf-8">
									var inv_data = {};
									
									function invoice_payments_chart() {
										var can = jQuery('#invoice_payments_chart');
										var ctx = can.get(0).getContext("2d");

										var chart = new Chart(ctx).Line( inv_data );
									}

									var inv_chart_data = function () {
										jQuery.post( '<?php echo esc_url_raw( add_query_arg( array( 'dept_filter' => $dept ), get_admin_url().'admin-ajax.php' ) ) ?>', { 
											action: '<?php echo SI_Reporting::AJAX_ACTION ?>', 
											data: 'invoice_payments', 
											segment: 'weeks', 
											span: 6, 
											security: '<?php echo wp_create_nonce( SI_Reporting::AJAX_NONCE ) ?>' 
											},
											function( data ) {
												inv_data = {
													labels: data.labels,
													datasets: [
														{
															label: "<?php _e( 'Invoiced', 'sprout-invoices' ) ?>",
															fillColor: "rgba(134,189,72,0.2)",
															strokeColor: "rgba(134,189,72,1)",
															pointColor: "rgba(134,189,72,1)",
															pointStrokeColor: "#fff",
															pointHighlightFill: "#fff",
															pointHighlightStroke: "rgba(134,189,72)",
															data: data.invoices
														},
														{
															label: "<?php _e( 'Payments', 'sprout-invoices' ) ?>",
															fillColor: "rgba(38,41,44,0.2)",
															strokeColor: "rgba(38,41,44,1)",
															pointColor: "rgba(38,41,44,1)",
															pointStrokeColor: "#fff",
															pointHighlightFill: "#fff",
															pointHighlightStroke: "rgba(38,41,44,1)",
															data: data.payments
														}
													]
												}
												invoice_payments_chart();
											}
										);
									};

									jQuery(document).ready(function($) {
										inv_chart_data();
									});
								</script>
								<p class="description"><?php _e( 'Compares total invoiced and the total payments.', 'sprout-invoices' ) ?></p>
							</div>
						</div>
					</div>
					
					<div class="postbox">
						<h3>
							<span><?php _e( 'Outstanding Balances &amp; Payments', 'sprout-invoices' ) ?></span>
						</h3>
						<div class="dashboard_widget inside">
							<div class="main">
								<canvas id="balance_totals_chart" min-height="300" max-height="500"></canvas>
								<script type="text/javascript" charset="utf-8">
									var balance_data = {};

									function balance_totals_chart() {
										var can = jQuery('#balance_totals_chart');
										var ctx = can.get(0).getContext("2d");                
										var chart = new Chart(ctx).Line( balance_data );
									}

									var balance_chart_data = function () {
										jQuery.post( '<?php echo esc_url_raw( add_query_arg( array( 'dept_filter' => $dept ), get_admin_url().'admin-ajax.php' ) ) ?>', { 
											action: '<?php echo SI_Reporting::AJAX_ACTION ?>', 
											data: 'balance_invoiced', 
											segment: 'weeks', 
											span: 6, 
											security: '<?php echo wp_create_nonce( SI_Reporting::AJAX_NONCE ) ?>' 
											},
											function( data ) {
												balance_data = {
													labels: data.labels,
													datasets: [
														{
															label: "<?php _e( 'Invoice Balances', 'sprout-invoices' ) ?>",
															fillColor: "rgba(255,90,94,0.2)",
															strokeColor: "rgba(255,90,94,1)",
															pointColor: "rgba(255,90,94,1)",
															pointStrokeColor: "#fff",
															pointHighlightFill: "#fff",
															pointHighlightStroke: "rgba(255,90,94,1)",
															data: data.balances
														},
														{
															label: "<?php _e( 'Payments', 'sprout-invoices' ) ?>",
															fillColor: "rgba(38,41,44,0.2)",
															strokeColor: "rgba(38,41,44,1)",
															pointColor: "rgba(38,41,44,1)",
															pointStrokeColor: "#fff",
															pointHighlightFill: "#fff",
															pointHighlightStroke: "rgba(38,41,44,1)",
															data: data.payments
														}
													]
												}
												balance_totals_chart();
											}
										);
									};

									jQuery(document).ready(function($) {
										balance_chart_data();
									});
								</script>
								<p class="description"><?php _e( 'Shows total outstanding balance and payments by week', 'sprout-invoices' ) ?></p>
							</div>
						</div>
					</div>

					<div class="postbox">
						<h3>
							<span><?php _e( 'Payment Status', 'sprout-invoices' ) ?></span>
						</h3>
						<div class="dashboard_widget inside">
							<div class="main">
								<canvas id="payments_status_chart" min-height="300" max-height="500"></canvas>
								<script type="text/javascript" charset="utf-8">

									var payment_status_data = {};

									function payments_status_chart() {
										var can = jQuery('#payments_status_chart');
										var ctx = can.get(0).getContext("2d");
										var chart = new Chart(ctx).Doughnut(payment_status_data );
									}

									var payments_status_chart_data = function () {
										jQuery.post( '<?php echo esc_url_raw( add_query_arg( array( 'dept_filter' => $dept ), get_admin_url().'admin-ajax.php' ) ) ?>', { 
											action: '<?php echo SI_Reporting::AJAX_ACTION ?>', 
											data: 'payment_statuses', 
											segment: 'weeks', 
											span: 6, 
											security: '<?php echo wp_create_nonce( SI_Reporting::AJAX_NONCE ) ?>' 
											},
											function( data ) {
												payment_status_data = [
													{
														value: data.status_pending,
														color:"rgba(255,165,0,1)",
														highlight: "rgba(255,165,0,.8)",
														label: "Pending"
													},
													{
														value: data.status_complete,
														color: "rgba(134,189,72,1)",
														highlight: "rgba(134,189,72,.8)",
														label: "Paid"
													},
													{
														value: data.status_void,
														color:"rgba(38,41,44,1)",
														highlight: "rgba(38,41,44,.8)",
														label: "Void"
													}
												];
												payments_status_chart();
											}
										);
									};

									jQuery(document).ready(function($) {
										payments_status_chart_data();
									});
								</script>
								<p class="description"><?php _e( 'Statuses from payments from the last 3 weeks', 'sprout-invoices' ) ?></p>
							</div>
						</div>
					</div>

					<div class="postbox">
						<h3>
							<span><?php _e( 'Invoice Status', 'sprout-invoices' ) ?></span>
						</h3>
						<div class="dashboard_widget inside">
							<div class="main">
								<canvas id="invoice_status_chart" min-height="300" max-height="500"></canvas>
								<script type="text/javascript" charset="utf-8">

									var invoice_status_data = {};

									function invoice_status_chart() {
										var can = jQuery('#invoice_status_chart');
										var ctx = can.get(0).getContext("2d");
										var chart = new Chart(ctx).Doughnut(invoice_status_data );
									}

									var invoice_status_data = function () {
										jQuery.post( '<?php echo esc_url_raw( add_query_arg( array( 'dept_filter' => $dept ), get_admin_url().'admin-ajax.php' ) ) ?>', { 
											action: '<?php echo SI_Reporting::AJAX_ACTION ?>', 
											data: 'invoice_statuses', 
											segment: 'weeks', 
											span: 6, 
											security: '<?php echo wp_create_nonce( SI_Reporting::AJAX_NONCE ) ?>' 
											},
											function( data ) {
												invoice_status_data = [
													{
														value: data.status_temp,
														color:"rgba(85,181,232,1)",
														highlight: "rgba(85,181,232,.8)",
														label: "<?php _e( 'Temp', 'sprout-invoices' ) ?>"
													},
													{
														value: data.status_pending,
														color:"rgba(255,165,0,1)",
														highlight: "rgba(255,165,0,.8)",
														label: "<?php _e( 'Pending', 'sprout-invoices' ) ?>"
													},
													{
														value: data.status_partial,
														color:"rgba(38,41,44,1)",
														highlight: "rgba(38,41,44,.8)",
														label: "<?php _e( 'Partial', 'sprout-invoices' ) ?>"
													},
													{
														value: data.status_complete,
														color: "rgba(134,189,72,1)",
														highlight: "rgba(134,189,72,.8)",
														label: "<?php _e( 'Complete', 'sprout-invoices' ) ?>"
													},
													{
														value: data.status_writeoff,
														color:"rgba(38,41,44,1)",
														highlight: "rgba(38,41,44,.8)",
														label: "<?php _e( 'Written Off', 'sprout-invoices' ) ?>"
													}
												];
												invoice_status_chart();
											}
										);
									};

									jQuery(document).ready(function($) {
										invoice_status_data();
									});
								</script>
								<p class="description"><?php _e( 'Statuses from invoices from the last 3 weeks', 'sprout-invoices' ) ?></p>
							</div>
						</div>
					</div>

				</div>
			</div>
			<div id="postbox-container-2" class="postbox-container">
				<div id="side-sortables" class="meta-box-sortables ui-sortable">
					<div class="postbox">
						<h3>
							<span><?php _e( 'Estimate Dashboard', 'sprout-invoices' ) ?></span>
						</h3>
						<div class="dashboard_widget inside">
							<div class="main">
								<?php
									$args = array(
										'orderby' => 'modified',
										'post_type' => SI_Estimate::POST_TYPE,
										'post_status' => 'any', // Not Written-off?
										'posts_per_page' => 3,
										'fields' => 'ids',
										);
									$estimates = new WP_Query( $args ); ?>

								<?php if ( ! empty( $estimates->posts ) ) : ?>
									<b><?php _e( 'Latest Updates', 'sprout-invoices' ) ?></b> 
									<ul>
										<?php foreach ( $estimates->posts as $estimate_id ) : ?>
											<li><a href="<?php echo get_edit_post_link( $estimate_id ) ?>"><?php echo get_the_title( $estimate_id ) ?></a> &mdash; <?php echo date( get_option( 'date_format' ), get_post_modified_time( 'U', false, $estimate_id ) ) ?></li>
										<?php endforeach ?>
									</ul>
								<?php else : ?>
									<p>
										<b><?php _e( 'Latest Updates', 'sprout-invoices' ) ?></b><br/>
										<?php _e( 'No recent estimates found.', 'sprout-invoices' ) ?>
									</p>
								<?php endif ?>

								<?php
									$args = array(
										'post_type' => SI_Estimate::POST_TYPE,
										'post_status' => array( SI_Estimate::STATUS_REQUEST ),
										'posts_per_page' => 3,
										'fields' => 'ids',
										);
									$estimates = new WP_Query( $args ); ?>

								<?php if ( ! empty( $estimates->posts ) ) : ?>
									<b><?php _e( 'Recent Requests', 'sprout-invoices' ) ?></b> 
									<ul>
										<?php foreach ( $estimates->posts as $estimate_id ) : ?>
											<li><a href="<?php echo get_edit_post_link( $estimate_id ) ?>"><?php echo get_the_title( $estimate_id ) ?></a> &mdash; <?php echo date( get_option( 'date_format' ), get_post_time( 'U', false, $estimate_id ) ) ?></li>
										<?php endforeach ?>
									</ul>
								<?php else : ?>
									<p>
										<b><?php _e( 'Recent Requests', 'sprout-invoices' ) ?></b><br/>
										<?php _e( 'No recently requested estimates.', 'sprout-invoices' ) ?>
									</p>
								<?php endif ?>

								<?php
									$args = array(
										'orderby' => 'modified',
										'post_type' => SI_Estimate::POST_TYPE,
										'post_status' => array( SI_Estimate::STATUS_DECLINED ),
										'posts_per_page' => 3,
										'fields' => 'ids',
										);
									$estimates = new WP_Query( $args ); ?>

								<?php if ( ! empty( $estimates->posts ) ) : ?>
									<b><?php _e( 'Recent Declined', 'sprout-invoices' ) ?></b> 
									<ul>
										<?php foreach ( $estimates->posts as $estimate_id ) : ?>
											<li><a href="<?php echo get_edit_post_link( $estimate_id ) ?>"><?php echo get_the_title( $estimate_id ) ?></a> &mdash; <?php echo date( get_option( 'date_format' ), get_post_time( 'U', false, $estimate_id ) ) ?></li>
										<?php endforeach ?>
									</ul>
								<?php else : ?>
									<p>
										<b><?php _e( 'Recent Declined', 'sprout-invoices' ) ?></b><br/>
										<?php _e( 'No recently declined estimates.', 'sprout-invoices' ) ?>
									</p>
								<?php endif ?>

								<?php
									$args = array(
										'post_type' => SI_Estimate::POST_TYPE,
										'post_status' => array( SI_Estimate::STATUS_PENDING ),
										'posts_per_page' => 3,
										'fields' => 'ids',
										'meta_query' => array(
												array(
													'meta_key' => '_expiration_date',
													'value' => array( 0, current_time( 'timestamp' ) ),
													'compare' => 'BETWEEN',
													),
											),
										);
									$estimates = new WP_Query( $args ); ?>

								<?php if ( ! empty( $estimates->posts ) ) : ?>
									<b><?php _e( 'Expired &amp; Pending', 'sprout-invoices' ) ?></b> 
									<ul>
										<?php foreach ( $estimates->posts as $estimate_id ) : ?>
											<li><a href="<?php echo get_edit_post_link( $estimate_id ) ?>"><?php echo get_the_title( $estimate_id ) ?></a> &mdash; <?php
												$expired_or_pending = ( si_get_estimate_expiration_date( $estimate_id ) > current_time( 'timestamp' ) ) ? __( 'Expired', 'sprout-invoices' ) : __( 'Pending', 'sprout-invoices' );
												printf( '%s: %s' , $expired_or_pending, date( get_option( 'date_format' ), si_get_estimate_expiration_date( $estimate_id ) ) ) ?></li>
										<?php endforeach ?>
									</ul>
								<?php else : ?>
									<p>
										<b><?php _e( 'Expired &amp; Pending', 'sprout-invoices' ) ?></b><br/>
										<?php _e( 'No recently expired or pending estimates.', 'sprout-invoices' ) ?>
									</p>
								<?php endif ?>
							</div>
						</div>
					</div>
					<div class="postbox">
						<h3>
							<span><?php _e( 'Estimates &amp; Invoices', 'sprout-invoices' ) ?></span>
						</h3>
						<div class="dashboard_widget inside">
							<div class="main">
								<canvas id="est_invoices_chart" min-height="300" max-height="500"></canvas>
								<script type="text/javascript" charset="utf-8">
									var est_inv_totals_data = {};

									function est_invoices_chart() {
										var can = jQuery('#est_invoices_chart');
										var ctx = can.get(0).getContext("2d");
										var chart = new Chart(ctx).Bar( est_inv_totals_data, {
											multiTooltipTemplate: "<%= value %>",
										} );
									}

									var est_inv_totals_data = function () {
										jQuery.post( '<?php echo esc_url_raw( add_query_arg( array( 'dept_filter' => $dept ), get_admin_url().'admin-ajax.php' ) ) ?>', { 
											action: '<?php echo SI_Reporting::AJAX_ACTION ?>', 
											data: 'est_invoice_totals', 
											segment: 'weeks', 
											span: 6, 
											security: '<?php echo wp_create_nonce( SI_Reporting::AJAX_NONCE ) ?>' 
											},
											function( data ) {
												est_inv_totals_data = {
													labels: data.labels,
													datasets: [
														{
															label: "<?php _e( 'Estimates', 'sprout-invoices' ) ?>",
															fillColor: "rgba(255,165,0,0.2)",
															strokeColor: "rgba(255,165,0,1)",
															pointColor: "rgba(255,165,0,1)",
															pointStrokeColor: "#fff",
															pointHighlightFill: "#fff",
															pointHighlightStroke: "rgba(255,165,0,1)",
															data: data.estimates
														},
														{
															label: "<?php _e( 'Invoices', 'sprout-invoices' ) ?>",
															fillColor: "rgba(134,189,72,0.2)",
															strokeColor: "rgba(134,189,72,1)",
															pointColor: "rgba(134,189,72,1)",
															pointStrokeColor: "#fff",
															pointHighlightFill: "#fff",
															pointHighlightStroke: "rgba(134,189,72)",
															data: data.invoices
														}
													]
												}
												est_invoices_chart();
											}
										);
									};

									jQuery(document).ready(function($) {
										est_inv_totals_data();
									});
								</script>
								<p class="description"><?php _e( 'Shows total estimates and invoices by week.', 'sprout-invoices' ) ?></p>
							</div>
						</div>
					</div>
					<div class="postbox">
						<h3>
							<span><?php _e( 'Requests &amp; Converted Requests', 'sprout-invoices' ) ?></span>
						</h3>
						<div class="dashboard_widget inside">
							<div class="main">
								<canvas id="req_estimates_chart" min-height="300" max-height="500"></canvas>
								<script type="text/javascript" charset="utf-8">
									var req_est_totals_data = {};

									function req_estimates_chart() {
										var can = jQuery('#req_estimates_chart');
										var ctx = can.get(0).getContext("2d");
										var chart = new Chart(ctx).Bar( req_est_totals_data );
									}

									var req_est_totals_data = function () {
										jQuery.post( '<?php echo esc_url_raw( add_query_arg( array( 'dept_filter' => $dept ), get_admin_url().'admin-ajax.php' ) ) ?>', { 
											action: '<?php echo SI_Reporting::AJAX_ACTION ?>', 
											data: 'req_to_inv_totals', 
											segment: 'weeks', 
											span: 6, 
											security: '<?php echo wp_create_nonce( SI_Reporting::AJAX_NONCE ) ?>' 
											},
											function( data ) {
												req_est_totals_data = {
													labels: data.labels,
													datasets: [
														{
															label: "<?php _e( 'Requests', 'sprout-invoices' ) ?>",
															fillColor: "rgba(85,181,232,0.2)",
															strokeColor: "rgba(85,181,232,1)",
															pointColor: "rgba(85,181,232,1)",
															pointStrokeColor: "#fff",
															pointHighlightFill: "#fff",
															pointHighlightStroke: "rgba(85,181,232,1)",
															data: data.requests
														},
														{
															label: "<?php _e( 'Invoices Generated', 'sprout-invoices' ) ?>",
															fillColor: "rgba(134,189,72,0.2)",
															strokeColor: "rgba(134,189,72,1)",
															pointColor: "rgba(134,189,72,1)",
															pointStrokeColor: "#fff",
															pointHighlightFill: "#fff",
															pointHighlightStroke: "rgba(134,189,72)",
															data: data.invoices_generated
														}
													]
												}
												req_estimates_chart();
											}
										);
									};

									jQuery(document).ready(function($) {
										req_est_totals_data();
									});
								</script>
								<p class="description"><?php _e( 'Shows total estimate requests and the total converted into invoices.', 'sprout-invoices' ) ?></p>
							</div>
						</div>
					</div>
					
					<div class="postbox">
						<h3>
							<span><?php _e( 'Estimate Status', 'sprout-invoices' ) ?></span>
						</h3>
						<div class="dashboard_widget inside">
							<div class="main">
								<canvas id="estimate_status_chart" min-height="300" max-height="500"></canvas>
								<script type="text/javascript" charset="utf-8">

									var estimate_status_data = {};

									function estimate_status_chart() {
										var can = jQuery('#estimate_status_chart');
										var ctx = can.get(0).getContext("2d");
										var chart = new Chart(ctx).Doughnut(estimate_status_data, {
												responsive: true,
												maintainAspectRatio: true
											});
									}

									var estimate_status_data = function () {
										jQuery.post( '<?php echo esc_url_raw( add_query_arg( array( 'dept_filter' => $dept ), get_admin_url().'admin-ajax.php' ) ) ?>', { 
											action: '<?php echo SI_Reporting::AJAX_ACTION ?>', 
											data: 'estimates_statuses', 
											segment: 'weeks', 
											span: 6, 
											security: '<?php echo wp_create_nonce( SI_Reporting::AJAX_NONCE ) ?>' 
											},
											function( data ) {
												estimate_status_data = [
													{
														value: data.status_request,
														color:"rgba(85,181,232,1)",
														highlight: "rgba(85,181,232,.8)",
														label: "<?php _e( 'Request', 'sprout-invoices' ) ?>"
													},
													{
														value: data.status_pending,
														color:"rgba(255,165,0,1)",
														highlight: "rgba(255,165,0,.8)",
														label: "<?php _e( 'Pending', 'sprout-invoices' ) ?>"
													},
													{
														value: data.status_approved,
														color: "rgba(134,189,72,1)",
														highlight: "rgba(134,189,72,.8)",
														label: "<?php _e( 'Approved', 'sprout-invoices' ) ?>"
													},
													{
														value: data.status_declined,
														color:"rgba(38,41,44,1)",
														highlight: "rgba(38,41,44,.8)",
														label: "<?php _e( 'Declined', 'sprout-invoices' ) ?>"
													}
												];
												estimate_status_chart();
											}
										);
									};

									jQuery(document).ready(function($) {
										estimate_status_data();
									});
								</script>
								<p class="description"><?php _e( 'Statuses from estimates from the last 3 weeks', 'sprout-invoices' ) ?></p>
							</div>
						</div>
					</div>

				</div>
			</div>
		</div>
	</div>
</div>
