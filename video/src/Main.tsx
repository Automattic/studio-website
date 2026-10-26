import React from 'react';
import { AbsoluteFill, Audio, Sequence, staticFile } from 'remotion';
import timeline from './timeline.json';
import { Agent } from './scenes/Agent';
import { Editor } from './scenes/Editor';
import { End } from './scenes/End';
import { Intro, Typing } from './scenes/Intro';
import { Reveal } from './scenes/Reveal';
import { Montage, Wall } from './scenes/Showcase';
import { useFonts } from './theme';

type Scene = { id: string; from: number; frames: number };
const at = ( id: string ) => ( timeline.scenes as Scene[] ).find( ( s ) => s.id === id )!;

export const Main: React.FC = () => {
	useFonts();
	const seq = ( id: string, node: React.ReactNode ) => (
		<Sequence key={ id } from={ at( id ).from } durationInFrames={ at( id ).frames } premountFor={ 30 }>
			{ node }
		</Sequence>
	);
	return (
		<AbsoluteFill style={ { background: '#fff' } }>
			{ /* Scenes are laid out at 1920 × 1080 and scaled up to the 2560 × 1440 frame. */ }
			<div style={ { position: 'absolute', width: 1920, height: 1080, transform: 'scale(1.3333333)', transformOrigin: '0 0' } }>
			{ seq( 'intro', <Intro /> ) }
			{ seq( 'typing', <Typing /> ) }
			{ seq( 'agent', <Agent /> ) }
			{ seq( 'reveal', <Reveal /> ) }
			{ seq( 'montage', <Montage /> ) }
			{ seq( 'wall', <Wall /> ) }
			{ seq( 'editor', <Editor /> ) }
			{ seq( 'end', <End /> ) }
			</div>
			<Audio src={ staticFile( 'audio/soundtrack.wav' ) } />
		</AbsoluteFill>
	);
};
