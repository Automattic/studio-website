/*
 * Open in WordPress Studio generator: a Blueprint URL, Base64 or JSON in, an
 * https://wp.com/open?deep_link=add-site?… link out, which WordPress Studio's add-site deep link reads
 * as blueprint_url (a URL) or blueprint (Base64 JSON).
 */
( function () {
	'use strict';

	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', init );
	} else {
		init();
	}

	function init() {
		var root = document.querySelector( '.blueprint-button' );
		if ( ! root ) {
			return;
		}
		var input = root.querySelector( '.blueprint-button__input' );
		var error = root.querySelector( '.blueprint-button__error' );
		var outputs = root.querySelectorAll( 'code[data-format]' );
		var tryLink = root.querySelector( '.blueprint-button__try' );
		var LABEL = 'Open in WordPress Studio';

		function toBase64( text ) {
			var bytes = new TextEncoder().encode( text );
			var binary = '';
			bytes.forEach( function ( b ) {
				binary += String.fromCharCode( b );
			} );
			return btoa( binary );
		}

		function isBlueprint( text ) {
			try {
				var value = JSON.parse( text );
				return value !== null && typeof value === 'object' && ! Array.isArray( value );
			} catch ( e ) {
				return false;
			}
		}

		/* Returns [ parameter, value ] or an error message. */
		function parse( text ) {
			if ( /^https?:\/\//i.test( text ) ) {
				try {
					new URL( text );
					return [ 'blueprint_url', text ];
				} catch ( e ) {
					return 'That URL isn’t valid.';
				}
			}
			if ( text.charAt( 0 ) === '{' ) {
				return isBlueprint( text ) ? [ 'blueprint', toBase64( text ) ] : 'That JSON isn’t valid.';
			}
			var base64 = text.replace( /\s+/g, '' );
			if ( /^[A-Za-z0-9+/]+=*$/.test( base64 ) ) {
				try {
					var json = new TextDecoder().decode( Uint8Array.from( atob( base64 ), function ( c ) {
						return c.charCodeAt( 0 );
					} ) );
					if ( isBlueprint( json ) ) {
						return [ 'blueprint', base64 ];
					}
				} catch ( e ) {}
			}
			return 'Paste a Blueprint URL, Base64 encoded JSON or raw JSON.';
		}

		function update() {
			var text = input.value.trim();
			var result = text ? parse( text ) : null;
			var link = Array.isArray( result ) ? 'https://wp.com/open?deep_link=' + encodeURIComponent( 'add-site?' + result[ 0 ] + '=' + encodeURIComponent( result[ 1 ] ) ) : '';
			var code = {
				markdown: link && '[' + LABEL + '](' + link + ')',
				html: link && '<a href="' + link + '">' + LABEL + '</a>',
				url: link,
			};
			outputs.forEach( function ( output ) {
				output.textContent = code[ output.getAttribute( 'data-format' ) ];
			} );
			error.textContent = typeof result === 'string' ? result : '';
			error.hidden = typeof result !== 'string';
			input.setAttribute( 'aria-invalid', typeof result === 'string' ? 'true' : 'false' );
			root.classList.toggle( 'has-link', !! link );
			tryLink.hidden = ! link;
			tryLink.firstElementChild.href = link || '#';
		}

		var timer;
		input.addEventListener( 'input', function () {
			clearTimeout( timer );
			timer = setTimeout( update, 250 );
		} );
		update();
	}
} )();
