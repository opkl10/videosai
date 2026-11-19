/**
 * Front-end runtime: upgrades every server rendered chart figure into a Chart.js canvas.
 *
 * Loaded only on pages that actually contain a chart.
 */
import { createChart } from './lib/chart-factory';

const RENDERED = new WeakMap();

function readConfig( figure ) {
	try {
		return JSON.parse( figure.getAttribute( 'data-statista-chart' ) || '{}' );
	} catch ( error ) {
		return null;
	}
}

function render( figure ) {
	if ( RENDERED.has( figure ) ) {
		return;
	}

	const config = readConfig( figure );
	const canvas = figure.querySelector( '.sc-canvas' );

	if ( ! config || ! canvas ) {
		return;
	}

	const chart = createChart( canvas, config );

	if ( ! chart ) {
		return;
	}

	RENDERED.set( figure, chart );
	figure.classList.add( 'is-ready' );

	const loader = figure.querySelector( '.sc-loading' );
	if ( loader ) {
		loader.remove();
	}

	if ( config.theme === 'auto' && typeof window.matchMedia === 'function' ) {
		const query = window.matchMedia( '(prefers-color-scheme: dark)' );
		const repaint = () => {
			chart.destroy();
			RENDERED.delete( figure );
			render( figure );
		};

		if ( typeof query.addEventListener === 'function' ) {
			query.addEventListener( 'change', repaint, { once: true } );
		}
	}
}

function boot() {
	const figures = document.querySelectorAll( '.statista-chart:not(.sc-chart-hidden)' );

	if ( ! figures.length ) {
		return;
	}

	if ( typeof window.IntersectionObserver !== 'function' ) {
		figures.forEach( render );
		return;
	}

	const observer = new window.IntersectionObserver(
		( entries ) => {
			entries.forEach( ( entry ) => {
				if ( entry.isIntersecting ) {
					render( entry.target );
					observer.unobserve( entry.target );
				}
			} );
		},
		{ rootMargin: '200px 0px' }
	);

	figures.forEach( ( figure ) => observer.observe( figure ) );
}

if ( document.readyState === 'loading' ) {
	document.addEventListener( 'DOMContentLoaded', boot );
} else {
	boot();
}

document.addEventListener( 'statista-charts:refresh', boot );
