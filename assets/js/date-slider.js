/* global woocommerce_price_slider_params */
jQuery( function( $ ) {

	// woocommerce_price_slider_params is required to continue, ensure the object exists
	if ( typeof woocommerce_date_slider_params === 'undefined' ) {
		return false;
	}

	// Get markup ready for slider
	$( 'input#min_date, input#max_date' ).hide();
	$( '.date_slider, .date_label' ).show();

	// Price slider uses jquery ui
	var min_date = $( '.date_slider_amount #min_date' ).data( 'date' ),
		max_date = $( '.date_slider_amount #max_date' ).data( 'date' ),
		current_min_date = parseInt( min_date, 10 ),
		current_max_date = parseInt( max_date, 10 );

	if ( woocommerce_date_slider_params.min_date ) {
		current_min_date = parseInt( woocommerce_date_slider_params.min_date, 10 );
	}
	if ( woocommerce_date_slider_params.max_date ) {
		current_max_date = parseInt( woocommerce_date_slider_params.max_date, 10 );
	}

	$( document.body ).bind( 'date_slider_create date_slider_slide', function( event, min, max ) {

			$( '.date_slider_amount span.from' ).html(min );
			$( '.date_slider_amount span.to' ).html(max );


		$( document.body ).trigger( 'date_slider_updated', [ min, max ] );
	});

	$( '.date_slider' ).slider({
		range: true,
		animate: true,
		min: min_date,
		max: max_date,
		values: [ current_min_date, current_max_date ],
		create: function() {

			$( '.date_slider_amount #min_date' ).val( current_min_date );
			$( '.date_slider_amount #max_date' ).val( current_max_date );

			$( document.body ).trigger( 'date_slider_create', [ current_min_date, current_max_date ] );
		},
		slide: function( event, ui ) {

			$( 'input#min_date' ).val( ui.values[0] );
			$( 'input#max_date' ).val( ui.values[1] );

			$( document.body ).trigger( 'date_slider_slide', [ ui.values[0], ui.values[1] ] );
		},
		change: function( event, ui ) {

			$( document.body ).trigger( 'date_slider_change', [ ui.values[0], ui.values[1] ] );
		}
	});

});
