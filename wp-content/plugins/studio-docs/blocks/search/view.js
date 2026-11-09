/**
 * Search dialog: loads the docs search index on first open, scores every record against the query
 * words (title, headings, description, content), filters by section, previews the selected result.
 * Shortcuts: ⌘K / Ctrl K and "/" open it, ⌘J / Ctrl J toggles the preview pane.
 */
( () => {
	const { escapeHtml, icon, readStorage: read, saveStorage: save } = window.studioDocs;

	class Search {
		records = null;
		loading = null;
		failed = false;
		popular = [];
		rows = [];
		selected = -1;
		section = null;
		previewOn = true;
		seq = 0;

		constructor( el ) {
			this.el = el;
			this.dialog = el.querySelector( '[data-docs-search-dialog]' );
			this.input = el.querySelector( '[data-docs-search-input]' );
			this.body = el.querySelector( '[data-docs-search-body]' );
			this.filters = el.querySelector( '[data-docs-search-filters]' );
			this.results = el.querySelector( '[data-docs-search-results]' );
			this.message = el.querySelector( '[data-docs-search-message]' );
			this.preview = el.querySelector( '[data-docs-search-preview]' );
			this.indexUrl = el.dataset.index || '/docs/search-index.json';
			try {
				this.popular = JSON.parse( el.querySelector( '[data-docs-search-popular]' )?.textContent || '[]' );
			} catch {
				this.popular = [];
			}
			const mac = /mac|iphone|ipad|ipod/i.test( navigator.platform );
			const open = el.querySelector( '[data-docs-search-open]' );
			if ( open && ! mac ) {
				open.title = open.title.replace( '⌘K', 'Ctrl K' );
			}
			const key = el.querySelector( '[data-docs-search-preview-key]' );
			if ( key ) {
				key.textContent = mac ? '⌘J' : 'Ctrl J';
			}
			this.previewOn = read( 'studio-docs-search-preview' ) !== '0';
			this.applyPreview();
			open?.addEventListener( 'click', () => this.open() );
			document.addEventListener( 'keydown', ( e ) => this.shortcut( e ) );
			this.input.addEventListener( 'input', () => this.render() );
			this.dialog.addEventListener( 'keydown', ( e ) => this.keydown( e ) );
			this.dialog.addEventListener( 'click', ( e ) => {
				if ( e.target === this.dialog ) {
					this.dialog.close();
				}
			} );
		}

		shortcut( e ) {
			const field = e.target instanceof HTMLElement && ( e.target.tagName === 'INPUT' || e.target.tagName === 'TEXTAREA' || e.target.isContentEditable );
			if ( ( e.key === 'k' || e.key === 'K' ) && ( e.metaKey || e.ctrlKey ) && ! e.shiftKey && ! e.altKey ) {
				e.preventDefault();
				this.dialog.open ? this.dialog.close() : this.open();
			} else if ( e.key === '/' && ! field ) {
				e.preventDefault();
				this.open();
			}
		}

		async open() {
			if ( this.dialog.open ) {
				return;
			}
			this.dialog.showModal();
			this.input.focus();
			this.input.select();
			this.render();
			await this.load();
			this.render();
		}

		load() {
			if ( this.records ) {
				return Promise.resolve( this.records );
			}
			this.loading ??= fetch( this.indexUrl )
				.then( ( r ) => {
					if ( ! r.ok ) {
						throw new Error( String( r.status ) );
					}
					return r.json();
				} )
				.then( ( data ) => {
					this.records = ( Array.isArray( data ) ? data : [] ).map( ( r ) => ( {
						...r,
						_title: ( r.title || '' ).toLowerCase(),
						_description: ( r.description || '' ).toLowerCase(),
						_content: ( r.content || '' ).toLowerCase(),
						_headings: ( r.headings || [] ).map( ( h ) => ( h.text || '' ).toLowerCase() ),
					} ) );
					this.failed = false;
					return this.records;
				} )
				.catch( () => {
					this.failed = true;
					this.loading = null;
					return null;
				} );
			return this.loading;
		}

		keydown( e ) {
			if ( e.key === 'ArrowDown' ) {
				e.preventDefault();
				this.move( 1 );
			} else if ( e.key === 'ArrowUp' ) {
				e.preventDefault();
				this.move( -1 );
			} else if ( e.key === 'Enter' && ! e.isComposing ) {
				const row = this.rows[ this.selected ];
				if ( row ) {
					e.preventDefault();
					this.activate( row );
				}
			} else if ( ( e.key === 'j' || e.key === 'J' ) && ( e.metaKey || e.ctrlKey ) ) {
				e.preventDefault();
				this.previewOn = ! this.previewOn;
				save( 'studio-docs-search-preview', this.previewOn ? '1' : '0' );
				this.applyPreview();
				this.updatePreview();
			}
		}

		setMessage( text ) {
			this.message.textContent = text;
			this.message.hidden = ! text;
		}

		/* Every query word must match; the title counts most, then headings, description, content. */
		search( query ) {
			const words = query.toLowerCase().split( /\s+/ ).filter( Boolean );
			const hits = [];
			for ( const r of this.records ) {
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
			return { hits: hits.map( ( h ) => h.record ), sections: [ ...sections ].map( ( [ label, count ] ) => ( { label, count } ) ), words };
		}

		mark( text, words ) {
			let html = escapeHtml( text );
			for ( const w of words ) {
				if ( w.length < 2 ) {
					continue;
				}
				html = html.replace( new RegExp( '(' + w.replace( /[.*+?^${}()|[\]\\]/g, '\\$&' ) + ')', 'ig' ), '<mark>$1</mark>' );
			}
			return html;
		}

		excerpt( record, words, length ) {
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
				return this.mark( text.slice( 0, length ) + ( text.length > length ? '…' : '' ), words );
			}
			const start = Math.max( 0, at - Math.floor( length / 3 ) );
			const slice = text.slice( start, start + length );
			return this.mark( ( start > 0 ? '…' : '' ) + slice + ( start + length < text.length ? '…' : '' ), words );
		}

		render() {
			const query = this.input.value.trim();
			this.rows = [];
			this.selected = -1;
			this.results.replaceChildren();
			this.setMessage( '' );
			if ( ! query ) {
				this.renderFilters( [] );
				if ( this.popular.length ) {
					const group = this.addGroup( 'Popular' );
					for ( const page of this.popular ) {
						group.append( this.linkRow( page.href, page.label ).el );
					}
				}
				this.finish();
				return;
			}
			if ( ! this.records ) {
				this.renderFilters( [] );
				this.preview.replaceChildren();
				this.setMessage( this.failed ? 'Something went wrong. Please try again.' : '…' );
				return;
			}
			const result = this.search( query );
			if ( this.section && ! result.sections.some( ( s ) => s.label === this.section ) ) {
				this.section = null;
			}
			this.renderFilters( result.sections );
			const hits = this.section ? result.hits.filter( ( r ) => ( r.section || 'Docs' ) === this.section ) : result.hits;
			if ( hits.length ) {
				const group = this.addGroup( 'Results' );
				for ( const record of hits ) {
					group.append( this.hitRow( record, result.words ).el );
				}
			} else {
				this.setMessage( 'No results found.' );
			}
			this.finish();
		}

		renderFilters( sections ) {
			this.filters.replaceChildren();
			if ( sections.length < 2 ) {
				this.filters.hidden = true;
				return;
			}
			this.filters.hidden = false;
			const total = sections.reduce( ( n, s ) => n + s.count, 0 );
			this.filters.append( this.pill( 'All', total, null ) );
			for ( const s of sections ) {
				this.filters.append( this.pill( s.label, s.count, s.label ) );
			}
		}

		pill( label, count, value ) {
			const button = document.createElement( 'button' );
			button.type = 'button';
			button.className = 'studio-docs-search__pill';
			button.setAttribute( 'aria-pressed', String( this.section === value ) );
			button.innerHTML = `${ escapeHtml( label ) } <span>${ count }</span>`;
			button.addEventListener( 'click', () => {
				this.section = value;
				this.render();
				this.input.focus();
			} );
			return button;
		}

		addGroup( label ) {
			const heading = document.createElement( 'p' );
			heading.className = 'studio-docs-search__group-label';
			heading.textContent = label;
			heading.setAttribute( 'aria-hidden', 'true' );
			const group = document.createElement( 'div' );
			group.setAttribute( 'role', 'group' );
			group.setAttribute( 'aria-label', label );
			this.results.append( heading, group );
			return group;
		}

		hitRow( record, words ) {
			const a = document.createElement( 'a' );
			a.href = record.url;
			a.className = 'studio-docs-search__option';
			a.innerHTML = `${ icon( 'file' ) }<span class="studio-docs-search__option-body"><span class="studio-docs-search__option-title">${ this.mark( record.title, words ) }</span><span class="studio-docs-search__option-excerpt">${ this.excerpt( record, words, 160 ) }</span></span>`;
			const row = { el: a, record, words, url: record.url };
			this.bind( row );
			return row;
		}

		linkRow( href, label ) {
			const a = document.createElement( 'a' );
			a.href = href;
			a.className = 'studio-docs-search__option';
			a.innerHTML = `${ icon( 'file' ) }<span class="studio-docs-search__option-body"><span class="studio-docs-search__option-title">${ escapeHtml( label ) }</span></span>`;
			const row = { el: a, url: href };
			this.bind( row );
			return row;
		}

		bind( row ) {
			row.el.setAttribute( 'role', 'option' );
			row.el.setAttribute( 'aria-selected', 'false' );
			row.el.id = `studio-docs-search-option-${ ++this.seq }`;
			row.el.addEventListener( 'mouseenter', () => this.select( this.rows.indexOf( row ) ) );
			this.rows.push( row );
		}

		finish() {
			this.input.setAttribute( 'aria-expanded', String( this.rows.length > 0 ) );
			if ( this.rows.length ) {
				this.select( 0 );
			} else {
				this.input.removeAttribute( 'aria-activedescendant' );
				this.preview.replaceChildren();
			}
		}

		select( index ) {
			if ( index < 0 || index >= this.rows.length ) {
				return;
			}
			this.rows[ this.selected ]?.el.setAttribute( 'aria-selected', 'false' );
			this.selected = index;
			const row = this.rows[ index ];
			row.el.setAttribute( 'aria-selected', 'true' );
			this.input.setAttribute( 'aria-activedescendant', row.el.id );
			row.el.scrollIntoView( { block: 'nearest' } );
			this.updatePreview();
		}

		move( delta ) {
			if ( this.rows.length ) {
				this.select( Math.min( this.rows.length - 1, Math.max( 0, this.selected + delta ) ) );
			}
		}

		activate( row ) {
			if ( row.url ) {
				this.dialog.close();
				location.href = row.url;
			}
		}

		updatePreview() {
			if ( ! this.previewOn ) {
				return;
			}
			const row = this.rows[ this.selected ];
			if ( ! row?.record ) {
				this.preview.replaceChildren();
				return;
			}
			this.preview.innerHTML = `<h3>${ this.mark( row.record.title, row.words ) }</h3><div>${ this.excerpt( row.record, row.words, 600 ) }</div>`;
		}

		applyPreview() {
			this.body.classList.toggle( 'has-preview', this.previewOn );
		}
	}

	for ( const el of document.querySelectorAll( '[data-docs-search]' ) ) {
		new Search( el );
	}
} )();
