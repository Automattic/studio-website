/**
 * Shared by the block scripts (window.studioDocs): clipboard with a live-region announcement, a
 * label flash, HTML escaping, inline icons. Also starts the image zoom on the article's images.
 */
( () => {
	'use strict';

	let status = null;
	const announce = ( text ) => {
		if ( ! status?.isConnected ) {
			status = document.createElement( 'div' );
			status.setAttribute( 'role', 'status' );
			status.setAttribute( 'aria-live', 'polite' );
			status.className = 'screen-reader-text';
			document.body.append( status );
		}
		status.textContent = '';
		status.textContent = text;
	};

	const copyText = async ( text ) => {
		try {
			await navigator.clipboard.writeText( text );
			return true;
		} catch {
			const area = document.createElement( 'textarea' );
			area.value = text;
			area.setAttribute( 'readonly', '' );
			area.style.cssText = 'position:fixed;top:0;left:-9999px;opacity:0';
			document.body.append( area );
			area.select();
			let ok = false;
			try {
				ok = document.execCommand( 'copy' );
			} catch {}
			area.remove();
			return ok;
		}
	};

	const flashLabel = ( el, text, ms = 1500, localStatus = null ) => {
		el.dataset.label ??= el.textContent ?? '';
		el.textContent = text;
		if ( localStatus ) {
			localStatus.textContent = '';
			localStatus.textContent = text;
		} else {
			announce( text );
		}
		clearTimeout( el._timer );
		el._timer = setTimeout( () => {
			el.textContent = el.dataset.label ?? '';
		}, ms );
	};

	const escapeHtml = ( s ) => String( s ).replace( /[&<>"']/g, ( c ) => ( { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[ c ] ) );

	// PHP's Render\icon() supplies the same Solar markup for dynamically inserted icons.
	const icon = ( name, size = 16 ) => ( window.studioDocsIcons?.[ name ] ?? '' )
		.replace( 'width="16" height="16"', `width="${ size }" height="${ size }"` );

	const readStorage = ( key ) => {
		try {
			return localStorage.getItem( key );
		} catch {
			return null;
		}
	};
	const saveStorage = ( key, value ) => {
		try {
			localStorage.setItem( key, value );
		} catch {}
	};

	window.studioDocs = { announce, copyText, flashLabel, escapeHtml, icon, readStorage, saveStorage };

	// Image zoom (medium-zoom) on the article's images that are not links.
	if ( typeof mediumZoom === 'function' ) {
		const images = [ ...document.querySelectorAll( '.studio-docs-article img:not([data-no-zoom])' ) ].filter( ( img ) => ! img.closest( 'a' ) );
		if ( images.length ) {
			mediumZoom( images, { background: 'color-mix(in oklab, var(--docs-background) 80%, transparent)', margin: 24 } );
		}
	}
} )();
