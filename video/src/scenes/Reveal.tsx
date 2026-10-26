import React from 'react';
import { AbsoluteFill, Img, interpolate, spring, staticFile, useCurrentFrame, useVideoConfig } from 'remotion';
import { Browser, C, easeInOut, MONO, ramp, SANS } from '../theme';

/** 16–24 s: the finished featured site — first screen, a real scroll, then the phone view. */
export const Reveal: React.FC = () => {
	const frame = useCurrentFrame();
	const { fps } = useVideoConfig();
	const W = 1640;
	const H = 940;
	const inner = H - 46;
	// 150 frames of a real, eased scroll recorded in the browser (sticky panes behave as live).
	const shot = Math.round( interpolate( frame, [ 44, 200 ], [ 0, 149 ], { extrapolateLeft: 'clamp', extrapolateRight: 'clamp' } ) );
	const enter = spring( { frame, fps, config: { damping: 20, stiffness: 90 } } );
	const phone = spring( { frame: frame - 140, fps, config: { damping: 18, stiffness: 110 } } );
	const shrink = ramp( frame, 140, 175, 0, 1, easeInOut );
	return (
		<AbsoluteFill style={ { background: C.surface, alignItems: 'center', justifyContent: 'center' } }>
			<div style={ { transform: `translateX(${ -shrink * 170 }px) scale(${ ( 0.86 + 0.14 * enter ) * ( 1 - shrink * 0.08 ) })`, opacity: Math.min( 1, enter * 1.5 ) } }>
				<Browser width={ W } height={ H } url="localhost:8895">
					<Img src={ staticFile( `scroll/s${ String( shot ).padStart( 4, '0' ) }.jpg` ) } style={ { position: 'absolute', inset: 0, width: W, height: inner } } />
				</Browser>
			</div>
			<div
				style={ {
					position: 'absolute',
					right: 120,
					top: 140,
					width: 340,
					height: 736,
					borderRadius: 52,
					padding: 12,
					background: C.ink,
					boxShadow: '0 50px 90px -30px rgba(15,18,34,0.45)',
					transform: `translateX(${ ( 1 - phone ) * 700 }px) rotate(${ ( 1 - phone ) * 8 }deg)`,
				} }
			>
				<div style={ { width: '100%', height: '100%', borderRadius: 42, overflow: 'hidden', background: '#2a3038' } }>
					<Img src={ staticFile( 'img/featured-mobile.jpg' ) } style={ { width: '100%' } } />
				</div>
			</div>
			<div
				style={ {
					position: 'absolute',
					left: 140,
					bottom: 54,
					display: 'flex',
					gap: 14,
					alignItems: 'center',
					opacity: ramp( frame, 20, 34 ),
					transform: `translateY(${ ramp( frame, 20, 40, 20, 0 ) }px)`,
				} }
			>
				<div style={ { padding: '12px 22px', borderRadius: 999, background: C.white, border: `1.5px solid ${ C.rule }`, font: `600 24px/1 ${ SANS }`, color: C.text } }>
					Northlight Architects
				</div>
				<div style={ { padding: '12px 22px', borderRadius: 999, background: C.white, border: `1.5px solid ${ C.rule }`, font: `400 20px/1 ${ MONO }`, color: C.muted } }>
					one prompt · 6 min · Strong
				</div>
			</div>
		</AbsoluteFill>
	);
};
