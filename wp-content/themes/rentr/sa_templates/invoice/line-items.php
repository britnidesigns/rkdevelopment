<?php // Original template file: plugins/sprout-invoices-pro/views/templates/theme/default/invoice/line-items.php ?>

<section class="line-items">
	<?php do_action( 'si_document_line_items' ) ?>
	<?php foreach ( $line_items as $position => $item_data ) : ?>
		<?php if ( is_int( $position ) ) : // is not a child ?>
			<?php
				$children = si_line_item_get_children( $position, $line_items );
				$has_children = ( ! empty( $children ) ) ? true : false ;
				$prev_type = ( isset( $item_type ) && '' !== $item_type ) ? $item_type : '' ;
				$item_type = ( isset( $item_data['type'] ) && '' !== $item_data['type'] ) ? $item_data['type'] : SI_Line_Items::get_default_type(); ?>

			<?php do_action( 'si_get_front_end_line_item_pre_row', $item_data, $position, $item_type, $has_children ) ?>

			<div class="item type-<?php echo esc_attr( $item_type ) ?> <?php if ( $has_children ) { echo esc_attr( 'line_item_has_children' ); } ?>" data-id="<?php echo (float) $position ?>">
				<?php si_front_end_line_item_columns( $item_data, $position, $prev_type, $has_children ) ?>
			</div>

			<?php $prev_type = $item_type; ?>
		<?php endif ?>
	<?php endforeach ?>
</section>

<ul class="totals">
	<?php foreach ( $totals as $slug => $items_total ) : ?>
		<?php if ( isset( $items_total['hide'] ) && $items_total['hide'] ) : ?>
			<?php continue; ?>
		<?php endif ?>

		<li id="line_<?php echo esc_attr( $slug ) ?>">
            <span<?php if ( isset( $items_total['helptip'] ) ) echo ' title="'.esc_attr( $items_total['helptip'] ).'"' ?>>
                <?php echo $items_total['label'] ?>
            </span>
			<span class="total"><?php echo $items_total['formatted'] ?></span>
		</li>
	<?php endforeach ?>
</ul>
