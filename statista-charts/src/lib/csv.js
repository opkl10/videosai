/**
 * CSV helpers for the editor.
 *
 * The REST endpoint does the real parsing; this is the offline fallback and the
 * "copy the current table as CSV" helper. Column classification matches
 * Config::parse_csv() in PHP: a text column labels the axis, numeric columns
 * become series, and dimension headers (Category, Year, Source, …) are skipped.
 */

const DELIMITERS = [ ',', '\t', ';', '|' ];

const LABEL_HEADERS = new Set( [
	'title',
	'name',
	'label',
	'product',
	'game',
	'item',
	'movie',
	'album',
	'app',
	'כותרת',
	'שם',
	'מוצר',
	'משחק',
] );

const DIMENSION_HEADERS = new Set( [
	'category',
	'categories',
	'type',
	'types',
	'genre',
	'source',
	'datasource',
	'timeframe',
	'period',
	'date',
	'year',
	'releaseyear',
	'releasedate',
	'id',
	'uid',
	'url',
	'link',
	'notes',
	'note',
	'comment',
	'platform',
	'region',
	'country',
	'status',
	'format',
	'קטגוריה',
	'סוג',
	'מקור',
	'שנה',
	'תאריך',
	'תקופה',
] );

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

function normalizeHeader( name ) {
	return String( name || '' )
		.trim()
		.toLowerCase()
		.replace( /[ \-_]/g, '' );
}

function headerIsDimension( name ) {
	if ( DIMENSION_HEADERS.has( normalizeHeader( name ) ) ) {
		return true;
	}

	return /(^|[_\s-])(category|categories|source|timeframe|genre|platform|year|date|id|type)s?$/i.test( name );
}

function columnMostlyNumeric( values ) {
	let numeric = 0;
	let filled = 0;

	( values || [] ).forEach( ( value ) => {
		if ( String( value ?? '' ).trim() === '' ) {
			return;
		}

		filled++;

		if ( toNumber( value ) !== null ) {
			numeric++;
		}
	} );

	return filled > 0 && numeric / filled >= 0.7;
}

function isMeasure( name, values ) {
	return ! headerIsDimension( name ) && columnMostlyNumeric( values );
}

function allEmpty( data ) {
	return data.every( ( value ) => value === null );
}

function labelIndex( header, columns ) {
	const preferred = header.findIndex( ( name ) => LABEL_HEADERS.has( normalizeHeader( name ) ) );

	if ( preferred !== -1 ) {
		return preferred;
	}

	const text = header.findIndex( ( name, index ) => ! columnMostlyNumeric( columns[ index ] || [] ) );

	return text === -1 ? 0 : text;
}

export function usableSeries( series ) {
	const list = Array.isArray( series ) ? series : [];
	const kept = list.filter( ( set ) => {
		const label = set && set.label ? String( set.label ) : '';
		const data = set && Array.isArray( set.data ) ? set.data : [];

		if ( headerIsDimension( label ) ) {
			return false;
		}

		return data.some( ( value ) => value !== null && value !== undefined && value !== '' );
	} );

	return kept.length ? kept : list;
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
	const header = ( rows.shift() || [] ).map( ( cell ) => String( cell || '' ).trim() );
	let width = header.length;

	rows.forEach( ( row ) => {
		width = Math.max( width, row.length );
	} );

	const columns = [];

	for ( let index = 0; index < width; index++ ) {
		columns[ index ] = rows.map( ( row ) => ( row[ index ] == null ? '' : String( row[ index ] ) ) );
	}

	const labelsAt = labelIndex( header, columns );
	const labels = columns[ labelsAt ] || [];
	let series = [];

	header.forEach( ( name, index ) => {
		if ( index === labelsAt || ! columns[ index ] ) {
			return;
		}

		if ( ! isMeasure( name, columns[ index ] ) ) {
			return;
		}

		const data = columns[ index ].map( toNumber );

		if ( allEmpty( data ) ) {
			return;
		}

		series.push( { label: name, data } );
	} );

	if ( ! series.length ) {
		series = header
			.map( ( name, index ) => {
				if ( index === labelsAt || ! columns[ index ] ) {
					return null;
				}

				return { label: name, data: columns[ index ].map( toNumber ) };
			} )
			.filter( Boolean );
	}

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
