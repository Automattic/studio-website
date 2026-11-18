/*
 * Start page: try once to open WordPress Studio's new-site flow, then point at the other ways in.
 * Progressive enhancement only: without this script the page is a plain link and two cards.
 *
 * A browser can't tell whether an app handles a link scheme. When the app opens, the page loses
 * focus or is hidden; when it is still in front after a moment, the app most likely didn't open.
 * Chromium browsers open the link from the page (they ignore it from a frame without a click and
 * add no history entry); Firefox and Safari from a hidden frame, since a page-level link to a
 * scheme nothing handles shows an error there. A click on the button in Safari opens it from the
 * page, since Safari may not open an app from a frame.
 */
( function () {
	'use strict';

	var page = document.querySelector( '.start-hero' );
	var button = page && page.querySelector( '.start-open a[href^="wp-studio:"]' );
	var status = page && page.querySelector( '.start-status' );
	if ( ! button || ! status ) {
		return;
	}

	var WAIT = 1500;
	var link = button.getAttribute( 'href' );
	var ua = navigator.userAgent;
	var safari = /Safari\//.test( ua ) && ! /Chrome|Chromium|Edg|OPR|CriOS|FxiOS/.test( ua );
	var useFrame = /Firefox\//.test( ua ) || safari;
	var phone = /Android|iPhone|iPad|iPod/i.test( ua ) || ( /Macintosh/.test( ua ) && navigator.maxTouchPoints > 1 );
	var timer = null;

	function show( state, parts ) {
		page.classList.remove( 'is-opening', 'is-opened', 'is-fallback' );
		if ( state ) {
			page.classList.add( 'is-' + state );
		}
		status.textContent = '';
		parts.forEach( function ( part ) {
			if ( typeof part === 'string' ) {
				status.appendChild( document.createTextNode( part ) );
				return;
			}
			var a = document.createElement( 'a' );
			a.href = part[ 0 ];
			a.textContent = part[ 1 ];
			status.appendChild( a );
		} );
	}

	var otherWays = [
		[ '#get-the-app', 'Download the app' ],
		' if it isn’t on this computer, or ',
		[ '#command-line', 'start from the command line' ],
		'.',
	];

	// The page can also lose focus to the browser's "Open WordPress Studio?" prompt, so this
	// state keeps the other ways in reach.
	function left() {
		if ( timer ) {
			clearTimeout( timer );
			timer = null;
			show( 'opened', [ 'Continue in WordPress Studio. Didn’t open? ' ].concat( otherWays ) );
		}
	}

	function open( clicked ) {
		clearTimeout( timer );
		show( 'opening', [ 'Opening WordPress Studio…' ] );
		timer = setTimeout( function () {
			timer = null;
			show( 'fallback', [ 'WordPress Studio didn’t open. ' ].concat( otherWays ) );
		}, WAIT );

		if ( useFrame && ! ( clicked && safari ) ) {
			var old = document.getElementById( 'start-frame' );
			if ( old ) {
				old.remove();
			}
			// The src is set before the frame is in the document, so it adds no history entry.
			var frame = document.createElement( 'iframe' );
			frame.id = 'start-frame';
			frame.hidden = true;
			frame.setAttribute( 'aria-hidden', 'true' );
			frame.src = link;
			document.body.appendChild( frame );
		} else {
			window.location.href = link;
		}
	}

	window.addEventListener( 'blur', left );
	window.addEventListener( 'pagehide', left );
	document.addEventListener( 'visibilitychange', function () {
		if ( document.hidden ) {
			left();
		}
	} );

	button.addEventListener( 'click', function ( event ) {
		event.preventDefault();
		open( true );
	} );

	if ( phone ) {
		show( '', [ 'WordPress Studio runs on macOS, Windows and Linux. Open this page on your computer to start.' ] );
		return;
	}

	// Once, after the page has loaded, so the attempt can't cut its loading short. A tab
	// opened in the background waits until it is shown.
	function openWhenShown() {
		if ( ! document.hidden ) {
			open();
			return;
		}
		document.addEventListener( 'visibilitychange', function first() {
			if ( ! document.hidden ) {
				document.removeEventListener( 'visibilitychange', first );
				open();
			}
		} );
	}
	if ( document.readyState === 'complete' ) {
		openWhenShown();
	} else {
		window.addEventListener( 'load', openWhenShown );
	}
} )();
