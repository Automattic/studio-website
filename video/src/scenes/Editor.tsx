import React from 'react';
import { AbsoluteFill, Img, interpolate, spring, staticFile, useCurrentFrame, useVideoConfig } from 'remotion';
import { BAR, C, easeInOut, Eyebrow, ramp, SANS } from '../theme';

/** 40–46 s: "Real WordPress" — title, then the site editor with the hero heading selected. */
export const Editor: React.FC = () => {
	const frame = useCurrentFrame();
	const { fps } = useVideoConfig();
	const T = BAR; // title holds one bar
	const titleOut = ramp( frame, T - 8, T + 4, 1, 0, easeInOut );
	const win = spring( { frame: frame - T + 6, fps, config: { damping: 20, stiffness: 100 } } );
	const swap = ramp( frame, T + 28, T + 38, 0, 1, easeInOut );
	// Push in toward the list view and the selected H1.
	const zoom = interpolate( frame, [ T + 30, 3 * BAR ], [ 1, 1.22 ], { extrapolateLeft: 'clamp', extrapolateRight: 'clamp', easing: easeInOut } );
	return (
		<AbsoluteFill style={ { background: C.white } }>
			<AbsoluteFill style={ { alignItems: 'center', justifyContent: 'center', opacity: titleOut } }>
				<div style={ { opacity: ramp( frame, 0, 10 ), transform: `translateY(${ ramp( frame, 0, 18, 30, 0 ) }px)`, textAlign: 'center' } }>
					<div style={ { font: `600 120px/1 ${ SANS }`, letterSpacing: '-0.045em', color: C.text } }>Real WordPress.</div>
					<div style={ { font: `600 120px/1 ${ SANS }`, letterSpacing: '-0.045em', color: C.muted, marginTop: 10, opacity: ramp( frame, 14, 26 ) } }>
						Not a prototype.
					</div>
				</div>
			</AbsoluteFill>
			{ frame >= T - 8 && (
				<AbsoluteFill style={ { background: C.surface, alignItems: 'center', justifyContent: 'center', opacity: ramp( frame, T - 8, T + 2 ) } }>
					<div
						style={ {
							width: 1600,
							height: 1000,
							borderRadius: 16,
							overflow: 'hidden',
							border: `1.5px solid ${ C.ruleStrong }`,
							boxShadow: '0 50px 100px -40px rgba(15,18,34,0.35)',
							position: 'relative',
							transform: `scale(${ ( 0.9 + 0.1 * win ) * 0.98 * zoom })`,
							transformOrigin: '30% 38%',
						} }
					>
						<Img src={ staticFile( 'img/editor-1.png' ) } style={ { position: 'absolute', width: 1600, height: 1000 } } />
						<Img src={ staticFile( 'img/editor-2.png' ) } style={ { position: 'absolute', width: 1600, height: 1000, opacity: swap } } />
					</div>
					<div style={ { position: 'absolute', left: 70, bottom: 50, opacity: ramp( frame, T + 40, T + 52 ) } }>
						<Eyebrow style={ { font: `500 24px/1 ${ SANS }`, padding: '12px 22px', boxShadow: '0 20px 40px -20px rgba(15,18,34,0.3)' } }>
							Every section is a block you can edit.
						</Eyebrow>
					</div>
				</AbsoluteFill>
			) }
		</AbsoluteFill>
	);
};
