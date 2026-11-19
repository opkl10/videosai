/**
 * CSV helpers for the editor.
 *
 * The REST endpoint does the real parsing; this is the offline fallback and the
 * "copy the current table as CSV" helper.
 */

const DELIMITERS = [ ',', '\t', ';', '|' ];

function detectDelimiter( line ) {
	let best = ',';
	let bestCount = 0;

	DELIMITERS.forEach( ( delimiter ) => {
		const count = line.split( delimiter ).length - 1;

		if ( count > bestCount ) {
			best = delimiter;
			bestCount = count;
		}
	} );

	return best;
}

function splitLine( line, delimiter ) {
	const cells = [];
	let current = '';
	let quoted = false;

	for ( let i = 0; i < line.length; i++ ) {
		const char = line[ i ];

		if ( quoted ) {
			if ( char === '"' && line[ i + 1 ] === '"' ) {
				current += '"';
				i++;
			} else if ( char === '"' ) {
				quoted = false;
			} else {
				current += char;
			}
		} else if ( char === '"' ) {
			quoted = true;
		} else if ( char === delimiter ) {
			cells.push( current.trim() );
			current = '';
		} else {
			current += char;
		}
	}

	cells.push( current.trim() );

	return cells;
}

export function parseCsv( text ) {
	const lines = String( text )
		.replace( /^\uFEFF/, '' )
		.replace( /\r\n?/g, '\n' )
		.split( '\n' )
		.filter( ( line ) => line.trim() !== '' );

	if ( ! lines.length ) {
		return { labels: [], series: [] };
	}

	const delimiter = detectDelimiter( lines[ 0 ] );
	const rows = lines.map( ( line ) => splitLine( line, delimiter ) );
	const header = rows.shift();
	const names = header.slice( 1 );

	const labels = rows.map( ( row ) => row[ 0 ] || '' );
	const series = names.map( ( name, index ) => ( {
		label: name,
		data: rows.map( ( row ) => toNumber( row[ index + 1 ] ) ),
	} ) );

	return { labels, series };
}

export function toNumber( value ) {
	if ( typeof value === 'number' ) {
		return Number.isFinite( value ) ? value : null;
	}

	if ( typeof value !== 'string' ) {
		return null;
	}

	let normalized = value.trim().replace( /[\s\u00a0%]/g, '' ).replace( /[^0-9,.+-]/g, '' );

	if ( ! normalized ) {
		return null;
	}

	const lastComma = normalized.lastIndexOf( ',' );
	const lastDot = normalized.lastIndexOf( '.' );

	if ( lastComma > -1 && lastDot > -1 ) {
		const decimal = lastComma > lastDot ? ',' : '.';
		const thousands = decimal === ',' ? '.' : ',';
		normalized = normalized.split( thousands ).join( '' ).replace( decimal, '.' );
	} else if ( lastComma > -1 ) {
		const decimals = normalized.length - lastComma - 1;
		normalized = decimals === 3 ? normalized.split( ',' ).join( '' ) : normalized.replace( ',', '.' );
	}

	const number = Number( normalized );

	return Number.isFinite( number ) ? number : null;
}

export function toCsv( labels, series ) {
	const escape = ( cell ) => {
		const text = cell === null || cell === undefined ? '' : String( cell );

		return /[",\n]/.test( text ) ? `"${ text.replace( /"/g, '""' ) }"` : text;
	};

	const header = [ '', ...series.map( ( set ) => set.label || '' ) ].map( escape ).join( ',' );
	const rows = labels.map( ( label, row ) =>
		[ label, ...series.map( ( set ) => ( set.data[ row ] === null || set.data[ row ] === undefined ? '' : set.data[ row ] ) ) ]
			.map( escape )
			.join( ',' )
	);

	return [ header, ...rows ].join( '\n' );
}
