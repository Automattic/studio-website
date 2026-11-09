/**
 * Page actions: Scroll to top, Copy as Markdown (fetches the page's Markdown), the "Open in chat" menu
 * (links built from the Markdown URL; a dropdown in the rail, a plain list in the collapsible table of
 * contents, where the block moves when the rail is hidden).
 */
( () => {
	const actions = document.querySelector( '[data-docs-page-actions]' );
	if ( ! actions ) {
		return;
	}
	const { copyText, flashLabel } = window.studioDocs;
	const wide = matchMedia( '(min-width: 80rem)' );
	const home = actions.parentElement;
	const menu = actions.querySelector( '.studio-docs-page-actions__menu' );

	const place = () => {
		const target = wide.matches ? home : document.querySelector( '[data-docs-page-actions-mount]' );
		if ( target && actions.parentElement !== target ) {
			if ( menu ) {
				menu.open = false;
			}
			target.append( actions );
		}
	};
	place();
	wide.addEventListener( 'change', place );

	// The dropdown in the rail: flip up when there is no room below, close on outside click, Escape
	// and focus out.
	const flip = () => {
		if ( ! menu || ! wide.matches ) {
			return;
		}
		const dropdown = menu.querySelector( '.studio-docs-page-actions__dropdown' );
		const summary = menu.querySelector( 'summary' );
		const box = summary.getBoundingClientRect();
		const rail = menu.closest( '.studio-docs-rail' )?.getBoundingClientRect() ?? { bottom: window.innerHeight, top: 0 };
		const up = box.bottom + dropdown.offsetHeight + 8 > rail.bottom && box.top - dropdown.offsetHeight - 8 > rail.top;
		menu.toggleAttribute( 'data-docs-flip', up );
	};
	const close = ( focus ) => {
		if ( menu?.open ) {
			menu.open = false;
			if ( focus ) {
				menu.querySelector( 'summary' )?.focus();
			}
		}
	};
	menu?.addEventListener( 'toggle', () => menu.open && flip() );
	window.addEventListener( 'resize', () => menu?.open && flip() );
	document.addEventListener( 'pointerdown', ( e ) => {
		if ( wide.matches && menu?.open && ! menu.contains( e.target ) ) {
			close( false );
		}
	} );
	document.addEventListener( 'keydown', ( e ) => {
		if ( e.key === 'Escape' && wide.matches && menu?.open && ! e.target.closest( 'dialog' ) ) {
			close( menu.contains( e.target ) );
		}
	} );
	menu?.addEventListener( 'focusout', ( e ) => {
		if ( wide.matches && e.relatedTarget instanceof Node && ! menu.contains( e.relatedTarget ) ) {
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

	actions.querySelector( '[data-docs-scroll-top]' )?.addEventListener( 'click', () => window.scrollTo( { behavior: 'smooth', top: 0 } ) );

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
			flashLabel( label, ok ? actions.dataset.copiedLabel || 'Copied!' : actions.dataset.failedLabel || 'Copy failed' );
		}
	} );
} )();
