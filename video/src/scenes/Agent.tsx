import React from 'react';
import { AbsoluteFill, spring, useCurrentFrame, useVideoConfig } from 'remotion';
import { BEAT, C, Eyebrow, MONO, ramp, SANS } from '../theme';

// The real steps Studio Code took building Northlight Architects (from its run log).
const STEPS: Array< [ string, string ] > = [
	[ 'Picked a look', 'Scandinavian' ],
	[ 'Picked a layout', 'Poster pages' ],
	[ 'Wrote', 'DESIGN.md' ],
	[ 'Generated', '8 project photographs' ],
	[ 'Scaffolded', 'the block theme' ],
	[ 'Built', 'home and studio pages' ],
	[ 'Validated', 'every block' ],
	[ 'Checked', 'its own screenshots' ],
];

const SWATCHES = [ '#f7f8f9', '#e9e4dc', '#dde1e6', '#6f7884', '#2f5d4a', '#2a3038' ];

const Check: React.FC< { p: number } > = ( { p } ) => (
	<svg width={ 30 } height={ 30 } viewBox="0 0 30 30" style={ { flex: 'none' } }>
		<circle cx={ 15 } cy={ 15 } r={ 13 } fill={ p > 0.5 ? C.primary : 'none' } stroke={ p > 0.5 ? C.primary : C.ruleStrong } strokeWidth={ 2 } />
		<path
			d="M9 15.5 L13.2 19.5 L21 11"
			fill="none"
			stroke="#fff"
			strokeWidth={ 2.6 }
			strokeLinecap="round"
			strokeLinejoin="round"
			strokeDasharray={ 20 }
			strokeDashoffset={ 20 * ( 1 - Math.max( 0, ( p - 0.5 ) * 2 ) ) }
		/>
	</svg>
);

/** 10–16 s: Studio Code at work, with the featured site's real design system. */
export const Agent: React.FC = () => {
	const frame = useCurrentFrame();
	const { fps } = useVideoConfig();
	const stepAt = ( i: number ) => 20 + i * ( BEAT - 2 );
	return (
		<AbsoluteFill style={ { background: C.white, padding: '110px 130px', flexDirection: 'row', gap: 90 } }>
			<div style={ { width: 760 } }>
				<div style={ { opacity: ramp( frame, 0, 12 ) } }>
					<Eyebrow>
						<span style={ { width: 10, height: 10, borderRadius: 5, background: C.primary } } /> Studio Code
					</Eyebrow>
				</div>
				<div
					style={ {
						font: `600 92px/1 ${ SANS }`,
						letterSpacing: '-0.045em',
						color: C.text,
						margin: '34px 0 48px',
						opacity: ramp( frame, 4, 18 ),
						transform: `translateY(${ ramp( frame, 4, 24, 30, 0 ) }px)`,
					} }
				>
					Studio builds it.
				</div>
				<div style={ { display: 'flex', flexDirection: 'column', gap: 18 } }>
					{ STEPS.map( ( [ verb, what ], i ) => {
						const s = spring( { frame: frame - stepAt( i ), fps, config: { damping: 16, stiffness: 160 } } );
						const done = ramp( frame, stepAt( i ) + 6, stepAt( i ) + 16, 0, 1 );
						return (
							<div
								key={ i }
								style={ {
									display: 'flex',
									alignItems: 'center',
									gap: 18,
									opacity: s,
									transform: `translateX(${ ( 1 - s ) * -24 }px)`,
									font: `400 27px/1.2 ${ MONO }`,
									color: C.text,
								} }
							>
								<Check p={ done } />
								<span style={ { color: C.muted } }>{ verb }</span>
								<span>{ what }</span>
							</div>
						);
					} ) }
				</div>
			</div>
			<DesignCard />
		</AbsoluteFill>
	);
};

const DesignCard: React.FC = () => {
	const frame = useCurrentFrame();
	const { fps } = useVideoConfig();
	const appear = spring( { frame: frame - 44, fps, config: { damping: 18, stiffness: 120 } } );
	return (
		<div
			style={ {
				flex: 1,
				alignSelf: 'center',
				borderRadius: 18,
				border: `1.5px solid ${ C.ruleStrong }`,
				background: C.white,
				overflow: 'hidden',
				opacity: appear,
				transform: `translateY(${ ( 1 - appear ) * 50 }px)`,
				boxShadow: '0 40px 80px -40px rgba(15,18,34,0.25)',
			} }
		>
			<div style={ { padding: '18px 26px', borderBottom: `1.5px solid ${ C.rule }`, background: C.surface, font: `400 22px/1 ${ MONO }`, color: C.muted } }>
				northlight-architects / <span style={ { color: C.text } }>DESIGN.md</span>
			</div>
			<div style={ { padding: 34 } }>
				<div style={ { font: `500 18px/1 ${ SANS }`, color: C.muted, letterSpacing: '0.08em', textTransform: 'uppercase', marginBottom: 18 } }>Colors</div>
				<div style={ { display: 'flex', gap: 14, marginBottom: 36 } }>
					{ SWATCHES.map( ( c, i ) => {
						const s = spring( { frame: frame - 54 - i * 4, fps, config: { damping: 12, stiffness: 180 } } );
						return (
							<div
								key={ c }
								style={ {
									flex: 1,
									height: 96,
									borderRadius: 12,
									background: c,
									border: [ '#f7f8f9', '#e9e4dc', '#dde1e6' ].includes( c ) ? `1.5px solid ${ C.rule }` : 'none',
									transform: `scale(${ 0.5 + 0.5 * s })`,
									opacity: s,
								} }
							/>
						);
					} ) }
				</div>
				<div style={ { font: `500 18px/1 ${ SANS }`, color: C.muted, letterSpacing: '0.08em', textTransform: 'uppercase', marginBottom: 14 } }>Type</div>
				<div style={ { background: '#f7f8f9', border: '1.5px solid #dde1e6', borderRadius: 14, padding: '30px 34px', opacity: ramp( frame, 84, 100 ) } }>
					<div style={ { font: `500 64px/1.05 Manrope, sans-serif`, letterSpacing: '-0.025em', color: '#2a3038' } }>
						Buildings shaped by <span style={ { color: '#2f5d4a' } }>daylight.</span>
					</div>
					<div style={ { font: `400 22px/1.5 Manrope, sans-serif`, color: '#6f7884', marginTop: 12 } }>
						Manrope · cool, light and quiet
					</div>
				</div>
			</div>
		</div>
	);
};
