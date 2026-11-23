/**
 * Each header or rail instance copies Markdown and opens the same chat destinations.
 */
( () => {
	const { copyText, flashLabel } = window.studioDocs;

	for ( const actions of document.querySelectorAll( '[data-docs-page-actions]' ) ) {
		const menu = actions.querySelector( '.studio-docs-page-actions__menu' );
		const toggle = menu?.querySelector( '[data-docs-page-actions-toggle]' );
		const dropdown = menu?.querySelector( '[role="menu"]' );
		const items = dropdown ? [ ...dropdown.querySelectorAll( '[role="menuitem"]' ) ] : [];
		const backdrop = actions.querySelector( '[data-docs-page-actions-backdrop]' );

		// Keep the focused item visible by scrolling only the menu, never the page.
		const focusItem = ( item ) => {
			if ( ! item || ! dropdown ) {
				return;
			}
			item.focus( { preventScroll: true } );
			const menuBox = dropdown.getBoundingClientRect();
			const itemBox = item.getBoundingClientRect();
			if ( itemBox.top < menuBox.top ) {
				dropdown.scrollTop += itemBox.top - menuBox.top;
			} else if ( itemBox.bottom > menuBox.bottom ) {
				dropdown.scrollTop += itemBox.bottom - menuBox.bottom;
			}
		};

		// The rail scrolls independently. Keep its menu in the visible part of the rail.
		const flip = () => {
			if ( ! menu || ! dropdown || dropdown.hidden || ! actions.classList.contains( 'is-rail' ) ) {
				return;
			}
			dropdown.style.maxHeight = '';
			const box = toggle.getBoundingClientRect();
			const rail = actions.closest( '.studio-docs-rail' )?.getBoundingClientRect();
			const top = Math.max( 0, rail?.top ?? 0 );
			const bottom = Math.min( window.innerHeight, rail?.bottom ?? window.innerHeight );
			const above = box.top - top - 6;
			const below = bottom - box.bottom - 6;
			const up = dropdown.offsetHeight > below && above > below;
			menu.toggleAttribute( 'data-docs-flip', up );
			dropdown.style.maxHeight = `${ Math.max( 0, up ? above : below ) }px`;
		};
		const open = () => {
			if ( ! toggle || ! dropdown ) {
				return;
			}
			dropdown.hidden = false;
			if ( backdrop ) {
				backdrop.hidden = false;
			}
			toggle.setAttribute( 'aria-expanded', 'true' );
			flip();
			focusItem( items[ 0 ] );
		};
		const close = ( focus ) => {
			if ( toggle && dropdown && ! dropdown.hidden ) {
				dropdown.hidden = true;
				if ( backdrop ) {
					backdrop.hidden = true;
				}
				toggle.setAttribute( 'aria-expanded', 'false' );
				if ( focus ) {
					toggle.focus( { preventScroll: true } );
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
			focusItem( items[ next ] );
		} );
		dropdown?.addEventListener( 'click', ( e ) => {
			if ( e.target.closest( '[role="menuitem"]' ) ) {
				close( true );
			}
		} );
		backdrop?.addEventListener( 'pointerdown', () => close( false ) );
		document.addEventListener( 'pointerdown', ( e ) => {
			if ( menu && ! menu.contains( e.target ) ) {
				close( false );
			}
		} );
		document.addEventListener( 'keydown', ( e ) => {
			if ( e.key === 'Escape' && dropdown && ! dropdown.hidden ) {
				close( menu.contains( e.target ) );
			}
		} );
		menu?.addEventListener( 'focusout', ( e ) => {
			if ( e.relatedTarget instanceof Node && ! menu.contains( e.relatedTarget ) ) {
				close( false );
			}
		} );
		window.addEventListener( 'resize', flip );
		actions.closest( '.studio-docs-rail' )?.addEventListener( 'scroll', flip );

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
		const status = actions.querySelector( '[data-docs-copy-status]' );
		for ( const copy of actions.querySelectorAll( '[data-docs-copy-page]' ) ) {
			copy.addEventListener( 'click', async () => {
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
					flashLabel( label, ok ? actions.dataset.copiedLabel || 'Copied' : actions.dataset.failedLabel || 'Copy failed', 2000, status );
				}
			} );
		}
	}
} )();
