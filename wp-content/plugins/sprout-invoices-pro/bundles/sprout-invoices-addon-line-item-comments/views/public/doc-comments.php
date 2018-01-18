<?php if ( ! empty( $comments ) ) : ?>
	<ol>
		<?php foreach ( $comments as $comment ) : ?>
			<li>
				<div id="comment_<?php echo (int) $comment->comment_ID; ?>" class="comment clearfix">
					<span class="comment_meta vcard">
						<?php echo get_avatar( $comment->comment_ID, 25 ); ?>
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
	</ol>
<?php endif ?>