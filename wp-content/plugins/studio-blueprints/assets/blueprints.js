/**
 * The Blueprints pages: the gallery's filter and search, the live preview, and the copy button.
 */
( () => {
	// ---- Gallery ----
	const grid = document.querySelector( '[data-sbp-grid]' );
	if ( grid ) {
		const cards = [ ...grid.children ];
		const chips = [ ...document.querySelectorAll( '[data-sbp-filter]' ) ];
		const search = document.querySelector( '[data-sbp-search]' );
		const empty = document.querySelector( '[data-sbp-empty]' );
		const params = new URLSearchParams( location.search );
		let use = chips.some( ( chip ) => chip.dataset.sbpFilter === params.get( 'use' ) ) ? params.get( 'use' ) : '';

		const apply = () => {
			const terms = search.value.trim().toLowerCase().split( /\s+/ ).filter( Boolean );
			let shown = 0;
			for ( const card of cards ) {
				const match =
					( ! use || card.dataset.use.split( ' ' ).includes( use ) ) &&
					terms.every( ( term ) => card.dataset.search.includes( term ) );
				card.hidden = ! match;
				shown += match ? 1 : 0;
			}
			empty.hidden = shown > 0;
			for ( const chip of chips ) {
				chip.setAttribute( 'aria-pressed', String( chip.dataset.sbpFilter === use ) );
			}
			const url = new URL( location.href );
			if ( use ) {
				url.searchParams.set( 'use', use );
			} else {
				url.searchParams.delete( 'use' );
			}
			history.replaceState( null, '', url );
		};

		for ( const chip of chips ) {
			chip.addEventListener( 'click', () => {
				use = chip.dataset.sbpFilter;
				apply();
			} );
		}
		search.addEventListener( 'input', apply );
		document.querySelector( '[data-sbp-reset]' ).addEventListener( 'click', () => {
			use = '';
			search.value = '';
			apply();
		} );
		apply();
	}

	// ---- Live preview: Playground loads only when asked for ----
	const preview = document.querySelector( '[data-sbp-preview]' );
	if ( preview ) {
		const image = preview.querySelector( '[data-sbp-image]' );
		const live = preview.querySelector( '[data-sbp-live]' );
		const start = preview.querySelector( '.sbp-preview__start' );
		const setMode = ( mode ) => {
			const isLive = mode === 'live' && live;
			if ( isLive && ! live.querySelector( 'iframe' ) ) {
				const frame = document.createElement( 'iframe' );
				frame.src = live.dataset.src;
				frame.title = 'Live preview';
				frame.allow = 'clipboard-write';
				live.append( frame );
			}
			if ( live ) {
				live.hidden = ! isLive;
			}
			image.hidden = Boolean( isLive );
			if ( start ) {
				start.hidden = Boolean( isLive );
			}
			for ( const button of preview.querySelectorAll( '.sbp-segments [data-sbp-mode]' ) ) {
				button.setAttribute( 'aria-pressed', String( button.dataset.sbpMode === ( isLive ? 'live' : 'image' ) ) );
			}
		};
		preview.addEventListener( 'click', ( event ) => {
			const button = event.target.closest( '[data-sbp-mode]' );
			if ( button ) {
				setMode( button.dataset.sbpMode );
			}
		} );
	}

	// ---- Copy the CLI command ----
	document.addEventListener( 'click', async ( event ) => {
		const button = event.target.closest( '[data-sbp-copy]' );
		if ( ! button ) {
			return;
		}
		try {
			await navigator.clipboard.writeText( button.dataset.sbpCopy );
			button.classList.add( 'is-copied' );
			setTimeout( () => button.classList.remove( 'is-copied' ), 1600 );
		} catch {}
	} );
} )();
