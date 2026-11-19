/**
 * Reads a card value as a percentage. Only a value written with a percent sign
 * gets a bar, so "17.07M" stays a plain figure.
 */
import { toNumber } from '../lib/csv';

export function percentOf( value ) {
	if ( ! String( value || '' ).includes( '%' ) ) {
		return null;
	}

	const number = toNumber( value );

	if ( number === null ) {
		return null;
	}

	return Math.max( 0, Math.min( 100, number ) );
}
