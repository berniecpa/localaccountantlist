/* Gallery picker for the listing edit screen. */
( function ( $ ) {
	'use strict';

	var frame;

	$( document ).on( 'click', '#llh-gallery-select', function ( e ) {
		e.preventDefault();

		if ( ! frame ) {
			frame = wp.media( {
				title: 'Select listing photos',
				multiple: true,
				library: { type: 'image' },
				button: { text: 'Use these photos' }
			} );

			frame.on( 'select', function () {
				var ids = [];
				var preview = $( '#llh-gallery-preview' ).empty();

				frame.state().get( 'selection' ).each( function ( attachment ) {
					var data = attachment.toJSON();
					var thumb = ( data.sizes && data.sizes.thumbnail ) ? data.sizes.thumbnail.url : data.url;
					ids.push( data.id );
					preview.append(
						$( '<img/>', { src: thumb, css: { margin: '4px', maxWidth: '100px' } } )
					);
				} );

				$( '#llh_gallery' ).val( ids.join( ',' ) );
			} );
		}

		frame.open();
	} );

	$( document ).on( 'click', '#llh-gallery-clear', function ( e ) {
		e.preventDefault();
		$( '#llh_gallery' ).val( '' );
		$( '#llh-gallery-preview' ).empty();
	} );
} )( jQuery );
