import React from 'react';
import { AbsoluteFill, Img, spring, staticFile, useCurrentFrame, useVideoConfig } from 'remotion';
import { Mosaic } from '../Mosaic';
import { C, MONO, ramp, SANS } from '../theme';

/** 56–66 s: the close — mosaic behind, the platform line, where to get it. */
export const End: React.FC = () => {
	const frame = useCurrentFrame();
	const { fps } = useVideoConfig();
	const s = ( at: number ) => spring( { frame: frame - at, fps, config: { damping: 18, stiffness: 120 } } );
	const rise = ( at: number ) => ( { opacity: s( at ), transform: `translateY(${ ( 1 - s( at ) ) * 34 }px)` } );
	return (
		<AbsoluteFill style={ { background: C.white } }>
			<Mosaic
				width={ 1100 }
				height={ 900 }
				start={ 0 }
				origin="bottom-left"
				seed={ 21 }
				style={ { left: -20, bottom: -20 } }
				mask="linear-gradient(to right, black 30%, transparent 90%), linear-gradient(to top, black 35%, transparent 100%)"
			/>
			<AbsoluteFill style={ { alignItems: 'flex-end', justifyContent: 'center', paddingRight: 150 } }>
				<div style={ { width: 1000 } }>
					<div style={ { display: 'flex', alignItems: 'center', gap: 18, ...rise( 8 ) } }>
						<Img src={ staticFile( 'img/studio-logo.png' ) } style={ { width: 64, height: 64, borderRadius: 14 } } />
						<div style={ { font: `600 40px/1 ${ SANS }`, letterSpacing: '-0.02em', color: C.text } }>WordPress Studio</div>
					</div>
					<div style={ { font: `600 124px/0.98 ${ SANS }`, letterSpacing: '-0.05em', color: C.text, margin: '40px 0 34px', ...rise( 18 ) } }>
						The WordPress platform for AI.
					</div>
					<div style={ { font: `400 32px/1.4 ${ SANS }`, color: C.muted, ...rise( 30 ) } }>Free and open source, on Mac, Windows and Linux.</div>
					<div style={ { display: 'flex', alignItems: 'center', gap: 22, marginTop: 46, ...rise( 42 ) } }>
						<div
							style={ {
								padding: '22px 40px',
								borderRadius: 999,
								background: C.primary,
								color: C.white,
								font: `500 30px/1 ${ SANS }`,
								boxShadow: '0 8px 24px rgb(56 88 233 / 0.28)',
							} }
						>
							Download Studio
						</div>
						<div style={ { font: `400 30px/1 ${ MONO }`, color: C.text } }>wordpress.studio</div>
					</div>
				</div>
			</AbsoluteFill>
			<AbsoluteFill style={ { background: C.white, opacity: ramp( frame, 270, 300 ) } } />
		</AbsoluteFill>
	);
};
