<span id="expense_importing">
	<span class="expense_import_question">
		<?php printf( '<button id="expense_import_question_answer" class="button">%s</button>', __( 'Import Expense', 'sprout-invoices' ) ) ?>
		<span title="<?php _e( 'Import expense tracked to your projects.', 'sprout-invoices' ) ?>" class="helptip add_item_help add_expense_help"></span>
	</span>
	<span id="expense_importing_project_selection" class="cloak">
		<?php si_projects_select( 0, $client_id, true, 'doc_project_expense' ) ?>
		<input id="expense_tracking_nonce" type="hidden" value="<?php echo wp_create_nonce( SI_Expense_Tracking_Premium::SUBMISSION_NONCE ) ?>" />
	</span><!-- #expense_importing_project_selection -->
</span><!-- #expense_importing -->
<?php if ( isset( $_GET[ SI_Expense_Tracking_Premium::IMPORT_QUERY_VAR ] ) && get_post_type( $_GET[ SI_Expense_Tracking_Premium::IMPORT_QUERY_VAR ] ) === SI_Project::POST_TYPE ) : ?>
	<script type="text/javascript">
		jQuery(function() {
			jQuery('ol.items_list > li.item').not('#line_item_default').remove();
			jQuery('#line_item_list').after(si_js_object.inline_spinner);
			jQuery('#expense_importing_project_selection select').val( '<?php echo $_GET[ SI_Expense_Tracking_Premium::IMPORT_QUERY_VAR ] ?>' ).trigger('change');
		});
	</script>
<?php endif ?>
