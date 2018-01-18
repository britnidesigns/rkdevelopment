<div id="admin_comment_list">
	<?php if ( !empty( $comments ) ): ?>
		<ol>
			<?php foreach ( $comments as $comment ): ?>
				<li>
					<div id="comment_<?php echo (int) $comment->comment_ID; ?>" class="comment clearfix<?php if ( isset( $_GET['respond'] ) && $_GET['respond'] == $comment->comment_ID ) echo ' highlight' ?>">
						<span class="comment_meta vcard">
							<?php echo get_avatar( $comment->comment_author_email, 25 ); ?>
						</span>

						<span class="comment_content">
							<span class="comment_date">
								<?php 
									printf( '<time datetime="%1$s">%2$s</time>',
										mysql2date('d', $comment->comment_date ),
										mysql2date(get_option('date_format'), $comment->comment_date) 
									); ?>
							</span>
							<?php comment_text( $comment->comment_ID ); ?>
						</span>
					</div>
				</li>
			<?php endforeach ?>
		</ol>
	<?php endif ?>
	<p>
		<textarea name="comment_response" id="si_comment_response" style="width:100%"></textarea>
		
		<button class="button submit_comment_response" data-comment-id="" data-li-position="<?php echo esc_attr( $position ); ?>" data-doc-id="<?php echo (int) $doc_id ?>" data-sec="<?php echo wp_create_nonce( SI_Controller::NONCE ) ?>"><?php _e( 'Submit Response', 'sprout-invoices' ) ?></button>
	</p>
</div>