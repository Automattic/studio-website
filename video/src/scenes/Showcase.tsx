import React from 'react';
import { AbsoluteFill, Img, interpolate, spring, staticFile, useCurrentFrame, useVideoConfig } from 'remotion';
import sites from '../sites.json';
import timeline from '../timeline.json';
import { BAR, Browser, C, easeInOut, MONO, ramp, rand, SANS } from '../theme';

type Site = { slug: string; name: string; kind: string; model: string };
const bySlug = Object.fromEntries( ( sites as Site[] ).map( ( s ) => [ s.slug, s ] ) );

export const SHOT = 30;
const GROUPS = timeline.groups as Array< [ string, string[] ] >;
const SHOTS = GROUPS.flatMap( ( [ title, slugs ] ) => slugs.filter( ( s ) => bySlug[ s ] ).map( ( slug ) => ( { title, site: bySlug[ slug ] } ) ) );
export const MONTAGE_FRAMES = SHOTS.length * SHOT;

/** 24–40 s: one site per two beats, under a line that changes each phrase. */
export const Montage: React.FC = () => {
	const frame = useCurrentFrame();
	const { fps } = useVideoConfig();
	const i = Math.max( 0, Math.min( SHOTS.length - 1, Math.floor( ( frame - 7 ) / SHOT ) ) );
	const title = SHOTS[ i ].title;
	const titleStart = SHOTS.findIndex( ( s ) => s.title === title ) * SHOT;
	return (
		<AbsoluteFill style={ { background: C.surface } }>
			<div style={ { position: 'absolute', left: 120, top: 0, bottom: 0, width: 560, display: 'flex', flexDirection: 'column', justifyContent: 'center' } }>
				<div
					key={ title }
					style={ {
						font: `600 96px/1 ${ SANS }`,
						letterSpacing: '-0.045em',
						color: C.text,
						opacity: ramp( frame, titleStart, titleStart + 8 ),
						transform: `translateY(${ ramp( frame, titleStart, titleStart + 14, 26, 0 ) }px)`,
					} }
				>
					{ title }
				</div>
				<div style={ { marginTop: 40, minHeight: 100 } }>
					<div style={ { font: `600 34px/1.15 ${ SANS }`, color: C.text, letterSpacing: '-0.02em' } }>{ SHOTS[ i ].site.name }</div>
					<div style={ { font: `400 26px/1.4 ${ SANS }`, color: C.muted, marginTop: 8 } }>{ SHOTS[ i ].site.kind }</div>
				</div>
			</div>
			{ SHOTS.map( ( shot, k ) => {
				const start = k * SHOT;
				if ( frame < start - 2 || frame > start + SHOT + 12 ) return null;
				const s = spring( { frame: frame - start, fps, config: { damping: 22, stiffness: 160, mass: 0.8 } } );
				const push = interpolate( frame - start, [ 0, SHOT + 12 ], [ 1.0, 1.035 ] );
				return (
					<div
						key={ shot.site.slug }
						style={ {
							position: 'absolute',
							left: 700,
							top: 148,
							transform: `translateX(${ ( 1 - s ) * 1300 }px) rotate(${ ( 1 - s ) * 2 }deg)`,
							zIndex: k,
						} }
					>
						<Browser width={ 1180 } height={ 783 }>
							<Img
								src={ staticFile( `sites/${ shot.site.slug }.jpg` ) }
								style={ { width: '100%', height: '100%', objectFit: 'cover', objectPosition: 'top', transform: `scale(${ push })`, transformOrigin: '50% 0' } }
							/>
						</Browser>
					</div>
				);
			} ) }
		</AbsoluteFill>
	);
};

/** 40-ish s: the camera pulls back from one site to the whole wall. */
export const Wall: React.FC = () => {
	const frame = useCurrentFrame();
	const { fps } = useVideoConfig();
	const all = sites as Site[];
	const cols = 6;
	const rows = Math.ceil( all.length / cols );
	const tw = 300;
	const th = 188;
	const gap = 18;
	const gridW = cols * tw + ( cols - 1 ) * gap;
	const gridH = rows * th + ( rows - 1 ) * gap;
	const zoom = interpolate( frame, [ 0, 70 ], [ 3.2, 1 ], { extrapolateRight: 'clamp', easing: easeInOut } );
	const card = spring( { frame: frame - 62, fps, config: { damping: 18, stiffness: 120 } } );
	return (
		<AbsoluteFill style={ { background: C.white, alignItems: 'center', justifyContent: 'center' } }>
			<div style={ { width: gridW, height: gridH, position: 'relative', transform: `scale(${ zoom })` } }>
				{ all.map( ( s, k ) => {
					const x = ( k % cols ) * ( tw + gap );
					const y = Math.floor( k / cols ) * ( th + gap );
					const pop = spring( { frame: frame - Math.round( rand( k ) * 18 ), fps, config: { damping: 16, stiffness: 140 } } );
					return (
						<div
							key={ s.slug }
							style={ {
								position: 'absolute',
								left: x,
								top: y,
								width: tw,
								height: th,
								borderRadius: 10,
								overflow: 'hidden',
								border: `1.5px solid ${ C.rule }`,
								opacity: pop,
								transform: `scale(${ 0.85 + 0.15 * pop })`,
							} }
						>
							<Img src={ staticFile( `sites/${ s.slug }.jpg` ) } style={ { width: '100%', height: '100%', objectFit: 'cover', objectPosition: 'top' } } />
						</div>
					);
				} ) }
			</div>
			<div
				style={ {
					position: 'absolute',
					padding: '34px 50px',
					borderRadius: 22,
					background: C.white,
					border: `1.5px solid ${ C.rule }`,
					boxShadow: '0 40px 80px -30px rgba(15,18,34,0.35)',
					textAlign: 'center',
					opacity: card,
					transform: `translateY(${ ( 1 - card ) * 40 }px)`,
				} }
			>
				<div style={ { font: `600 84px/1 ${ SANS }`, letterSpacing: '-0.045em', color: C.text } }>One prompt each.</div>
				<div style={ { font: `400 26px/1.4 ${ MONO }`, color: C.muted, marginTop: 14 } }>No edits afterward.</div>
			</div>
		</AbsoluteFill>
	);
};

export const WALL_FRAMES = 2 * BAR;
