import React from 'react';
import { AbsoluteFill, Img, interpolate, staticFile, useCurrentFrame } from 'remotion';
import { Mosaic } from '../Mosaic';
import { BAR, C, easeInOut, easeOut, ramp, SANS } from '../theme';

/** 0–4 s: the mosaic assembles and the first line lands. */
export const Intro: React.FC = () => {
	const frame = useCurrentFrame();
	const line = ( text: string, at: number, color = C.text ) => (
		<div
			style={ {
				font: `600 150px/1 ${ SANS }`,
				letterSpacing: '-0.045em',
				color,
				opacity: ramp( frame, at, at + 14 ),
				transform: `translateY(${ ramp( frame, at, at + 22, 40, 0 ) }px)`,
			} }
		>
			{ text }
		</div>
	);
	const out = ramp( frame, 2 * BAR - 10, 2 * BAR, 1, 0, easeInOut );
	return (
		<AbsoluteFill style={ { background: C.white } }>
			<Mosaic
				width={ 1200 }
				height={ 1000 }
				start={ 0 }
				origin="top-right"
				style={ { right: -20, top: -20 } }
				mask="linear-gradient(to left, black 35%, transparent 95%), linear-gradient(to bottom, black 40%, transparent 100%)"
			/>
			<AbsoluteFill style={ { padding: '0 140px', justifyContent: 'center', opacity: out } }>
				{ line( 'Describe it.', 18 ) }
			</AbsoluteFill>
		</AbsoluteFill>
	);
};

/** 4–10 s: the real Studio app, the prompt typed into "Create a new site". */
export const Typing: React.FC = () => {
	const frame = useCurrentFrame();
	const TOTAL = 124;
	const idx = Math.round( interpolate( frame, [ 16, 150 ], [ 0, TOTAL - 1 ], { extrapolateLeft: 'clamp', extrapolateRight: 'clamp' } ) );
	// The homepage frames the video as the app window, so the capture fills it, still.
	return (
		<AbsoluteFill style={ { background: C.white } }>
			<Img
				src={ staticFile( `typing/f${ String( idx ).padStart( 4, '0' ) }.jpg` ) }
				style={ { width: '100%', height: '100%', objectFit: 'cover' } }
			/>
		</AbsoluteFill>
	);
};
