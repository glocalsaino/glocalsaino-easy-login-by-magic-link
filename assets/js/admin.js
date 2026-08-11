/* global jQuery */
( function ( $ ) {
	'use strict';

	$( function () {

		$( document ).on( 'click', '.wpll-copy-link', function () {
			var $btn     = $( this );
			var url      = $btn.data( 'url' );
			var original = $btn.text();

			navigator.clipboard.writeText( url ).then( function () {
				$btn.text( '✓' );
				setTimeout( function () { $btn.text( original ); }, 1500 );
			} );
		} );

	} );

}( jQuery ) );
