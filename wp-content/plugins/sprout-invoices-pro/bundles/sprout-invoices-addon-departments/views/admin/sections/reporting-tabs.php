<ul class="subsubsub subsubnav">
	<li class="departments"><a href="<?php echo esc_url( remove_query_arg( $query_var ) ) ?>" <?php if ( $current == '' ) echo 'class="current"' ?>><?php _e( 'All Departments', 'sprout-invoices' ) ?></a></li>
	<?php foreach ( $departments as $key => $name ): ?>
		<li class="invoices">| <a href="<?php echo esc_url( add_query_arg( $query_var, $key ) ) ?>" <?php if ( $current == $key ) echo 'class="current"' ?>><?php echo esc_html( $name ) ?></a></li>
	<?php endforeach ?>
</ul>