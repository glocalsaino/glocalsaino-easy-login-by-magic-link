/* global glocalsainoMagicLinkAdmin */
( function () {
	'use strict';

	var i18n = window.glocalsainoMagicLinkAdmin || { copied: 'Copied', confirmRevoke: 'Revoke this link?' };

	function copyText( text ) {
		if ( navigator.clipboard && window.isSecureContext ) {
			return navigator.clipboard.writeText( text );
		}
		// Fallback for http:// admin screens, where the Clipboard API is unavailable.
		var field = document.createElement( 'textarea' );
		field.value = text;
		field.setAttribute( 'readonly', '' );
		field.style.position = 'absolute';
		field.style.left = '-9999px';
		document.body.appendChild( field );
		field.select();
		document.execCommand( 'copy' );
		document.body.removeChild( field );
		return Promise.resolve();
	}

	document.addEventListener( 'click', function ( event ) {
		var target = event.target;

		if ( target.classList.contains( 'gselml-url-field' ) ) {
			target.select();
			return;
		}

		if ( target.classList.contains( 'gselml-copy-link' ) ) {
			var original = target.textContent;
			copyText( target.getAttribute( 'data-url' ) ).then( function () {
				target.textContent = '✓ ' + i18n.copied;
				setTimeout( function () {
					target.textContent = original;
				}, 1500 );
			} );
		}
	} );

	document.addEventListener( 'submit', function ( event ) {
		if ( event.target.classList.contains( 'gselml-revoke-form' ) && ! window.confirm( i18n.confirmRevoke ) ) {
			event.preventDefault();
		}
	} );
}() );
