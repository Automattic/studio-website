/**
 * Header actions: copy the page's Markdown and open it or a chat prompt from the options menu.
 */
( () => {
	const actions = document.querySelector( '[data-docs-page-actions]' );
	if ( ! actions ) {
		return;
	}
	const { copyText, flashLabel } = window.studioDocs;
	const menu = actions.querySelector( '.studio-docs-page-actions__menu' );
	const toggle = menu?.querySelector( '[data-docs-menu-toggle]' );
	const dropdown = menu?.querySelector( '[role="menu"]' );
	const items = dropdown ? [ ...dropdown.querySelectorAll( '[role="menuitem"]' ) ] : [];
	const open = () => {
		if ( ! toggle || ! dropdown ) {
			return;
		}
		dropdown.hidden = false;
		toggle.setAttribute( 'aria-expanded', 'true' );
		items[ 0 ]?.focus();
	};
	const close = ( focus ) => {
		if ( toggle && dropdown && ! dropdown.hidden ) {
			dropdown.hidden = true;
			toggle.setAttribute( 'aria-expanded', 'false' );
			if ( focus ) {
				toggle.focus();
			}
		}
	};
	toggle?.addEventListener( 'click', () => {
		if ( dropdown?.hidden ) {
			open();
		} else {
			close( false );
		}
	} );
	toggle?.addEventListener( 'keydown', ( e ) => {
		if ( e.key === 'ArrowDown' ) {
			e.preventDefault();
			open();
		}
	} );
	dropdown?.addEventListener( 'keydown', ( e ) => {
		const index = items.indexOf( document.activeElement );
		let next = index;
		if ( e.key === 'ArrowDown' ) {
			next = ( index + 1 ) % items.length;
		} else if ( e.key === 'ArrowUp' ) {
			next = ( index - 1 + items.length ) % items.length;
		} else if ( e.key === 'Home' ) {
			next = 0;
		} else if ( e.key === 'End' ) {
			next = items.length - 1;
		} else if ( e.key === 'Escape' ) {
			e.preventDefault();
			close( true );
			return;
		} else if ( e.key === 'Tab' ) {
			// Restore the toggle before the browser advances focus in the requested direction.
			close( true );
			return;
		} else if ( e.key === ' ' && index !== -1 ) {
			e.preventDefault();
			items[ index ].click();
			return;
		} else {
			return;
		}
		e.preventDefault();
		items[ next ]?.focus();
	} );
	dropdown?.addEventListener( 'click', ( e ) => {
		if ( e.target.closest( '[role="menuitem"]' ) ) {
			close( false );
		}
	} );
	document.addEventListener( 'pointerdown', ( e ) => {
		if ( menu && ! menu.contains( e.target ) ) {
			close( false );
		}
	} );
	menu?.addEventListener( 'focusout', ( e ) => {
		if ( e.relatedTarget instanceof Node && ! menu.contains( e.relatedTarget ) ) {
			close( false );
		}
	} );

	// Chat links.
	const md = actions.dataset.markdownUrl || '';
	const mdUrl = new URL( md, location.origin ).href;
	const prompt = encodeURIComponent( ( actions.dataset.chatPrompt || '' ).replaceAll( '{url}', () => mdUrl ) );
	const targets = {
		chatgpt: `https://chatgpt.com/?hints=search&prompt=${ prompt }`,
		claude: `https://claude.ai/new?q=${ prompt }`,
		cursor: `https://cursor.com/link/prompt?text=${ prompt }`,
		scira: `https://scira.ai/?q=${ prompt }`,
		t3: `https://t3.chat/new?q=${ prompt }`,
		v0: `https://v0.app?q=${ prompt }`,
	};
	for ( const a of actions.querySelectorAll( '[data-open-in]' ) ) {
		const href = targets[ a.dataset.openIn ];
		if ( href ) {
			a.setAttribute( 'href', href );
		}
	}

	const label = actions.querySelector( '[data-docs-copy-label]' );
	actions.querySelector( '[data-docs-copy-page]' )?.addEventListener( 'click', async () => {
		let ok = false;
		try {
			const response = await fetch( md );
			if ( ! response.ok ) {
				throw new Error( `Fetching ${ md } failed (${ response.status })` );
			}
			ok = await copyText( await response.text() );
		} catch ( error ) {
			console.error( '[docs] Copy as Markdown failed', error );
		}
		if ( label ) {
			flashLabel( label, ok ? actions.dataset.copiedLabel || 'Copied' : actions.dataset.failedLabel || 'Copy failed', 2000 );
		}
	} );
} )();
