import React from 'react';
import { Composition } from 'remotion';
import { Main } from './Main';
import timeline from './timeline.json';

export const Root: React.FC = () => (
	<Composition id="Main" component={ Main } durationInFrames={ timeline.totalFrames } fps={ 30 } width={ 2560 } height={ 1440 } />
);
