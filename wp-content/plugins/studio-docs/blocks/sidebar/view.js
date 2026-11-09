/**
 * The sidebar drawer: the menu toggle opens and closes it, the page behind it is inert while it is
 * open, Escape closes it, focus returns to the button; the current page is scrolled into view.
 */
( () => {
	const root = document.documentElement;
	const wide = matchMedia( '(min-width: 64rem)' );
	const drawer = document.querySelector( '[data-docs-sidebar]' );
	if ( ! drawer ) {
		return;
	}
	const header = () => document.querySelector( '.studio-docs-header' );
	const button = () => document.querySelector( '.studio-docs-header [data-docs-menu-toggle]' );
	const isOpen = () => ! wide.matches && root.hasAttribute( 'data-docs-nav-open' );

	const drawerTop = () => {
		const el = header();
		if ( el ) {
			root.style.setProperty( '--docs-drawer-top', el.getBoundingClientRect().bottom + 'px' );
		}
	};
	document.addEventListener( 'click', ( e ) => {
		if ( e.target.closest( '[data-docs-menu-toggle]' ) && root.toggleAttribute( 'data-docs-nav-open' ) ) {
			drawerTop();
		}
	} );
	addEventListener( 'resize', () => {
		if ( ! root.hasAttribute( 'data-docs-nav-open' ) ) {
			return;
		}
		if ( wide.matches ) {
			root.removeAttribute( 'data-docs-nav-open' );
		} else {
			drawerTop();
		}
	} );

	// Everything but the header, the drawer and the backdrop is inert while the drawer is open.
	const siblings = () => {
		const out = [];
		let node = drawer;
		while ( node !== document.body && node.parentElement ) {
			for ( const sibling of node.parentElement.children ) {
				if ( sibling !== node && ! sibling.matches( '.studio-docs-header, .wp-block-template-part:has(.studio-docs-header), .studio-docs-sidebar-backdrop, script, style, template' ) ) {
					out.push( sibling );
				}
			}
			node = node.parentElement;
		}
		return out;
	};
	const release = () => {
		for ( const el of document.querySelectorAll( '[data-docs-inert]' ) ) {
			el.removeAttribute( 'inert' );
			el.removeAttribute( 'data-docs-inert' );
		}
	};
	let wasOpen = false;
	const sync = () => {
		const open = isOpen();
		button()?.setAttribute( 'aria-expanded', open ? 'true' : 'false' );
		const hidden = ! ( wide.matches || open );
		drawer.inert = hidden;
		if ( hidden ) {
			drawer.setAttribute( 'aria-hidden', 'true' );
		} else {
			drawer.removeAttribute( 'aria-hidden' );
		}
		if ( open ) {
			for ( const sibling of siblings() ) {
				if ( ! sibling.hasAttribute( 'inert' ) ) {
					sibling.setAttribute( 'inert', '' );
					sibling.setAttribute( 'data-docs-inert', '' );
				}
			}
		} else {
			release();
			const active = document.activeElement;
			if ( wasOpen && ! wide.matches && active && ( drawer.contains( active ) || active.matches( '[data-docs-menu-toggle]' ) ) ) {
				button()?.focus();
			}
		}
		wasOpen = open;
	};
	sync();
	wide.addEventListener( 'change', sync );
	new MutationObserver( sync ).observe( root, { attributeFilter: [ 'data-docs-nav-open' ] } );
	document.addEventListener( 'keydown', ( e ) => {
		if ( e.key === 'Escape' && isOpen() && ! document.querySelector( 'dialog[open]' ) ) {
			root.removeAttribute( 'data-docs-nav-open' );
			sync();
			button()?.focus();
		}
	} );

	// Scroll the current page into view.
	const current = [ ...drawer.querySelectorAll( 'a[aria-current="page"]' ) ].find( ( a ) => a.getClientRects().length );
	if ( current ) {
		const box = drawer.getBoundingClientRect();
		const link = current.getBoundingClientRect();
		if ( link.top < box.top || link.bottom > box.bottom ) {
			drawer.scrollTop += link.top - box.top - ( drawer.clientHeight - link.height ) / 2;
		}
	}
} )();
