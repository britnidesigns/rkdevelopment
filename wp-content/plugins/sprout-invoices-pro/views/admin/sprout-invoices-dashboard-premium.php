<?php do_action( 'sprout_settings_header' ); ?>

<div id="si_dashboard" class="wrap about-wrap">

	<div id="si_settings" class="welcome_content clearfix">

		<?php do_action( 'sprout_settings_messages' ) ?>

		<?php if ( SI_Updates::license_status() != 'valid' ) :  ?>

		<div class="license-overview">

			<h1 class="headline_callout"><?php _e( 'Getting Started', 'sprout-invoices' ) ?></h1>

			<div class="activate_message clearfix">
				<h4><?php _e( 'Start off by activating your pro license...', 'sprout-invoices' ) ?></h4>

				<div class="activation_inputs clearfix">
					<input type="text" name="<?php echo SI_Updates::LICENSE_KEY_OPTION ?>" id="<?php echo SI_Updates::LICENSE_KEY_OPTION ?>" value="<?php echo SI_Updates::license_key() ?>" class="text-input fat-input <?php echo 'license_'.SI_Updates::license_status() ?>" size="40">

					<?php if ( SI_Updates::license_status() !== false && SI_Updates::license_status() === 'valid' ) : ?>
						<button id="activate_license" class="si_admin_button lg si_muted" disabled="disabled" @click="activateLicense('si_activate_license')" :disabled='isSaving'><?php _e( 'Activate Pro License', 'sprout-invoices' ) ?></button>
						<button id="deactivate_license" class="si_admin_button lg" @click="activateLicense('si_deactivate_license')" :disabled='isSaving'><?php _e( 'Deactivate License', 'sprout-invoices' ) ?></button>
					<?php else : ?>
						<button id="activate_license" class="si_admin_button lg" @click="activateLicense('si_activate_license')" :disabled='isSaving'><?php _e( 'Activate Pro License', 'sprout-invoices' ) ?></button>
					<?php endif ?>
					
					<img
						v-if='isSaving == true'
						id='loading-indicator' src='<?php get_site_url() ?>/wp-admin/images/wpspin_light-2x.gif' alt='Loading indicator' />

					<span id="si_html_message"></span>

					<span class="input_desc help_block"><?php printf( 'You can find your license from your account page at <a href="%s">sproutapps.co</a>', si_get_sa_link( 'https://sproutapps.co/account/' ) ) ?></span>
				</div>
				<p>
					<?php _e( 'An active license for Sprout Invoices provides support and updates. By activating your license, you can get automatic plugin updates from the WordPress dashboard. Updates provide you with the latest bug fixes and the new features each major release brings.', 'sprout-invoices' ) ?>
				</p>
			</div>
		</div>

		<?php endif ?>

		<div class="workflow-overview">

			<h1 class="headline_callout"><?php _e( 'The Sprout Invoices Flow', 'sprout-invoices' ) ?></h1>

			<div class="feature-section col three-col clearfix">
				<div class="col-1">
					<span class="flow_icon icon-handshake"></span>
					<h4><?php _e( 'Lead Generation', 'sprout-invoices' ); ?></h4>
					<p><?php printf( __( "Receiving estimate requests on your site is simplified with Sprout Invoices. <a href='%s'>General Settings</a> has more information on how to add a form to your site as well as settings to integrate with an advanced form plugin, e.g. Gravity Forms or Ninja Forms.", 'sprout-invoices' ), admin_url( 'admin.php?page=sprout-invoices-settings' ) ); ?></p>
					<p><?php printf( "<a href='https://sproutapps.co/support/knowledgebase/sprout-invoices/advanced/customize-estimate-submission-form/' target='_blank' class='si_admin_button si_muted'>%s</a>", __( 'Documentation', 'sprout-invoices' ) ); ?></p>
				</div>
				<div class="col-2">
					<span class="flow_icon icon-sproutapps-estimates"></span>
					<h4><?php _e( 'Estimating', 'sprout-invoices' ); ?></h4>
					<p><?php printf( __( "A new <a href='%s'>estimate</a> is automatically created and notifications are sent after every estimate request submission. The <a href='%s'>notification</a> to you will provide a link to this new estimate; allowing you to review, update, and send the estimate to your prospective client without having to communicate via email.", 'sprout-invoices' ), admin_url( 'post-new.php?post_type='.SI_Estimate::POST_TYPE ),  admin_url( 'admin.php?page=sprout-invoices-notifications' ) ); ?></p>
					<p><?php printf( "<a href='https://sproutapps.co/support/knowledgebase/sprout-invoices/estimates/' target='_blank' class='si_admin_button si_muted'>%s</a>", __( 'Documentation', 'sprout-invoices' ) ); ?></p>
				</div>
				<div class="col-3 last-feature">
					<span class="flow_icon icon-sproutapps-invoices"></span>
					<h4><?php _e( 'Invoicing', 'sprout-invoices' ); ?></h4>
					<p><?php printf( __( "An <a href='%s'>invoice</a> is automatically created from an accepted estimate. By default these newly created invoices are <em>not</em> sent to the client, instead you  will need to review them before sending. Your <a href='%s'>notifications</a> are meant to be setup to help review, mark, and send them out quickly.", 'sprout-invoices' ), admin_url( 'post-new.php?post_type='.SI_Invoice::POST_TYPE ),  admin_url( 'admin.php?page=sprout-invoices-notifications' ) ); ?></p>
					<p><?php printf( "<a href='https://sproutapps.co/support/knowledgebase/sprout-invoices/invoices/' target='_blank' class='si_admin_button si_muted'>%s</a>", __( 'Documentation', 'sprout-invoices' ) ); ?></p>
				</div>
			</div>

		</div>
	</div>

	<div class="welcome_content">
		<h1 class="headline_callout"><?php _e( 'FAQs', 'sprout-invoices' ) ?></h1>
		<div class="feature-section col three-col clearfix">
			<div>
				<h4><?php _e( 'Where do I start?', 'sprout-invoices' ); ?></h4>
				<p>
					<ol>
						<li><?php printf( __( "While Sprout Invoices tried to set some good defaults you'll need to go to <a href='%s'>General Settings</a> and finish settings things up.", 'sprout-invoices' ), admin_url( 'admin.php?page=sprout-invoices-settings' ) ); ?></li>
						<li><?php printf( __( "Setup <a href='%s'>Payment Processor</a> so you can start collecting money! Don't forget to test since you don't want to let your client find out you've configured things incorrectly.", 'sprout-invoices' ), admin_url( 'admin.php?page=sprout-invoices-payments' ) ); ?></li>
						<li><?php printf( __( "There are a lot of <a href='%s'>notifications</a> sent throughout the entire client project process, make sure they have your personality and represent your brand well.", 'sprout-invoices' ), admin_url( 'admin.php?page=sprout-invoices-notifications' ) ); ?></li>
						<li><?php printf( __( "Start <a href='%s'>importing</a> your data from other services (i.e. WP-Invoice, Freshbooks or Harvest).", 'sprout-invoices' ), admin_url( 'admin.php?page=sprout-invoices-import' ) ); ?></li>
						<li><?php _e( 'Grow your business while not forgetting about your loved ones...and the occasional round of golf.', 'sprout-invoices' ) ?></li>
					</ol>
					<p><?php printf( "<a href='https://sproutapps.co/support/knowledgebase/sprout-invoices/sprout-invoices-getting-started/' target='_blank' class='si_admin_button si_muted'>%s</a>", __( 'Documentation', 'sprout-invoices' ) ); ?></p>
				</p>
			</div>
			<div>

				<h4><?php _e( 'Clients &amp; WordPress Users?', 'sprout-invoices' ); ?></h4>
				<p><?php printf( __( "<a href='%s'>Clients</a> have WordPress users associated with them and clients are not limited to a single user either. This allows for you to have multiple points of contact for a company/client.", 'sprout-invoices' ), admin_url( 'edit.php?post_type=sa_client' ) ); ?></p>

				<p><?php printf( "<a href='https://sproutapps.co/support/knowledgebase/sprout-invoices/clients/' target='_blank' class='si_admin_button si_muted'>%s</a>", __( 'Documentation', 'sprout-invoices' ) ); ?></p>

				<h4><?php _e( 'What are Predefined Line Items?', 'sprout-invoices' ); ?></h4>
				<p><?php printf( __( "Predefined line-tems help with the creation of your estimates and invoices by pre-filling line items. Create some tasks that matter to your business before creating your first estimate or invoice and you'll see how they can save you a lot of time.", 'sprout-invoices' ) ); ?></p>

				<p><?php printf( "<a href='https://sproutapps.co/support/knowledgebase/sprout-invoices/invoices/predefined-tasks-line-items/' target='_blank' class='si_admin_button si_muted'>%s</a>", __( 'Documentation', 'sprout-invoices' ) ); ?></p>

				<h4><?php _e( 'How well am I doing?', 'sprout-invoices' ); ?></h4>				
				<p><?php printf( __( "The <a href='%s'>Reports Dashboard</a> should key you on how well your're growing your business. There are reports for the estimates, invoices, payments and clients available for filtering and exporting.", 'sprout-invoices' ), admin_url( 'admin.php?page=sprout-invoices-reports' ) ); ?></p>

				<p><?php printf( "<a href='https://sproutapps.co/support/knowledgebase/sprout-invoices/reports/' target='_blank' class='si_admin_button si_muted'>%s</a>", __( 'Documentation', 'sprout-invoices' ) ); ?></p>
			</div>
			<div class="last-feature">
				<h4><?php _e( 'Can I import from X service or WP-Invoice?', 'sprout-invoices' ); ?></h4>
				<p><?php printf( __( 'Yes! WP-Invoice, Harvest and Freshbooks importers are now available.', 'sprout-invoices' ) ); ?></p>
				<p><?php printf( "<a href='https://sproutapps.co/support/knowledgebase/sprout-invoices/sprout-invoices-getting-started/importing/' target='_blank' class='si_admin_button si_muted'>%s</a>", __( 'Documentation', 'sprout-invoices' ) ); ?></p>

				<h4><?php _e( 'I need help! Where is the support?', 'sprout-invoices' ); ?></h4>
				<p><?php printf( __( "We want to make sure using Sprout Invoices is enjoyable and not a hassle. Sprout Apps has some pretty awesome <a href='%s'>support</a> and a budding <a href='%s'>knowledgebase</a> that will help you get anything resolved.", 'sprout-invoices' ), 'https://sproutapps.co//support/', 'https://sproutapps.co/support/knowledgebase/' ); ?></p>

				<p><?php printf( "<a href='%s' target='_blank' class='si_admin_button si_muted'>%s</a>", si_get_sa_link( 'https://sproutapps.co/support/' ), __( 'Support', 'sprout-invoices' ) ); ?>&nbsp;<?php printf( "<a href='https://sproutapps.co/support/knowledgebase/sprout-invoices/' target='_blank' class='si_admin_button si_muted'>%s</a>", __( 'Documentation', 'sprout-invoices' ) ); ?></p>

			</div>
		</div>

	</div>

</div>

