/**
 * Theme toggle: flips `data-theme` on <html> (set before the first paint by the inline script in the
 * head) and remembers the choice.
 */
( () => {
	const root = document.documentElement;
	document.addEventListener( 'click', ( e ) => {
		const toggle = e.target.closest( '[data-docs-theme-toggle]' );
		if ( ! toggle ) {
			return;
		}
		const next = root.dataset.theme === 'dark' ? 'light' : 'dark';
		// No transitions while every color changes at once.
		const style = document.createElement( 'style' );
		style.textContent = '*,*::before,*::after{transition:none!important}';
		document.head.append( style );
		root.dataset.theme = next;
		window.getComputedStyle( root ).opacity;
		setTimeout( () => style.remove(), 1 );
		window.studioDocs.saveStorage( 'studio-docs-theme', next );
	} );
} )();
