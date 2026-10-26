import React, { useEffect, useState } from 'react';
import { continueRender, delayRender, Easing, Img, interpolate, staticFile } from 'remotion';

export const FPS = 30;
export const BAR = 60; // 120 BPM, 4/4: one bar = 2 s = 60 frames.
export const BEAT = 15;

export const C = {
	primary: '#3858e9',
	text: '#0f1222',
	muted: '#6b7080',
	rule: '#e8eaf0',
	ruleStrong: '#dfe2ea',
	surface: '#f6f7fb',
	white: '#ffffff',
	ink: '#0f1222',
};

export const SANS = '"Instrument Sans", system-ui, sans-serif';
export const MONO = '"JetBrains Mono", ui-monospace, monospace';

export const easeOut = Easing.bezier( 0.16, 1, 0.3, 1 );
export const easeInOut = Easing.bezier( 0.65, 0, 0.35, 1 );

export const ramp = ( frame: number, from: number, to: number, a = 0, b = 1, easing = easeOut ) =>
	interpolate( frame, [ from, to ], [ a, b ], { extrapolateLeft: 'clamp', extrapolateRight: 'clamp', easing } );

const FONTS: Array< [ string, string, string ] > = [
	[ 'Instrument Sans', 'fonts/instrument-sans.woff2', '400 600' ],
	[ 'JetBrains Mono', 'fonts/jetbrains-mono.woff2', '400' ],
	[ 'Manrope', 'fonts/manrope.woff2', '200 800' ],
];

export const useFonts = () => {
	const [ handle ] = useState( () => delayRender( 'fonts' ) );
	useEffect( () => {
		Promise.all(
			FONTS.map( ( [ family, file, weight ] ) => {
				const face = new FontFace( family, `url(${ staticFile( file ) }) format("woff2")`, { weight } );
				document.fonts.add( face );
				return face.load();
			} )
		).then( () => continueRender( handle ) );
	}, [ handle ] );
};

/** A pill eyebrow label, as on the site's section heads. */
export const Eyebrow: React.FC< { children: React.ReactNode; style?: React.CSSProperties } > = ( { children, style } ) => (
	<div
		style={ {
			display: 'inline-flex',
			alignItems: 'center',
			gap: 10,
			padding: '8px 18px',
			border: `1.5px solid ${ C.rule }`,
			borderRadius: 999,
			background: C.white,
			font: `500 22px/1 ${ SANS }`,
			color: C.text,
			...style,
		} }
	>
		{ children }
	</div>
);

/** A browser window: hairline chrome, address pill, 14px radius. */
export const Browser: React.FC< {
	width: number;
	height: number;
	url?: string;
	children: React.ReactNode;
	style?: React.CSSProperties;
} > = ( { width, height, url, children, style } ) => (
	<div
		style={ {
			width,
			height,
			borderRadius: 16,
			overflow: 'hidden',
			background: C.white,
			border: `1.5px solid ${ C.ruleStrong }`,
			boxShadow: '0 40px 80px -30px rgba(15, 18, 34, 0.25), 0 12px 24px -12px rgba(15, 18, 34, 0.12)',
			display: 'flex',
			flexDirection: 'column',
			...style,
		} }
	>
		<div
			style={ {
				height: 46,
				flex: 'none',
				display: 'flex',
				alignItems: 'center',
				gap: 8,
				padding: '0 18px',
				borderBottom: `1.5px solid ${ C.rule }`,
				background: C.surface,
			} }
		>
			{ [ 0, 1, 2 ].map( ( i ) => (
				<div key={ i } style={ { width: 12, height: 12, borderRadius: 6, background: C.ruleStrong } } />
			) ) }
			{ url && (
				<div
					style={ {
						margin: '0 auto',
						padding: '6px 22px',
						borderRadius: 999,
						background: C.white,
						border: `1.5px solid ${ C.rule }`,
						font: `400 16px/1 ${ MONO }`,
						color: C.muted,
					} }
				>
					{ url }
				</div>
			) }
		</div>
		<div style={ { position: 'relative', flex: 1, overflow: 'hidden' } }>{ children }</div>
	</div>
);

export const Cover: React.FC< { src: string; style?: React.CSSProperties } > = ( { src, style } ) => (
	<Img
		src={ staticFile( src ) }
		style={ { position: 'absolute', inset: 0, width: '100%', height: '100%', objectFit: 'cover', objectPosition: 'top', ...style } }
	/>
);

/** Deterministic pseudo-random in [0, 1). */
export const rand = ( n: number ) => {
	const x = Math.sin( n * 12.9898 + 78.233 ) * 43758.5453;
	return x - Math.floor( x );
};
