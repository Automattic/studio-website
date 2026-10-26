/* Studio theme: tabs, copy buttons, scroll reveal. Progressive enhancement only. */
( function () {
	'use strict';

	var reduce = window.matchMedia( '(prefers-reduced-motion: reduce)' ).matches;

	/* Download: point the main button at the visitor's platform and mark its row. */
	( function () {
		var primary = document.querySelector( '.dl-primary .wp-block-button__link' );
		if ( ! primary ) {
			return;
		}
		var ua = navigator.userAgent;
		var platform = ( navigator.userAgentData && navigator.userAgentData.platform ) || navigator.platform || '';
		if ( /Android|iPhone|iPad|iPod/i.test( ua ) ) {
			return;
		}
		var os = /Mac/i.test( platform ) ? [ 'mac', 'macOS' ] : /Win/i.test( platform ) ? [ 'windows', 'Windows' ] : /Linux|X11/i.test( platform + ua ) ? [ 'linux', 'Linux' ] : null;
		if ( ! os ) {
			return;
		}
		var row = document.querySelector( '.platform-row.os-' + os[ 0 ] );
		if ( row ) {
			row.classList.add( 'is-detected' );
			var first = row.querySelector( '.wp-block-button__link' );
			if ( first ) {
				primary.href = first.href;
			}
		}
		primary.textContent = 'Download for ' + os[ 1 ];
	} )();

	/* Tabs: a .is-style-tabs buttons row whose links point at #door-* panels. */
	document.querySelectorAll( '.wp-block-buttons.is-style-tabs' ).forEach( function ( list ) {
		var links = Array.prototype.slice.call( list.querySelectorAll( 'a[href^="#"]' ) );
		var panels = links
			.map( function ( a ) {
				return document.getElementById( a.getAttribute( 'href' ).slice( 1 ) );
			} )
			.filter( Boolean );
		if ( panels.length < 2 ) {
			return;
		}
		list.setAttribute( 'role', 'tablist' );

		function select( index, focus ) {
			links.forEach( function ( a, i ) {
				var on = i === index;
				a.setAttribute( 'aria-selected', on ? 'true' : 'false' );
				a.setAttribute( 'tabindex', on ? '0' : '-1' );
				a.closest( '.wp-block-button' ).classList.toggle( 'is-active', on );
				if ( panels[ i ] ) {
					panels[ i ].hidden = ! on;
				}
			} );
			if ( focus ) {
				links[ index ].focus();
			}
		}

		links.forEach( function ( a, i ) {
			a.setAttribute( 'role', 'tab' );
			if ( panels[ i ] ) {
				a.setAttribute( 'aria-controls', panels[ i ].id );
				a.id = a.id || 'tab-' + panels[ i ].id;
				panels[ i ].setAttribute( 'role', 'tabpanel' );
				panels[ i ].setAttribute( 'aria-labelledby', a.id );
			}
			a.addEventListener( 'click', function ( e ) {
				e.preventDefault();
				select( i, false );
			} );
			a.addEventListener( 'keydown', function ( e ) {
				if ( e.key === 'ArrowRight' || e.key === 'ArrowLeft' ) {
					e.preventDefault();
					var next = ( i + ( e.key === 'ArrowRight' ? 1 : -1 ) + links.length ) % links.length;
					select( next, true );
				}
			} );
		} );

		var start = links.findIndex( function ( a ) {
			return a.getAttribute( 'href' ) === window.location.hash;
		} );
		select( start > -1 ? start : 0, false );
		list.parentElement.classList.add( 'is-tabbed' );
	} );

	/* Clipboard write with a fallback for non-secure contexts. */
	function copyText( text, done ) {
		if ( navigator.clipboard && window.isSecureContext ) {
			navigator.clipboard.writeText( text ).then( done );
			return;
		}
		var ta = document.createElement( 'textarea' );
		ta.value = text;
		ta.setAttribute( 'readonly', '' );
		ta.style.position = 'fixed';
		ta.style.opacity = '0';
		document.body.appendChild( ta );
		ta.select();
		try {
			document.execCommand( 'copy' );
			done();
		} catch ( err ) {}
		document.body.removeChild( ta );
	}

	/* Copy buttons on every code block. */
	document.querySelectorAll( 'pre.wp-block-code' ).forEach( function ( pre ) {
		var code = pre.querySelector( 'code' ) || pre;
		var btn = document.createElement( 'button' );
		btn.type = 'button';
		btn.className = 'studio-copy';
		btn.textContent = 'Copy';
		btn.setAttribute( 'aria-label', 'Copy to clipboard' );
		btn.addEventListener( 'click', function () {
			var text = code.innerText.replace( /\u00a0/g, ' ' ).trim();
			var done = function () {
				btn.textContent = 'Copied';
				btn.classList.add( 'is-copied' );
				setTimeout( function () {
					btn.textContent = 'Copy';
					btn.classList.remove( 'is-copied' );
				}, 1600 );
			};
			copyText( text, done );
		} );
		pre.classList.add( 'has-copy' );
		pre.appendChild( btn );
	} );

	/*
	 * Showcase keyboard access: each prompt (it can scroll) is a tab stop that reveals its
	 * overlay and pauses the row. With reduced motion the row itself scrolls, so it is
	 * a focusable, labelled region.
	 */
	document.querySelectorAll( '.showcase .sc-card' ).forEach( function ( card ) {
		var text = card.querySelector( '.sc-text' );
		var name = card.querySelector( '.sc-name' );
		if ( text ) {
			text.setAttribute( 'tabindex', '0' );
			text.setAttribute( 'role', 'group' );
			text.setAttribute( 'aria-label', 'Prompt' + ( name ? ' for ' + name.textContent.trim() : '' ) );
			/* A real button for keyboard and screen readers; the whole card copies on click too. */
			var btn = document.createElement( 'button' );
			btn.type = 'button';
			btn.className = 'sc-copy';
			btn.textContent = 'Copy prompt';
			if ( name ) {
				btn.setAttribute( 'aria-label', 'Copy the prompt for ' + name.textContent.trim() );
			}
			text.parentNode.appendChild( btn );
		}
	} );

	/* Delegated, so the marquee clones (made below) copy too. */
	document.addEventListener( 'click', function ( event ) {
		var card = event.target.closest( '.showcase .sc-card' );
		if ( ! card ) {
			return;
		}
		var onButton = event.target.closest( '.sc-copy' );
		if ( ! onButton && String( window.getSelection() ).trim() ) {
			return; /* the visitor is selecting text, not copying */
		}
		var text = card.querySelector( '.sc-text' );
		var btn = card.querySelector( '.sc-copy' );
		if ( ! text || ! btn ) {
			return;
		}
		copyText( text.textContent.trim(), function () {
			btn.textContent = 'Copied';
			card.classList.add( 'is-copied' );
			clearTimeout( card._copyTimer );
			card._copyTimer = setTimeout( function () {
				btn.textContent = 'Copy prompt';
				card.classList.remove( 'is-copied' );
			}, 1800 );
		} );
	} );
	if ( reduce ) {
		document.querySelectorAll( '.marquee' ).forEach( function ( marquee ) {
			marquee.setAttribute( 'tabindex', '0' );
			marquee.setAttribute( 'role', 'region' );
			marquee.setAttribute( 'aria-label', 'Sites built with Studio, scrolls sideways' );
		} );
	}

	/* Marquee: duplicate the track's cards once so the loop is seamless. */
	if ( ! reduce ) {
		document.querySelectorAll( '.marquee-track' ).forEach( function ( track ) {
			var items = Array.prototype.slice.call( track.children );
			items.forEach( function ( item ) {
				var clone = item.cloneNode( true );
				/*
				 * Hidden from assistive tech and the tab order, but not inert: inert content
				 * gets no hover or clicks, and half the visible sites are clones.
				 */
				clone.setAttribute( 'aria-hidden', 'true' );
				clone.querySelectorAll( 'a, button, [tabindex]' ).forEach( function ( el ) {
					el.setAttribute( 'tabindex', '-1' );
				} );
				track.appendChild( clone );
			} );
			track.classList.add( 'is-running' );

			/*
			 * Driven from JS rather than a CSS animation: pausing a compositor animation
			 * re-syncs it with the main thread and the track visibly snaps. Here the offset
			 * only ever advances, so stopping can never jump.
			 */
			var marquee = track.closest( '.marquee' );
			var isShowcase = !! track.closest( '.showcase' );
			var secondsPerItem = isShowcase ? 4.5 : 11;
			var loop = 0, offset = 0, speed = 1, target = 1, last = 0, visible = true, frame = null;

			function measure() {
				loop = items.length ? track.children[ items.length ].offsetLeft - items[ 0 ].offsetLeft : 0;
			}
			function tick( now ) {
				var dt = last ? Math.min( now - last, 50 ) / 1000 : 0;
				last = now;
				speed += ( target - speed ) * Math.min( 1, dt * 12 );
				if ( Math.abs( target - speed ) < 0.005 ) {
					speed = target;
				}
				if ( loop > 0 ) {
					offset = ( offset + speed * dt * ( loop / ( items.length * secondsPerItem ) ) ) % loop;
					track.style.transform = 'translate3d(' + -offset.toFixed( 2 ) + 'px,0,0)';
				}
				frame = visible ? requestAnimationFrame( tick ) : null;
			}
			function start() {
				if ( ! frame ) {
					last = 0;
					frame = requestAnimationFrame( tick );
				}
			}

			measure();
			window.addEventListener( 'resize', measure );
			window.addEventListener( 'load', measure );

			/* Showcase pauses only over a site; other marquees pause anywhere inside. Focus pauses both. */
			marquee.addEventListener( 'mouseover', function ( event ) {
				target = ! isShowcase || event.target.closest( '.sc-card' ) ? 0 : 1;
			} );
			marquee.addEventListener( 'mouseleave', function () {
				target = 1;
			} );
			marquee.addEventListener( 'focusin', function () {
				target = 0;
			} );
			marquee.addEventListener( 'focusout', function () {
				target = 1;
			} );

			/* Only run while on screen. */
			if ( 'IntersectionObserver' in window ) {
				new IntersectionObserver( function ( entries ) {
					visible = entries[ 0 ].isIntersecting;
					if ( visible ) {
						start();
					}
				} ).observe( marquee );
			} else {
				start();
			}
		} );
	}

	/* Scroll reveal: content is visible by default; JS adds the start state. */
	/* Hero video: swap native controls for a Sound on pill; clicking the video pauses and resumes. */
	document.querySelectorAll( '.video-window' ).forEach( function ( win ) {
		var video = win.querySelector( 'video' );
		if ( ! video ) {
			return;
		}
		video.removeAttribute( 'controls' );
		video.controls = false;
		video.muted = true;
		win.classList.add( 'is-enhanced' );

		var controls = document.createElement( 'div' );
		controls.className = 'video-controls';

		var rewind = document.createElement( 'button' );
		rewind.type = 'button';
		rewind.className = 'video-rewind';
		rewind.setAttribute( 'aria-label', 'Rewind to the start' );
		rewind.title = 'Rewind';
		rewind.innerHTML = '<svg viewBox="0 0 24 24" width="14" height="14" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 12a9 9 0 1 0 3-6.7L3 8"/><path d="M3 3v5h5"/></svg>';
		rewind.addEventListener( 'click', function () {
			video.currentTime = 0;
			video.play();
		} );

		var pill = document.createElement( 'button' );
		pill.type = 'button';
		pill.className = 'sound-pill';
		pill.setAttribute( 'aria-pressed', 'false' );
		pill.textContent = 'Sound on';

		controls.appendChild( rewind );
		controls.appendChild( pill );
		win.appendChild( controls );

		/* Native loop can stall where the server ignores range requests; restart from 0 ourselves instead. */
		video.loop = false;
		video.addEventListener( 'ended', function () {
			video.currentTime = 0;
			video.play();
		} );

		pill.addEventListener( 'click', function () {
			video.muted = ! video.muted;
			pill.textContent = video.muted ? 'Sound on' : 'Sound off';
			pill.setAttribute( 'aria-pressed', video.muted ? 'false' : 'true' );
			if ( ! video.muted && video.paused ) {
				video.play();
			}
		} );

		video.setAttribute( 'aria-label', 'Demo video. Click to pause or play.' );
		video.addEventListener( 'click', function () {
			if ( video.paused ) {
				video.play();
			} else {
				video.pause();
			}
		} );

		if ( reduce ) {
			video.pause();
		}
	} );

	if ( reduce || ! ( 'IntersectionObserver' in window ) ) {
		return;
	}
	var targets = document.querySelectorAll(
		'.is-style-band > *:not(.marquee), .is-style-mosaic > *'
	);
	var io = new IntersectionObserver(
		function ( entries ) {
			entries.forEach( function ( entry ) {
				if ( entry.isIntersecting ) {
					entry.target.classList.add( 'is-revealed' );
					io.unobserve( entry.target );
				}
			} );
		},
		{ rootMargin: '0px 0px -8% 0px' }
	);
	targets.forEach( function ( el ) {
		if ( el.getBoundingClientRect().top < window.innerHeight ) {
			return;
		}
		el.classList.add( 'will-reveal' );
		io.observe( el );
	} );
} )();
