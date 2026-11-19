/**
 * The "one line per item" format: `label: value`.
 *
 * Deliberately forgiving — a colon, an equals sign, a tab, a spaced dash, plain
 * spaces or a comma all separate the label from the value, empty lines are
 * dropped, and values may carry thousands separators, a percent sign or a
 * currency symbol.
 */
import { toNumber } from './csv';

// Tried in order. Spaces come before the comma so that "January 1,200" keeps
// its thousands separator instead of splitting on it.
const SEPARATORS = [ /[:：]/g, /=/g, /\t/g, /\s[-–—]\s/g, /\s+/g, /,/g ];

const NUMBER_ONLY = /^[\d\s.,%+\-–₪$€£]+$/;

function lastMatch( line, pattern ) {
	let found = null;
	let match;

	pattern.lastIndex = 0;
	while ( ( match = pattern.exec( line ) ) !== null ) {
		found = match;

		if ( match.index === pattern.lastIndex ) {
			pattern.lastIndex++;
		}
	}

	return found;
}

function trimEdges( text ) {
	return text.replace( /^[\s,;:=–—-]+/, '' ).replace( /[\s,;:=–—-]+$/, '' );
}

function splitLine( line ) {
	for ( const pattern of SEPARATORS ) {
		const match = lastMatch( line, pattern );

		if ( match && match.index > 0 ) {
			return [ trimEdges( line.slice( 0, match.index ) ), trimEdges( line.slice( match.index + match[ 0 ].length ) ) ];
		}
	}

	return [ trimEdges( line ), '' ];
}

/**
 * Parses the text of the quick entry field.
 *
 * @param {string} text Raw text, one item per line.
 * @return {{labels: string[], data: Array<number|null>, errors: Array<{line: number, text: string}>}} Parsed data, plus the lines that hold no usable number.
 */
export function parseSimpleList( text ) {
	const labels = [];
	const data = [];
	const errors = [];

	String( text || '' )
		.replace( /\r\n?/g, '\n' )
		.split( '\n' )
		.forEach( ( rawLine, index ) => {
			const line = rawLine.trim();

			if ( ! line ) {
				return;
			}

			// A bare number keeps its position as the label.
			if ( NUMBER_ONLY.test( line ) ) {
				const only = toNumber( line );

				if ( only !== null ) {
					labels.push( String( labels.length + 1 ) );
					data.push( only );
					return;
				}
			}

			let [ label, rawValue ] = splitLine( line );
			let value = toNumber( rawValue );

			// "120 January" reads as well as "January 120".
			if ( value === null && rawValue !== '' && toNumber( label ) !== null ) {
				value = toNumber( label );
				label = rawValue;
			}

			// A line still being typed ("January:") is not an error yet; a line
			// whose value simply is not a number is.
			if ( value === null && rawValue !== '' ) {
				errors.push( { line: index + 1, text: line } );
			}

			labels.push( label );
			data.push( value );
		} );

	return { labels, data, errors };
}

/**
 * Writes chart data back into the quick entry format.
 *
 * @param {string[]}           labels Category labels.
 * @param {Array<number|null>} data   Values.
 * @return {string} One line per item.
 */
export function serializeSimpleList( labels, data ) {
	const values = data || [];

	return ( labels || [] )
		.map( ( label, index ) => {
			const value = values[ index ];

			return `${ label }: ${ value === null || value === undefined ? '' : value }`;
		} )
		.join( '\n' );
}

/**
 * True when both sides hold the same numbers under the same labels.
 *
 * @param {{labels: string[], data: Array<number|null>}} a First set.
 * @param {{labels: string[], data: Array<number|null>}} b Second set.
 * @return {boolean} Whether they match.
 */
export function isSameList( a, b ) {
	return (
		JSON.stringify( a.labels || [] ) === JSON.stringify( b.labels || [] ) &&
		JSON.stringify( a.data || [] ) === JSON.stringify( b.data || [] )
	);
}
