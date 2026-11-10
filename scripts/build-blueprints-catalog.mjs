#!/usr/bin/env node
/**
 * Proof of concept. Builds the /blueprints catalog from the `blueprints/` folder of Automattic/studio
 * (one folder per Blueprint: blueprint.json, meta.json, a screenshot, and any bundled resources), the way
 * build-docs-manifest.mjs stages the docs:
 *
 *   wp-content/plugins/studio-blueprints/catalog/
 *     catalog.json      every Blueprint, with the revision it was built from
 *     images/<slug>.*   the screenshots
 *     bundles/<slug>.zip   the Blueprint and its bundled resources, for a folder that has any
 *
 *   STUDIO_BLUEPRINTS_REF=<branch-or-sha>   pin a revision (default: add/blueprints-catalog-poc, until it lands on trunk)
 *   STUDIO_BLUEPRINTS_SOURCE=<path>         use a local checkout of Automattic/studio instead of cloning
 *
 * The output is committed for the proof of concept, so the branch runs without the other repository; like
 * the docs, it would be build output.
 */
import { execFileSync } from 'node:child_process';
import { cp, mkdir, mkdtemp, readdir, readFile, rm, writeFile } from 'node:fs/promises';
import { existsSync } from 'node:fs';
import os from 'node:os';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const SOURCE = {
	repo: 'https://github.com/Automattic/studio.git',
	ref: process.env.STUDIO_BLUEPRINTS_REF || 'add/blueprints-catalog-poc',
	path: 'blueprints',
};
const USE_CASES = [ 'business', 'blog', 'portfolio', 'store', 'events', 'development' ];
const OUT = path.resolve( path.dirname( fileURLToPath( import.meta.url ) ), '../wp-content/plugins/studio-blueprints/catalog' );

async function checkout() {
	if ( process.env.STUDIO_BLUEPRINTS_SOURCE ) {
		const dir = path.resolve( process.env.STUDIO_BLUEPRINTS_SOURCE );
		let commit = 'local';
		try {
			commit = execFileSync( 'git', [ 'rev-parse', 'HEAD' ], { cwd: dir, stdio: [ 'ignore', 'pipe', 'ignore' ] } ).toString().trim();
		} catch {}
		return { dir: path.join( dir, SOURCE.path ), commit, cleanup: async () => {} };
	}
	const cacheDir = await mkdtemp( path.join( os.tmpdir(), 'studio-blueprints-' ) );
	const git = ( ...args ) => execFileSync( 'git', args, { cwd: cacheDir, stdio: [ 'ignore', 'ignore', 'inherit' ] } );
	git( 'init', '-q' );
	git( 'remote', 'add', 'origin', SOURCE.repo );
	git( 'sparse-checkout', 'set', SOURCE.path );
	git( 'fetch', '-q', '--depth', '1', 'origin', SOURCE.ref );
	git( 'checkout', '-q', 'FETCH_HEAD' );
	const commit = execFileSync( 'git', [ 'rev-parse', 'HEAD' ], { cwd: cacheDir } ).toString().trim();
	return { dir: path.join( cacheDir, SOURCE.path ), commit, cleanup: () => rm( cacheDir, { recursive: true, force: true } ) };
}

const readJson = async ( file ) => JSON.parse( await readFile( file, 'utf8' ) );

const source = await checkout();
const slugs = await readJson( path.join( source.dir, 'index.json' ) );

await rm( OUT, { recursive: true, force: true } );
await mkdir( path.join( OUT, 'images' ), { recursive: true } );
await mkdir( path.join( OUT, 'bundles' ), { recursive: true } );

const catalog = [];
for ( const slug of slugs ) {
	if ( ! /^[a-z0-9]+(-[a-z0-9]+)*$/.test( slug ) ) {
		throw new Error( `${ slug }: a Blueprint folder is lowercase letters, digits and hyphens` );
	}
	const dir = path.join( source.dir, slug );
	const meta = await readJson( path.join( dir, 'meta.json' ) );
	const blueprint = await readJson( path.join( dir, 'blueprint.json' ) );
	if ( ! meta.title ) {
		throw new Error( `${ slug }: meta.json needs a title` );
	}
	const unknown = ( meta.useCases ?? [] ).filter( ( useCase ) => ! USE_CASES.includes( useCase ) );
	if ( unknown.length ) {
		throw new Error( `${ slug }: unknown use case ${ unknown.join( ', ' ) }` );
	}

	const files = ( await readdir( dir, { recursive: true, withFileTypes: true } ) )
		.filter( ( entry ) => entry.isFile() && entry.name !== '.DS_Store' )
		.map( ( entry ) => path.relative( dir, path.join( entry.parentPath, entry.name ) ) );
	const screenshot = files.find( ( file ) => /^screenshot\.(jpe?g|png|webp)$/.test( file ) );
	const resources = files.filter( ( file ) => ! [ 'blueprint.json', 'meta.json', screenshot ].includes( file ) );

	let image = null;
	if ( screenshot ) {
		image = slug + path.extname( screenshot );
		await cp( path.join( dir, screenshot ), path.join( OUT, 'images', image ) );
	}

	// A folder with resources becomes a bundle: the Blueprint and what it refers to, in one archive.
	let bundle = null;
	if ( resources.length ) {
		if ( meta.bundleUrl ) {
			throw new Error( `${ slug }: has both bundled files and a bundleUrl` );
		}
		bundle = `${ slug }.zip`;
		execFileSync( 'zip', [ '-q', '-X', '-D', path.join( OUT, 'bundles', bundle ), 'blueprint.json', ...resources.sort() ], { cwd: dir } );
	}

	catalog.push( {
		slug,
		title: meta.title,
		excerpt: meta.description ?? '',
		image,
		useCases: meta.useCases ?? [],
		demoUrl: meta.demoUrl ?? null,
		bundle,
		bundleUrl: meta.bundleUrl ?? null,
		blueprint,
	} );
	console.log( `${ slug }${ bundle ? ' (bundle)' : '' }${ meta.bundleUrl ? ' (external bundle)' : '' }${ image ? '' : ' (no screenshot)' }` );
}

await writeFile(
	path.join( OUT, 'catalog.json' ),
	JSON.stringify( { source: { repository: 'Automattic/studio', path: SOURCE.path, ref: process.env.STUDIO_BLUEPRINTS_SOURCE ? 'local' : SOURCE.ref, commit: source.commit }, blueprints: catalog }, null, '\t' ) + '\n'
);
await source.cleanup();
console.log( `${ catalog.length } Blueprints → ${ path.relative( process.cwd(), OUT ) }/catalog.json` );
