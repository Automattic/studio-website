/**
 * Table of contents: marks the heading in view with aria-current="location".
 */
( () => {
	const OFFSET = 72;
	for ( const list of document.querySelectorAll( '.wp-block-studio-docs-toc .studio-docs-toc__list' ) ) {
		const items = [];
		for ( const link of list.querySelectorAll( 'a[href^="#"]' ) ) {
			const id = decodeURIComponent( link.hash.slice( 1 ) );
			const heading = id ? document.getElementById( id ) : null;
			if ( heading ) {
				items.push( { heading, link } );
			}
		}
		if ( ! items.length ) {
			continue;
		}
		let current = null;
		let queued = false;
		const update = () => {
			const visible = items.filter( ( { heading } ) => heading.getClientRects().length > 0 );
			const atEnd = window.innerHeight + window.scrollY >= document.documentElement.scrollHeight - 2;
			let active = null;
			if ( atEnd ) {
				active = visible.at( -1 ) ?? null;
			} else {
				active = visible[ 0 ] ?? null;
				for ( const item of visible ) {
					if ( item.heading.getBoundingClientRect().top <= OFFSET ) {
						active = item;
					}
				}
			}
			const link = active?.link ?? null;
			if ( link !== current ) {
				current?.removeAttribute( 'aria-current' );
				link?.setAttribute( 'aria-current', 'location' );
				current = link;
			}
		};
		const observer = new IntersectionObserver( update, { rootMargin: `-${ OFFSET }px 0px -70% 0px`, threshold: 0 } );
		for ( const { heading } of items ) {
			observer.observe( heading );
		}
		window.addEventListener( 'scroll', () => {
			if ( queued ) {
				return;
			}
			queued = true;
			requestAnimationFrame( () => {
				queued = false;
				update();
			} );
		} );
		update();
	}
} )();
