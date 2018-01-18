<!DOCTYPE html>
<html <?php language_attributes(); ?>>
	<head>
		<meta charset="<?php bloginfo( 'charset' ); ?>" />
		<meta name="viewport" content="width=device-width, initial-scale=1">
		<link rel="profile" href="http://gmpg.org/xfn/11" />
		<?php wp_head(); ?>
		<script type="text/javascript">
			/* <![CDATA[ */
			var si_js_object = <?php echo wp_json_encode( SI_Controller::get_localized_js() ); ?>;
			/* ]]> */
		</script>
		<meta name="robots" content="noindex, nofollow" />
	</head>

	<body id="expense_tracker" <?php body_class(); ?>>

		<div id="outer_doc_wrap">
			<div id="expense_tracker_wrap">
				<div class="front_end_form clearfix">
					<?php sa_admin_fields( $fields, 'expense' ); ?>
				</div><!-- #tt_body -->
				<div id="tt_save" class="clearfix">
					<button href="javascript:void(0)" id="create_expense_entry" class="button button-large button-primary"><?php _e( 'Log Expense', 'sprout-invoices' ) ?></button>
				</div><!-- #tt_save -->
			</div><!-- #expense_tracker_wrap -->
		</div>
	</body>
	<?php wp_footer() ?>
</html>
<?php do_action( 'invoice_viewed' ) ?>
