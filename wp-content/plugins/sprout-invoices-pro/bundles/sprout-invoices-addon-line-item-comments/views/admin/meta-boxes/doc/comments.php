<dl id="comments_list">
	<?php foreach ( $comments as $comment ): ?>
		<?php 
			$position_value = get_comment_meta( $comment->comment_ID, $meta_key, true );
			$position = ( $position_value ) ? $position_value : '' ;
			 ?>
		<dt>
			<span class="history_status comment"><?php echo (int) $comment->comment_author ?></span><br/>
			<span class="history_date"><?php echo date_i18n( get_option( 'date_format' ).' @ '.get_option( 'time_format' ), strtotime( $comment->comment_date ) ) ?></span>
		</dt>

		<dd>
			<p><b><?php printf( __( 'Line Item #%s', 'sprout-invoices' ), $position ) ?></b></p>
			<?php echo wpautop( get_comment_text( $comment->comment_ID ) ) ?>
			<p>
				<textarea id="response_ta_<?php echo (int) $comment->comment_ID ?>" name="comment_response" style="width:100%"></textarea>
				
				<button class="button submit_comment_response" data-comment-id="<?php echo (int) $comment->comment_ID ?>" data-li-position="<?php echo esc_attr( $position ); ?>" data-doc-id="<?php the_ID() ?>" data-sec="<?php echo wp_create_nonce( SI_Controller::NONCE ) ?>"><?php _e( 'Submit Response', 'sprout-invoices' ) ?></button>
			</p>
		</dd>
	<?php endforeach ?>
</dl>