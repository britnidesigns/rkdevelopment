var $ = jQuery.noConflict();
jQuery(function($) {
	
	$('ol.items_list').on( 'siCalculateLineItemTotals', function( event, li, $subtotal ) {

		// Clear out the totals if there's no more children
		$(li).find('.column_subtotal span').html('');
		$(li).find('.column_tax_hst span').html('');
		$(li).find('.column_tax_pst span').html('');
		$(li).find('.column_total span').html('');

		var $hst_value = $(li).find('.column_tax_hst input').val();
		var $pst_value = $(li).find('.column_tax_pst input').val();
		
		var $total = $subtotal;
		var $new_total = $subtotal;
		var $hst_total = 0;
		var $pst_total = 0;

		if ( $hst_value ) {
			// hst tax
			$hst_total = ( $total ) * ( parseFloat( $hst_value ) / 100 );
			// tally total
			$new_total = Number($new_total) + Number($hst_total);
		};
		if ( $pst_value ) {
			// pst tax
			$pst_total = ( $total ) * ( parseFloat( $pst_value ) / 100 );
			// tally total
			$new_total = Number($new_total) + Number($pst_total);
		};

		// Subtotals
		$(li).find('.column_pricetotal span').html( parseFloat( $new_total ).toFixed(2) );
		$(li).find('.column_pricetotal input').val( parseFloat( $new_total ).toFixed(2) );

		// Totals
		$(li).find('.column_total span').html( parseFloat( $new_total ).toFixed(2) );
		$(li).find('.column_total input').val( parseFloat( $new_total ).toFixed(2) );


	});
	
	calculate_parent_subtotals();
	$('ol.items_list').on( 'siCalculateParentTotals', function( event ) {
		calculate_parent_subtotals();
	});

	function calculate_parent_subtotals() {
		$('ol.items_list .item').each(function(i, li) {
			if ( $(li).children('ol').length > 0 ) {
				var $totals = 0;
				$(li).children('ol').find('.item .column_pricetotal input').each(function(i,n){
					$val = ( $(n).val() === '' ) ? 0 : $(n).val();
					$totals += parseFloat($val);
				});
				var $parent_total_span = $(li).find('.column_pricetotal span').first(),
					$parent_total = parseFloat( $totals ).toFixed(2);

				total_update( $parent_total_span, $parent_total );
			}
		});
	}

	/**
	 * Simple function to highlight the total update.
	 * @param  {object} span  span that will get the total
	 * @param  {float} total total that will be showed
	 * @return {}       
	 */
	function total_update( span, total ) {
		$total = ( isNaN(total) ) ? '0.00' : total;
		span.hide().html($total).fadeIn();
	}

});