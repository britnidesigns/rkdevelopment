<span id="time_importing">
	<span class="time_import_question">
		<?php printf( '<button id="time_import_question_answer" class="button">%s</button>', __( 'Import Time', 'sprout-invoices' ) ) ?>
		<span title="<?php _e( 'Import time tracked to your projects.', 'sprout-invoices' ) ?>" class="helptip add_item_help add_time_help"></span>
	</span>
	<span id="time_importing_project_selection" class="cloak">
		<?php si_projects_select( 0, $client_id, true, 'doc_project_time' ) ?>
		<input id="time_tracking_nonce" type="hidden" value="<?php echo wp_create_nonce( SI_Time_Tracking_Premium::SUBMISSION_NONCE ) ?>" />
	</span><!-- #time_importing_project_selection -->
</span><!-- #time_importing -->
<?php if ( isset( $_GET[SI_Time_Tracking_Premium::IMPORT_QUERY_VAR] ) && get_post_type( $_GET[SI_Time_Tracking_Premium::IMPORT_QUERY_VAR] ) === SI_Project::POST_TYPE ) : ?>
	<script type="text/javascript">
		jQuery(function() {
			jQuery('ol.items_list > li.item').not('#line_item_default').remove();
			jQuery('#line_item_list').after(si_js_object.inline_spinner);
			jQuery('#time_importing_project_selection select').val( '<?php echo $_GET[SI_Time_Tracking_Premium::IMPORT_QUERY_VAR] ?>' ).trigger('change');
		});
	</script>
<?php endif ?>