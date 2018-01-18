<?php
	$comment_ids = array();
foreach ( $comments as $comment ) {
	$comment_ids[] = $comment->comment_ID;
}
	$show = ( isset( $_GET['respond'] ) && in_array( $_GET['respond'], $comment_ids ) ) ? true : false ;
	?>

<div id="line_item_comments_<?php echo str_replace( '.', '-', $position ) ?>" class="line_item_comments<?php if ( ! $show ) { echo ' cloak'; } ?>">
	<div class="line_items_inner_wrap">
		<ol class="line_items_comments_list">
			<?php if ( ! empty( $comments ) ) : ?>
				<?php foreach ( $comments as $comment ) : ?>
					<li>
						<div id="comment_<?php echo (int) $comment->comment_ID; ?>" class="comment clearfix<?php if ( isset( $_GET['respond'] ) && $_GET['respond'] == $comment->comment_ID ) { echo ' highlight'; } ?>">
							<span class="comment_meta vcard">
								<?php echo get_avatar( $comment->comment_author_email, 25 ); ?>
							</span>

							<span class="comment_content">
								<span class="comment_date">
									<?php
										printf( '<time datetime="%1$s">%2$s</time>',
											mysql2date( 'd', $comment->comment_date ),
											mysql2date( get_option( 'date_format' ), $comment->comment_date )
										); ?>
								</span>
								<?php comment_text( $comment->comment_ID ); ?>
							</span>
						</div>
					</li>
				<?php endforeach ?>
			<?php endif ?>
		</ol>

		<div id="<?php echo str_replace( '.', '-', $position ) ?>_line_item_comment_wrap" class="line_item_comment_wrap clearfix">
			<form id="<?php echo esc_attr( $position ); ?>_line_item" class="submit_doc_note_form" action="line_item_comment" method="post">
				<textarea id="line_<?php echo esc_attr( $position ); ?>_comment" name="comment" placeholder="<?php _e( 'Anything we can add/change on this line item?', 'sprout-invoices' ) ?>"></textarea>
				<input name="item_position" value="<?php echo esc_attr( $position ); ?>" type="hidden" />
				<input name="doc_id" value="<?php echo esc_attr( $doc_id ); ?>" type="hidden" />
				<input name="note_sec" value="<?php echo wp_create_nonce( SI_Controller::NONCE ) ?>" type="hidden" />
				<button type="submit" name="submit_note" class="submit button primary_button"><?php _e( 'Submit Comment', 'sprout-invoices' ) ?></button>
			</form>
		</div>
	</div>
</div>

