/**
 * Moves markup other plugins inject into the dashboard (WhatsApp / Telegram
 * boxes, share buttons) out from under the banner and the charts.
 *
 * PHP already does this for `the_content` filters. This covers scripts that
 * insert their box after the page has loaded.
 */

const OURS =
	/(?:^|\s)(?:statista-|sd-|sc-|ss-|wp-block-)/;

const KEEP_TAGS = new Set( [
	'IMG',
	'CANVAS',
	'SVG',
	'PATH',
	'G',
	'TABLE',
	'THEAD',
	'TBODY',
	'TFOOT',
	'TR',
	'TH',
	'TD',
	'COL',
	'COLGROUP',
	'CAPTION',
	'BR',
	'SCRIPT',
	'STYLE',
	'NOSCRIPT',
] );

const FRAME =
	'.statista-chart, .statista-stats, .sd-banner, .sd-body, .sd-main, .sd-grid, .sd-panel, .sd-kpis';

function belongs( node ) {
	if ( ! ( node instanceof Element ) ) {
		return false;
	}

	if ( KEEP_TAGS.has( node.tagName ) ) {
		return true;
	}

	if ( OURS.test( node.className || '' ) ) {
		return true;
	}

	return Boolean( node.querySelector( FRAME ) );
}

function afterHost( dashboard ) {
	const next = dashboard.nextElementSibling;

	if ( next && next.classList.contains( 'statista-dashboard-after' ) ) {
		return next;
	}

	const host = document.createElement( 'div' );
	host.className = 'statista-dashboard-after';
	dashboard.insertAdjacentElement( 'afterend', host );
	return host;
}

function scrub( dashboard ) {
	if ( dashboard.dataset.statistaScrub === 'running' ) {
		return;
	}

	dashboard.dataset.statistaScrub = 'running';

	const foreign = [];

	const walk = ( parent ) => {
		Array.from( parent.children ).forEach( ( child ) => {
			if ( belongs( child ) ) {
				walk( child );
				return;
			}

			foreign.push( child );
		} );
	};

	walk( dashboard );

	if ( foreign.length ) {
		const host = afterHost( dashboard );
		foreign.forEach( ( node ) => host.appendChild( node ) );
	}

	delete dashboard.dataset.statistaScrub;
}

function boot() {
	const dashboards = document.querySelectorAll( '.statista-dashboard' );

	if ( ! dashboards.length ) {
		return;
	}

	dashboards.forEach( scrub );

	const observer = new MutationObserver( ( mutations ) => {
		const dirty = new Set();

		mutations.forEach( ( mutation ) => {
			const target =
				mutation.target instanceof Element
					? mutation.target
					: mutation.target.parentElement;
			const dashboard = target && target.closest( '.statista-dashboard' );

			if ( dashboard ) {
				dirty.add( dashboard );
			}
		} );

		dirty.forEach( scrub );
	} );

	dashboards.forEach( ( dashboard ) => {
		observer.observe( dashboard, { childList: true, subtree: true } );
	} );
}

if ( document.readyState === 'loading' ) {
	document.addEventListener( 'DOMContentLoaded', boot );
} else {
	boot();
}
