/**
 * The docs script: the color scheme toggle, the navigation drawer, the "On this page" scroll-spy,
 * the page actions (Copy as Markdown, Open in chat, Scroll to top), the copy buttons on code
 * blocks, the image zoom and the search dialog. The behaviour follows the Blume build of the docs.
 */
( () => {
	'use strict';

	const root = document.documentElement;
	const save = ( key, value ) => {
		try {
			localStorage.setItem( key, value );
		} catch {}
	};
	const read = ( key ) => {
		try {
			return localStorage.getItem( key );
		} catch {
			return null;
		}
	};
	const ICONS = {
		check: '<path d="M20 6 9 17l-5-5"/>',
		copy: '<rect width="14" height="14" x="8" y="8" rx="2" ry="2"/><path d="M4 16c-1.1 0-2-.9-2-2V4c0-1.1.9-2 2-2h10c1.1 0 2 .9 2 2"/>',
		file: '<path d="M15 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7Z"/><path d="M14 2v4a2 2 0 0 0 2 2h4"/>',
	};
	const icon = ( name, size = 16 ) =>
		`<svg xmlns="http://www.w3.org/2000/svg" width="${ size }" height="${ size }" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">${ ICONS[ name ] ?? '' }</svg>`;
	const escapeHtml = ( s ) => String( s ).replace( /[&<>"']/g, ( c ) => ( { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[ c ] ) );

	/* ---- Clipboard, with a live region for the announcement. ---- */
	let status = null;
	const announce = ( text ) => {
		if ( ! status?.isConnected ) {
			status = document.createElement( 'div' );
			status.setAttribute( 'role', 'status' );
			status.className = 'sr-only';
			document.body.append( status );
		}
		status.textContent = '';
		status.textContent = text;
	};
	const copyText = async ( text ) => {
		try {
			await navigator.clipboard.writeText( text );
			return true;
		} catch {
			const area = document.createElement( 'textarea' );
			area.value = text;
			area.setAttribute( 'readonly', '' );
			area.style.cssText = 'position:fixed;top:0;left:-9999px;opacity:0';
			document.body.append( area );
			area.select();
			let ok = false;
			try {
				ok = document.execCommand( 'copy' );
			} catch {}
			area.remove();
			return ok;
		}
	};
	const flashLabel = ( el, text, ms = 1500 ) => {
		el.dataset.blumeLabel ??= el.textContent ?? '';
		el.textContent = text;
		announce( text );
		clearTimeout( el._timer );
		el._timer = setTimeout( () => {
			el.textContent = el.dataset.blumeLabel ?? '';
		}, ms );
	};

	/* ---- Header: theme toggle, drawer toggle. ---- */
	const drawerTop = () => {
		const header = document.querySelector( '[data-blume-header]' );
		if ( header ) {
			root.style.setProperty( '--blume-drawer-top', header.getBoundingClientRect().bottom + 'px' );
		}
	};
	document.addEventListener( 'click', ( e ) => {
		const toggle = e.target.closest( '[data-blume-theme-toggle]' );
		if ( toggle ) {
			const next = root.dataset.theme === 'dark' ? 'light' : 'dark';
			const style = document.createElement( 'style' );
			style.textContent = '*,*::before,*::after{transition:none!important}';
			document.head.append( style );
			root.dataset.theme = next;
			window.getComputedStyle( root ).opacity;
			setTimeout( () => style.remove(), 1 );
			save( 'blume-theme', next );
		}
		const nav = e.target.closest( '[data-blume-nav-toggle]' );
		if ( nav ) {
			if ( root.toggleAttribute( 'data-blume-nav-open' ) ) {
				drawerTop();
			}
		}
	} );
	addEventListener( 'resize', () => {
		if ( ! root.hasAttribute( 'data-blume-nav-open' ) ) {
			return;
		}
		if ( matchMedia( '(min-width:64rem)' ).matches ) {
			root.removeAttribute( 'data-blume-nav-open' );
		} else {
			drawerTop();
		}
	} );

	/* ---- Drawer: inert the page behind it, Escape closes, focus returns to the button. ---- */
	( () => {
		const wide = matchMedia( '(min-width: 64rem)' );
		const drawer = () => document.querySelector( '[data-blume-nav-drawer]' );
		const button = () => document.querySelector( '[data-blume-header] [data-blume-nav-toggle]' );
		const isOpen = () => ! wide.matches && root.hasAttribute( 'data-blume-nav-open' );
		const siblings = ( el ) => {
			const out = [];
			let node = el;
			while ( node !== document.body && node.parentElement ) {
				for ( const sibling of node.parentElement.children ) {
					if ( sibling !== node && ! sibling.matches( '[data-blume-header], [data-blume-nav-toggle], script, style, template' ) ) {
						out.push( sibling );
					}
				}
				node = node.parentElement;
			}
			return out;
		};
		const release = () => {
			for ( const el of document.querySelectorAll( '[data-blume-drawer-backdrop]' ) ) {
				el.removeAttribute( 'inert' );
				el.removeAttribute( 'data-blume-drawer-backdrop' );
			}
		};
		let wasOpen = false;
		const sync = () => {
			const open = isOpen();
			button()?.setAttribute( 'aria-expanded', open ? 'true' : 'false' );
			const el = drawer();
			if ( ! el ) {
				release();
				wasOpen = open;
				return;
			}
			const hidden = ! ( wide.matches || open );
			el.inert = hidden;
			if ( hidden ) {
				el.setAttribute( 'aria-hidden', 'true' );
			} else {
				el.removeAttribute( 'aria-hidden' );
			}
			if ( open ) {
				for ( const sibling of siblings( el ) ) {
					if ( ! sibling.hasAttribute( 'inert' ) ) {
						sibling.setAttribute( 'inert', '' );
						sibling.setAttribute( 'data-blume-drawer-backdrop', '' );
					}
				}
			} else {
				release();
				const active = document.activeElement;
				if ( wasOpen && ! wide.matches && active && ( el.contains( active ) || active.matches( '[data-blume-nav-toggle]' ) ) ) {
					button()?.focus();
				}
			}
			wasOpen = open;
		};
		sync();
		wide.addEventListener( 'change', sync );
		new MutationObserver( sync ).observe( root, { attributeFilter: [ 'data-blume-nav-open' ] } );
		document.addEventListener( 'keydown', ( e ) => {
			if ( e.key === 'Escape' && isOpen() && ! document.querySelector( 'dialog[open]' ) ) {
				root.removeAttribute( 'data-blume-nav-open' );
				sync();
				button()?.focus();
			}
		} );

		// Scroll the current page into view in the sidebar.
		const nav = drawer();
		const tree = nav?.querySelector( '[data-blume-nav-tree]' ) || nav;
		const current = tree ? [ ...tree.querySelectorAll( 'a[aria-current="page"]' ) ].find( ( a ) => a.getClientRects().length ) : null;
		if ( nav && current ) {
			const box = nav.getBoundingClientRect();
			const link = current.getBoundingClientRect();
			if ( link.top < box.top || link.bottom > box.bottom ) {
				nav.scrollTop += link.top - box.top - ( nav.clientHeight - link.height ) / 2;
			}
		}
	} )();

	/* ---- "On this page": scroll-spy. ---- */
	class Toc extends HTMLElement {
		#items = [];
		#current = null;
		#observer = null;
		#queued = false;
		#OFFSET = 72;

		connectedCallback() {
			for ( const link of this.querySelectorAll( 'a[href^="#"]' ) ) {
				const id = decodeURIComponent( link.hash.slice( 1 ) );
				const heading = id ? document.querySelector( `#${ CSS.escape( id ) }` ) : null;
				if ( heading ) {
					this.#items.push( { heading, link } );
				}
			}
			if ( ! this.#items.length ) {
				return;
			}
			this.#observer = new IntersectionObserver( () => this.#update(), { rootMargin: `-${ this.#OFFSET }px 0px -70% 0px`, threshold: 0 } );
			for ( const { heading } of this.#items ) {
				this.#observer.observe( heading );
			}
			window.addEventListener( 'scroll', this.#onScroll );
			this.#update();
		}

		disconnectedCallback() {
			this.#observer?.disconnect();
			window.removeEventListener( 'scroll', this.#onScroll );
		}

		#onScroll = () => {
			if ( this.#queued ) {
				return;
			}
			this.#queued = true;
			requestAnimationFrame( () => {
				this.#queued = false;
				this.#update();
			} );
		};

		#update() {
			const visible = this.#items.filter( ( { heading } ) => heading.getClientRects().length > 0 );
			const atEnd = window.innerHeight + window.scrollY >= root.scrollHeight - 2;
			let active = null;
			if ( atEnd ) {
				active = visible.at( -1 ) ?? null;
			} else {
				active = visible[ 0 ] ?? null;
				for ( const item of visible ) {
					if ( item.heading.getBoundingClientRect().top <= this.#OFFSET ) {
						active = item;
					}
				}
			}
			const link = active?.link ?? null;
			if ( link !== this.#current ) {
				this.#current?.removeAttribute( 'aria-current' );
				link?.setAttribute( 'aria-current', 'location' );
				this.#current = link;
			}
		}
	}
	if ( ! customElements.get( 'blume-toc' ) ) {
		customElements.define( 'blume-toc', Toc );
	}

	/* ---- Dropdowns (details[data-blume-dropdown]): close on outside click, Escape, focus out. ---- */
	const openDropdowns = () => [ ...document.querySelectorAll( 'details[data-blume-dropdown][open]' ) ];
	const closeDropdown = ( details, focus ) => {
		details.open = false;
		if ( focus ) {
			details.querySelector( 'summary' )?.focus();
		}
	};
	document.addEventListener( 'pointerdown', ( e ) => {
		for ( const d of openDropdowns() ) {
			if ( ! ( e.target instanceof Node && d.contains( e.target ) ) ) {
				closeDropdown( d, false );
			}
		}
	} );
	document.addEventListener( 'keydown', ( e ) => {
		if ( e.key !== 'Escape' || e.isComposing || ( e.target instanceof Element && e.target.closest( 'dialog' ) ) ) {
			return;
		}
		for ( const d of openDropdowns() ) {
			closeDropdown( d, e.target instanceof Node && d.contains( e.target ) );
		}
	} );
	document.addEventListener( 'focusout', ( e ) => {
		const { relatedTarget } = e;
		if ( ! ( relatedTarget instanceof Node ) ) {
			return;
		}
		for ( const d of openDropdowns() ) {
			if ( e.target instanceof Node && d.contains( e.target ) && ! d.contains( relatedTarget ) ) {
				closeDropdown( d, false );
			}
		}
	} );
	window.addEventListener( 'blur', () => {
		for ( const d of openDropdowns() ) {
			closeDropdown( d, false );
		}
	} );

	/* ---- Page actions. ---- */
	( () => {
		const actions = document.querySelector( '[data-blume-page-actions]' );
		if ( ! actions ) {
			return;
		}
		const wide = matchMedia( '(min-width: 80rem)' );
		const place = () => {
			const target = document.querySelector( wide.matches ? '[data-blume-toc]' : '[data-blume-page-actions-mount]' );
			if ( target && actions.parentElement !== target ) {
				for ( const d of actions.querySelectorAll( 'details' ) ) {
					d.open = false;
					d.toggleAttribute( 'data-blume-dropdown', wide.matches );
				}
				target.append( actions );
			}
		};
		const flip = ( details ) => {
			const menu = details.querySelector( '[data-blume-menu]' );
			const summary = details.querySelector( 'summary' );
			if ( ! menu || ! summary ) {
				return;
			}
			const box = summary.getBoundingClientRect();
			const rail = details.closest( '[data-blume-toc]' )?.getBoundingClientRect() ?? { bottom: window.innerHeight, top: 0 };
			const up = box.bottom + menu.offsetHeight + 8 > rail.bottom && box.top - menu.offsetHeight - 8 > rail.top;
			menu.classList.toggle( 'top-full', ! up );
			menu.classList.toggle( 'mt-1', ! up );
			menu.classList.toggle( 'bottom-full', up );
			menu.classList.toggle( 'mb-1', up );
		};
		const all = [ ...actions.querySelectorAll( 'details' ) ];
		for ( const d of all ) {
			d.addEventListener( 'toggle', () => {
				if ( d.open ) {
					for ( const other of all ) {
						if ( other !== d ) {
							other.open = false;
						}
					}
					if ( d.hasAttribute( 'data-blume-dropdown' ) ) {
						flip( d );
					}
				}
			} );
		}
		window.addEventListener( 'resize', () => {
			const open = actions.querySelector( 'details[data-blume-dropdown][open]' );
			if ( open ) {
				flip( open );
			}
		} );

		const md = actions.getAttribute( 'data-md' ) ?? '';
		const mdUrl = new URL( md, location.origin ).href;
		const prompt = encodeURIComponent( ( actions.getAttribute( 'data-i18n-chat-prompt' ) ?? '' ).replaceAll( '{url}', () => mdUrl ) );
		const targets = {
			chatgpt: ( q ) => `https://chatgpt.com/?hints=search&prompt=${ q }`,
			claude: ( q ) => `https://claude.ai/new?q=${ q }`,
			cursor: ( q ) => `https://cursor.com/link/prompt?text=${ q }`,
			scira: ( q ) => `https://scira.ai/?q=${ q }`,
			t3: ( q ) => `https://t3.chat/new?q=${ q }`,
			v0: ( q ) => `https://v0.app?q=${ q }`,
		};
		for ( const a of actions.querySelectorAll( '[data-open-in]' ) ) {
			const build = targets[ a.getAttribute( 'data-open-in' ) ?? '' ];
			if ( build ) {
				a.setAttribute( 'href', build( prompt ) );
			}
		}
		actions.querySelector( '[data-blume-scroll-top]' )?.addEventListener( 'click', () => window.scrollTo( { behavior: 'smooth', top: 0 } ) );

		const copied = actions.getAttribute( 'data-i18n-copied' ) || 'Copied!';
		const failed = actions.getAttribute( 'data-i18n-copy-failed' ) || 'Copy failed';
		const label = actions.querySelector( '[data-blume-copy-label]' );
		actions.querySelector( '[data-blume-copy-page]' )?.addEventListener( 'click', async () => {
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
				flashLabel( label, ok ? copied : failed );
			}
		} );

		place();
		wide.addEventListener( 'change', place );
	} )();

	/* ---- Code blocks: the copy button. ---- */
	for ( const button of document.querySelectorAll( '.prose pre [data-blume-copy]' ) ) {
		const pre = button.closest( 'pre' );
		const [ check, copy ] = button.querySelectorAll( 'svg' );
		let timer;
		button.addEventListener( 'click', async () => {
			const code = pre?.querySelector( 'code' )?.textContent ?? '';
			if ( ! ( await copyText( code ) ) ) {
				return;
			}
			clearTimeout( timer );
			const show = ( done ) => {
				check?.classList.toggle( 'scale-0', ! done );
				copy?.classList.toggle( 'scale-0', done );
				button.setAttribute( 'aria-label', done ? 'Copied!' : 'Copy code' );
				button.classList.toggle( 'hover:bg-muted', ! done );
				button.classList.toggle( 'hover:text-foreground', ! done );
			};
			show( true );
			announce( 'Copied!' );
			timer = setTimeout( () => show( false ), 1500 );
		} );
	}

	/* ---- Images: zoom (medium-zoom). ---- */
	if ( typeof mediumZoom === 'function' ) {
		const images = [ ...document.querySelectorAll( '.prose img:not([data-no-zoom])' ) ].filter( ( img ) => ! img.closest( 'a' ) );
		if ( images.length ) {
			mediumZoom( images, { background: 'color-mix(in oklab, var(--color-background) 80%, transparent)', margin: 24 } );
		}
	}

	/* ---- Search. ---- */
	class Search extends HTMLElement {
		#records = null;
		#loading = null;
		#failed = false;
		#popular = [];
		#rows = [];
		#selected = -1;
		#section = null;
		#previewOn = true;
		#seq = 0;
		#gen = 0;

		connectedCallback() {
			this.dialog = this.querySelector( '[data-blume-search-dialog]' );
			this.input = this.querySelector( '[data-blume-search-input]' );
			this.grid = this.querySelector( '[data-blume-search-grid]' );
			this.filters = this.querySelector( '[data-blume-search-filters]' );
			this.results = this.querySelector( '[data-blume-search-results]' );
			this.message = this.querySelector( '[data-blume-search-message]' );
			this.preview = this.querySelector( '[data-blume-search-preview]' );
			this.indexUrl = this.getAttribute( 'data-index' ) || '/docs/search-index.json';
			try {
				this.#popular = JSON.parse( this.querySelector( '[data-blume-search-popular]' )?.textContent || '[]' );
			} catch {
				this.#popular = [];
			}
			const mac = /mac|iphone|ipad|ipod/i.test( navigator.platform );
			const open = this.querySelector( '[data-blume-search-open]' );
			if ( open && ! mac ) {
				open.dataset.blumeTooltip = `${ open.dataset.label ?? '' } (⁦Ctrl K⁩)`;
			}
			const kbd = this.querySelector( '[data-blume-search-preview-kbd]' );
			if ( kbd ) {
				kbd.textContent = mac ? '⌘J' : 'Ctrl J';
			}
			this.#previewOn = read( 'blume-search-preview' ) !== '0';
			this.#applyPreview();
			open?.addEventListener( 'click', () => this.open() );
			document.addEventListener( 'keydown', this.#shortcut );
			this.input.addEventListener( 'input', () => this.render() );
			this.dialog.addEventListener( 'keydown', ( e ) => this.#keydown( e ) );
			this.dialog.addEventListener( 'close', () => root.removeAttribute( 'data-blume-search-dialog-open' ) );
			this.dialog.addEventListener( 'click', ( e ) => {
				if ( e.target === this.dialog ) {
					this.dialog.close();
				}
			} );
		}

		#shortcut = ( e ) => {
			const field = e.target instanceof HTMLElement && ( e.target.tagName === 'INPUT' || e.target.tagName === 'TEXTAREA' || e.target.isContentEditable );
			if ( ( e.key === 'k' || e.key === 'K' ) && ( e.metaKey || e.ctrlKey ) && ! e.shiftKey && ! e.altKey ) {
				e.preventDefault();
				this.dialog.open ? this.dialog.close() : this.open();
			} else if ( e.key === '/' && ! field ) {
				e.preventDefault();
				this.open();
			}
		};

		async open() {
			if ( this.dialog.open ) {
				return;
			}
			this.dialog.showModal();
			root.setAttribute( 'data-blume-search-dialog-open', '' );
			this.input.focus();
			this.input.select();
			this.render();
			await this.#load();
			this.render();
		}

		#load() {
			if ( this.#records ) {
				return Promise.resolve( this.#records );
			}
			this.#loading ??= fetch( this.indexUrl )
				.then( ( r ) => {
					if ( ! r.ok ) {
						throw new Error( String( r.status ) );
					}
					return r.json();
				} )
				.then( ( data ) => {
					const records = Array.isArray( data ) ? data : [];
					this.#records = records.map( ( r ) => ( {
						...r,
						_title: ( r.title || '' ).toLowerCase(),
						_description: ( r.description || '' ).toLowerCase(),
						_content: ( r.content || '' ).toLowerCase(),
						_headings: ( r.headings || [] ).map( ( h ) => ( h.text || '' ).toLowerCase() ),
					} ) );
					this.#failed = false;
					return this.#records;
				} )
				.catch( () => {
					this.#failed = true;
					this.#loading = null;
					return null;
				} );
			return this.#loading;
		}

		#keydown( e ) {
			if ( e.key === 'ArrowDown' ) {
				e.preventDefault();
				this.#move( 1 );
			} else if ( e.key === 'ArrowUp' ) {
				e.preventDefault();
				this.#move( -1 );
			} else if ( e.key === 'Enter' && ! e.isComposing ) {
				const row = this.#rows[ this.#selected ];
				if ( row ) {
					e.preventDefault();
					this.#activate( row );
				}
			} else if ( ( e.key === 'j' || e.key === 'J' ) && ( e.metaKey || e.ctrlKey ) ) {
				e.preventDefault();
				this.#previewOn = ! this.#previewOn;
				save( 'blume-search-preview', this.#previewOn ? '1' : '0' );
				this.#applyPreview();
				this.#updatePreview();
			}
		}

		#setMessage( text ) {
			this.message.textContent = text;
			this.message.hidden = ! text;
		}

		/* Every query word must match; the title counts most, then headings, description, content. */
		#search( query ) {
			const words = query.toLowerCase().split( /\s+/ ).filter( Boolean );
			const hits = [];
			for ( const r of this.#records ) {
				let score = 0;
				let ok = true;
				for ( const w of words ) {
					let s = 0;
					if ( r._title.includes( w ) ) {
						s += r._title.startsWith( w ) ? 12 : 8;
					}
					if ( r._headings.some( ( h ) => h.includes( w ) ) ) {
						s += 4;
					}
					if ( r._description.includes( w ) ) {
						s += 3;
					}
					if ( r._content.includes( w ) ) {
						s += 1;
					}
					if ( ! s ) {
						ok = false;
						break;
					}
					score += s;
				}
				if ( ok ) {
					hits.push( { record: r, score } );
				}
			}
			hits.sort( ( a, b ) => b.score - a.score );
			const sections = new Map();
			for ( const { record } of hits ) {
				const label = record.section || 'Docs';
				sections.set( label, ( sections.get( label ) ?? 0 ) + 1 );
			}
			return {
				hits: hits.map( ( h ) => h.record ),
				sections: [ ...sections ].map( ( [ label, count ] ) => ( { label, count } ) ),
				words,
			};
		}

		#mark( text, words ) {
			let html = escapeHtml( text );
			for ( const w of words ) {
				if ( w.length < 2 ) {
					continue;
				}
				html = html.replace( new RegExp( '(' + w.replace( /[.*+?^${}()|[\]\\]/g, '\\$&' ) + ')', 'ig' ), '<mark>$1</mark>' );
			}
			return html;
		}

		#excerpt( record, words, length ) {
			const text = record.content || record.description || '';
			const lower = text.toLowerCase();
			let at = -1;
			for ( const w of words ) {
				const i = lower.indexOf( w );
				if ( i !== -1 && ( at === -1 || i < at ) ) {
					at = i;
				}
			}
			if ( at === -1 ) {
				return this.#mark( text.slice( 0, length ) + ( text.length > length ? '…' : '' ), words );
			}
			const start = Math.max( 0, at - Math.floor( length / 3 ) );
			const slice = text.slice( start, start + length );
			return this.#mark( ( start > 0 ? '…' : '' ) + slice + ( start + length < text.length ? '…' : '' ), words );
		}

		render() {
			const query = this.input.value.trim();
			this.#gen += 1;
			this.#rows = [];
			this.#selected = -1;
			this.results.replaceChildren();
			this.#setMessage( '' );
			if ( ! query ) {
				this.#renderFilters( [] );
				if ( this.#popular.length ) {
					const group = this.#addGroup( 'Popular' );
					for ( const page of this.#popular ) {
						group.append( this.#linkRow( page.href, page.label ).el );
					}
				}
				this.#finish();
				return;
			}
			if ( ! this.#records ) {
				this.#renderFilters( [] );
				this.preview.replaceChildren();
				this.#setMessage( this.#failed ? 'Something went wrong. Please try again.' : '…' );
				return;
			}
			const result = this.#search( query );
			if ( this.#section && ! result.sections.some( ( s ) => s.label === this.#section ) ) {
				this.#section = null;
			}
			this.#renderFilters( result.sections );
			const hits = this.#section ? result.hits.filter( ( r ) => ( r.section || 'Docs' ) === this.#section ) : result.hits;
			if ( hits.length ) {
				const group = this.#addGroup( 'Results' );
				for ( const record of hits ) {
					group.append( this.#hitRow( record, result.words ).el );
				}
			} else {
				this.#setMessage( 'No results found.' );
			}
			this.#finish();
		}

		#renderFilters( sections ) {
			this.filters.replaceChildren();
			if ( sections.length < 2 ) {
				this.filters.hidden = true;
				return;
			}
			this.filters.hidden = false;
			const total = sections.reduce( ( n, s ) => n + s.count, 0 );
			this.filters.append( this.#pill( 'All', total, null ) );
			for ( const s of sections ) {
				this.filters.append( this.#pill( s.label, s.count, s.label ) );
			}
		}

		#pill( label, count, value ) {
			const button = document.createElement( 'button' );
			button.type = 'button';
			const active = this.#section === value;
			button.setAttribute( 'aria-pressed', String( active ) );
			button.className = `inline-flex cursor-pointer items-center gap-1.5 whitespace-nowrap rounded-full border px-2.5 py-1 font-medium text-xs transition-colors ${ active ? 'border-transparent bg-foreground text-background' : 'border-border text-muted-foreground hover:text-foreground' }`;
			button.innerHTML = `${ escapeHtml( label ) } <span class="opacity-60">${ count }</span>`;
			button.addEventListener( 'click', () => {
				this.#section = value;
				this.render();
				this.input.focus();
			} );
			return button;
		}

		#addGroup( label ) {
			const heading = document.createElement( 'p' );
			heading.className = 'm-0 px-2.5 pt-3 pb-1 font-normal text-muted-foreground text-xs';
			heading.textContent = label;
			heading.setAttribute( 'aria-hidden', 'true' );
			const group = document.createElement( 'div' );
			group.setAttribute( 'role', 'group' );
			group.setAttribute( 'aria-label', label );
			this.results.append( heading, group );
			return group;
		}

		#rowClass = 'flex w-full cursor-pointer items-start gap-2.5 rounded-blume border border-transparent px-2.5 py-2 text-start text-inherit transition-colors hover:no-underline';
		#markClass = '[&_mark]:rounded-sm [&_mark]:bg-accent/25 [&_mark]:text-inherit';

		#hitRow( record, words ) {
			const a = document.createElement( 'a' );
			a.href = record.url;
			a.className = `${ this.#rowClass } ${ this.#markClass }`;
			a.innerHTML = `
				<span class="mt-0.5 shrink-0 text-muted-foreground">${ icon( 'file' ) }</span>
				<span class="flex-1">
					<span class="block truncate font-normal text-foreground text-sm">${ this.#mark( record.title, words ) }</span>
					<span class="mt-0.5 line-clamp-2 text-muted-foreground text-xs">${ this.#excerpt( record, words, 160 ) }</span>
				</span>`;
			const row = { el: a, record, words, url: record.url };
			this.#bind( row );
			return row;
		}

		#linkRow( href, label ) {
			const a = document.createElement( 'a' );
			a.href = href;
			a.className = this.#rowClass;
			a.innerHTML = `
				<span class="mt-0.5 shrink-0 text-muted-foreground">${ icon( 'file' ) }</span>
				<span class="flex-1"><span class="block truncate font-normal text-foreground text-sm">${ escapeHtml( label ) }</span></span>`;
			const row = { el: a, url: href };
			this.#bind( row );
			return row;
		}

		#bind( row ) {
			row.el.setAttribute( 'role', 'option' );
			row.el.setAttribute( 'aria-selected', 'false' );
			row.el.id = `blume-search-option-${ ++this.#seq }`;
			row.el.addEventListener( 'mouseenter', () => this.#select( this.#rows.indexOf( row ) ) );
			this.#rows.push( row );
		}

		#finish() {
			this.input.setAttribute( 'aria-expanded', String( this.#rows.length > 0 ) );
			if ( this.#rows.length ) {
				this.#select( 0 );
			} else {
				this.input.removeAttribute( 'aria-activedescendant' );
				this.preview.replaceChildren();
			}
		}

		#select( index ) {
			if ( index < 0 || index >= this.#rows.length ) {
				return;
			}
			const previous = this.#rows[ this.#selected ];
			if ( previous ) {
				previous.el.classList.remove( 'bg-muted', 'border-border' );
				previous.el.classList.add( 'border-transparent' );
				previous.el.setAttribute( 'aria-selected', 'false' );
			}
			this.#selected = index;
			const row = this.#rows[ index ];
			row.el.classList.remove( 'border-transparent' );
			row.el.classList.add( 'bg-muted', 'border-border' );
			row.el.setAttribute( 'aria-selected', 'true' );
			this.input.setAttribute( 'aria-activedescendant', row.el.id );
			row.el.scrollIntoView( { block: 'nearest' } );
			this.#updatePreview();
		}

		#move( delta ) {
			if ( ! this.#rows.length ) {
				return;
			}
			this.#select( Math.min( this.#rows.length - 1, Math.max( 0, this.#selected + delta ) ) );
		}

		#activate( row ) {
			if ( row.url ) {
				this.dialog.close();
				location.href = row.url;
			}
		}

		#updatePreview() {
			if ( ! this.#previewOn ) {
				return;
			}
			const row = this.#rows[ this.#selected ];
			if ( ! row?.record ) {
				this.preview.replaceChildren();
				return;
			}
			this.preview.innerHTML = `
				<h3 class="m-0 mb-3 font-medium text-foreground text-lg ${ this.#markClass }">${ this.#mark( row.record.title, row.words ) }</h3>
				<div class="text-muted-foreground text-sm leading-relaxed ${ this.#markClass }">${ this.#excerpt( row.record, row.words, 600 ) }</div>`;
		}

		#applyPreview() {
			this.grid.classList.toggle( 'md:grid-cols-[22rem_minmax(0,1fr)]', this.#previewOn );
			this.preview.classList.toggle( 'md:block', this.#previewOn );
		}
	}
	if ( ! customElements.get( 'blume-search' ) ) {
		customElements.define( 'blume-search', Search );
	}
} )();
