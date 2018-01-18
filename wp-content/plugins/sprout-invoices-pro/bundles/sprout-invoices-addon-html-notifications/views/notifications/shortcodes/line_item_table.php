<table class="invoice-items" cellpadding="0" cellspacing="0" style="font-family: 'Helvetica Neue', 'Helvetica', Helvetica, Arial, sans-serif; box-sizing: border-box; font-size: 14px; width: 100%; margin: 0; padding: 0;">
<?php foreach ( $line_items as $position => $data ) :
		// This is were the loop is for all the items.
		// Variables for each line item info
		$desc = ( isset( $data['desc'] ) ) ? apply_filters( 'the_content', $data['desc'] ) : '' ;
		$li_total = ( isset( $data['total'] ) ) ? $data['total'] : 0 ; ?>

	<tr style="font-family: 'Helvetica Neue', 'Helvetica', Helvetica, Arial, sans-serif; box-sizing: border-box; font-size: 14px; margin: 0; padding: 0;  width: 100%;"><td width="80%" style="font-family: 'Helvetica Neue', 'Helvetica', Helvetica, Arial, sans-serif; box-sizing: border-box; font-size: 14px; vertical-align: top; border-top-width: 1px; border-top-color: #eee; border-top-style: solid; margin: 0; padding: 5px 0;" valign="top"><?php echo $desc ?></td><td style="font-family: 'Helvetica Neue', 'Helvetica', Helvetica, Arial, sans-serif; box-sizing: border-box; font-size: 14px; vertical-align: top; text-align: right; border-top-width: 1px; border-top-color: #eee; border-top-style: solid; margin: 0; padding: 5px 0;" align="right" valign="top"><p><?php sa_formatted_money( $li_total, $doc_id ) ?></p></td>
	</tr>
<?php endforeach ?>
<?php if ( is_array( $totals ) && ! empty( $totals ) ) :  ?>
	<?php foreach ( $totals as $slug => $items_total ) :  ?>
		<?php if ( isset( $items_total['hide'] ) && $items_total['hide'] ) : ?>
			<?php continue; ?>
		<?php endif ?>
		<tr class="<?php echo esc_attr( $slug ) ?>" style="font-family: 'Helvetica Neue', 'Helvetica', Helvetica, Arial, sans-serif; box-sizing: border-box; font-size: 14px; margin: 0; padding: 0;  width: 100%;"><td width="80%" style="font-family: 'Helvetica Neue', 'Helvetica', Helvetica, Arial, sans-serif; box-sizing: border-box; font-size: 14px; vertical-align: top; text-align: right; border-bottom-color: #333; border-bottom-width: 2px; border-bottom-style: solid; font-weight: 700; margin: 0; padding: 5px 0;" align="left" valign="top"><?php echo $items_total['label'] ?></td><td width="20%" style="font-family: 'Helvetica Neue', 'Helvetica', Helvetica, Arial, sans-serif; box-sizing: border-box; font-size: 14px; vertical-align: top; text-align: right; border-bottom-color: #333; border-bottom-width: 2px; border-bottom-style: solid; font-weight: 700; margin: 0; padding: 5px 0;" align="right" valign="top"><?php echo $items_total['formatted'] ?></td>
		</tr>
	<?php endforeach ?>
<?php endif ?>
</table>
