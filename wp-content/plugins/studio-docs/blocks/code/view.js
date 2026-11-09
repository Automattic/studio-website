/**
 * Code block: the copy button.
 */
( () => {
	const { copyText, announce } = window.studioDocs;
	for ( const button of document.querySelectorAll( '.wp-block-studio-docs-code .studio-docs-code__copy' ) ) {
		const pre = button.closest( 'pre' );
		const label = button.getAttribute( 'aria-label' ) || '';
		let timer;
		button.addEventListener( 'click', async () => {
			const code = pre?.querySelector( 'code' )?.textContent ?? '';
			if ( ! ( await copyText( code ) ) ) {
				return;
			}
			clearTimeout( timer );
			button.dataset.copied = '';
			button.setAttribute( 'aria-label', button.dataset.copiedLabel || 'Copied!' );
			announce( button.dataset.copiedLabel || 'Copied!' );
			timer = setTimeout( () => {
				delete button.dataset.copied;
				button.setAttribute( 'aria-label', label );
			}, 1500 );
		} );
	}
} )();
