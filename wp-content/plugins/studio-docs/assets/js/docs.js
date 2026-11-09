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

	const flashLabel = ( el, text, ms = 1500 ) => {
		el.dataset.label ??= el.textContent ?? '';
		el.textContent = text;
		announce( text );
		clearTimeout( el._timer );
		el._timer = setTimeout( () => {
			el.textContent = el.dataset.label ?? '';
		}, ms );
	};

	const escapeHtml = ( s ) => String( s ).replace( /[&<>"']/g, ( c ) => ( { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[ c ] ) );

	const ICONS = {
		check: '<path d="M20 6 9 17l-5-5"/>',
		copy: '<rect width="14" height="14" x="8" y="8" rx="2" ry="2"/><path d="M4 16c-1.1 0-2-.9-2-2V4c0-1.1.9-2 2-2h10c1.1 0 2 .9 2 2"/>',
		file: '<path d="M15 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7Z"/><path d="M14 2v4a2 2 0 0 0 2 2h4"/>',
	};
	const icon = ( name, size = 16 ) =>
		`<svg xmlns="http://www.w3.org/2000/svg" width="${ size }" height="${ size }" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">${ ICONS[ name ] ?? '' }</svg>`;

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
