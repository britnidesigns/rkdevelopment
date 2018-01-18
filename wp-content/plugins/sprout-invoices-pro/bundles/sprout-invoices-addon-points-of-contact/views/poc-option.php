<?php if ( empty( $associated_users ) ) :  ?>
	<p><?php _e( 'No associated users saved.', 'sprout-invoices' )  ?></p>
<?php else : ?>
	<p>
		<select name="<?php echo $field_name ?>" id="si_client_poc_select" class="select2">
			<option value=""></option>
			<?php foreach ( $associated_users as $user_id ) :  ?>
				<?php
					$user = get_userdata( $user_id );
				if ( ! is_a( $user, 'WP_User' ) ) {
					continue;
				} ?>
				<option value="<?php echo (int) $user_id ?>" <?php selected( $user_id, $selected_id ) ?> data-url="<?php echo esc_url( admin_url( 'user-edit.php?user_id=' . $user_id ) ) ?>" data-user-email="<?php echo esc_attr( $user->user_email ); ?>"><?php echo esc_html( $user->display_name ) ?></option>
			<?php endforeach ?>
			<option value="0"><?php _e( '(Disable All Notifications)', 'sprout-invoices' ) ?></option>
		</select>
	</p>
	<p><?php _e( 'Select a single point of contact; this will limit all automatic notifications to this single associated user (unless manually sent to others).', 'sprout-invoices' ) ?></p>
<?php endif ?>
<hr/>
