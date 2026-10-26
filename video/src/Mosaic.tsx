import React, { useMemo } from 'react';
import { spring, useCurrentFrame, useVideoConfig } from 'remotion';
import { C, rand } from './theme';

type Kind = 'blank' | 'surface' | 'lines' | 'heading' | 'image' | 'button' | 'blue';
type Tile = { x: number; y: number; w: number; h: number; kind: Kind; seed: number };

const MOD = 48; // module incl. gap
const GAP = 10;

/** Packs the block-editor tiles of the site's mosaic onto a grid. */
const pack = ( cols: number, rows: number, seed: number ): Tile[] => {
	const used = new Set< string >();
	const tiles: Tile[] = [];
	const shapes = [ [ 1, 1 ], [ 1, 2 ], [ 2, 1 ], [ 2, 2 ], [ 1, 3 ], [ 3, 2 ], [ 2, 3 ] ];
	let n = seed;
	for ( let y = 0; y < rows; y++ ) {
		for ( let x = 0; x < cols; x++ ) {
			if ( used.has( `${ x },${ y }` ) ) continue;
			const fits = shapes.filter( ( [ w, h ] ) => {
				for ( let dx = 0; dx < w; dx++ ) for ( let dy = 0; dy < h; dy++ ) {
					if ( x + dx >= cols || y + dy >= rows || used.has( `${ x + dx },${ y + dy }` ) ) return false;
				}
				return true;
			} );
			const [ w, h ] = fits[ Math.floor( rand( n++ ) * fits.length ) ];
			for ( let dx = 0; dx < w; dx++ ) for ( let dy = 0; dy < h; dy++ ) used.add( `${ x + dx },${ y + dy }` );
			const r = rand( n++ );
			const area = w * h;
			let kind: Kind = r < 0.22 ? 'surface' : r < 0.42 ? 'blank' : r < 0.62 ? 'lines' : r < 0.74 ? 'heading' : r < 0.86 ? 'image' : r < 0.94 ? 'button' : 'blue';
			if ( kind === 'image' && area < 2 ) kind = 'surface';
			if ( kind === 'blue' && area > 2 ) kind = 'button';
			tiles.push( { x, y, w, h, kind, seed: n++ } );
		}
	}
	return tiles;
};

const TileView: React.FC< { t: Tile } > = ( { t } ) => {
	const W = t.w * MOD - GAP;
	const H = t.h * MOD - GAP;
	const base: React.CSSProperties = {
		position: 'absolute',
		inset: 0,
		borderRadius: 7,
		border: `1.5px solid ${ C.ruleStrong }`,
		background: t.kind === 'surface' ? C.surface : C.white,
	};
	if ( t.kind === 'blue' ) {
		return <div style={ { ...base, background: C.primary, border: 'none', opacity: 0.92 } } />;
	}
	const bar = ( top: number, width: number, height = 5, color = C.rule ) => (
		<div style={ { position: 'absolute', left: 12, top, width, height, borderRadius: 3, background: color } } />
	);
	return (
		<div style={ base }>
			{ t.kind === 'lines' &&
				[ 0, 1, 2 ].filter( ( i ) => 14 + i * 12 < H - 8 ).map( ( i ) => bar( 14 + i * 12, Math.max( 14, ( W - 24 ) * ( i === 2 ? 0.6 : 1 ) ) ) ) }
			{ t.kind === 'heading' && bar( 14, Math.max( 14, ( W - 24 ) * 0.7 ), 10, '#dfe2ea' ) }
			{ t.kind === 'button' && (
				<div
					style={ {
						position: 'absolute',
						left: 10,
						right: 10,
						top: H / 2 - 11,
						height: 22,
						borderRadius: 999,
						background: rand( t.seed ) < 0.5 ? C.primary : C.rule,
						opacity: 0.9,
					} }
				/>
			) }
			{ t.kind === 'image' && (
				<svg width={ W } height={ H } style={ { position: 'absolute', inset: 0 } }>
					<circle cx={ W * 0.3 } cy={ H * 0.32 } r={ 6 } fill={ C.ruleStrong } />
					<path d={ `M ${ W * 0.15 } ${ H * 0.8 } L ${ W * 0.42 } ${ H * 0.48 } L ${ W * 0.6 } ${ H * 0.66 } L ${ W * 0.72 } ${ H * 0.56 } L ${ W * 0.88 } ${ H * 0.8 } Z` } fill={ C.rule } />
				</svg>
			) }
		</div>
	);
};

/**
 * The site's block mosaic, assembling tile by tile. Tiles pop in waves from
 * `origin` on the beat grid starting at `start`; `fadeTo` sets where the
 * mask fades the field out (toward the copy and downward).
 */
export const Mosaic: React.FC< {
	width: number;
	height: number;
	start?: number;
	origin?: 'top-right' | 'bottom-left';
	seed?: number;
	style?: React.CSSProperties;
	mask?: string;
	exitAt?: number;
} > = ( { width, height, start = 0, origin = 'top-right', seed = 7, style, mask, exitAt } ) => {
	const frame = useCurrentFrame();
	const { fps } = useVideoConfig();
	const cols = Math.ceil( width / MOD );
	const rows = Math.ceil( height / MOD );
	const tiles = useMemo( () => pack( cols, rows, seed ), [ cols, rows, seed ] );
	return (
		<div
			style={ {
				position: 'absolute',
				width,
				height,
				WebkitMaskImage: mask,
				maskImage: mask,
				WebkitMaskComposite: 'source-in',
				maskComposite: 'intersect',
				...style,
			} }
		>
			{ tiles.map( ( t, i ) => {
				const ox = origin === 'top-right' ? cols - ( t.x + t.w / 2 ) : t.x + t.w / 2;
				const oy = origin === 'top-right' ? t.y + t.h / 2 : rows - ( t.y + t.h / 2 );
				const dist = Math.hypot( ox, oy * 1.2 );
				const delay = start + Math.round( dist * 2.2 + rand( t.seed + 3 ) * 8 );
				const s = spring( { frame: frame - delay, fps, config: { damping: 14, stiffness: 170, mass: 0.6 } } );
				let out = 1;
				if ( exitAt !== undefined ) {
					const e = spring( { frame: frame - exitAt - Math.round( rand( t.seed + 9 ) * 10 ), fps, config: { damping: 20, stiffness: 200 } } );
					out = 1 - e;
				}
				return (
					<div
						key={ i }
						style={ {
							position: 'absolute',
							left: t.x * MOD,
							top: t.y * MOD,
							width: t.w * MOD - GAP,
							height: t.h * MOD - GAP,
							opacity: Math.min( 1, s * 1.4 ) * out,
							transform: `scale(${ 0.6 + 0.4 * s }) translateY(${ ( 1 - s ) * 10 }px)`,
						} }
					>
						<TileView t={ t } />
					</div>
				);
			} ) }
		</div>
	);
};
