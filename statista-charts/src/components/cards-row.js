/**
 * The editable row of stat cards that can sit above a chart.
 */
import { Button } from '@wordpress/components';
import { __ } from '@wordpress/i18n';

import Card from '../stats/card';

const EMPTY_CARD = { icon: '', label: '', value: '', delta: '', note: '', trend: 'none' };

export function exampleCards() {
	return [
		{ icon: '📈', label: __( 'Conversion rate', 'statista-charts' ), value: '27.4%', delta: '+3.2%', note: '', trend: 'up' },
		{ icon: '👥', label: __( 'Visitors', 'statista-charts' ), value: '18.2K', delta: '+6%', note: '', trend: 'up' },
		{ icon: '🛒', label: __( 'Cart abandonment', 'statista-charts' ), value: '68%', delta: '-1.5%', note: '', trend: 'down' },
	];
}

export default function CardsRow( { cards, colors, theme, showBar, isSelected, onChange } ) {
	if ( ! isSelected && cards.length === 0 ) {
		return null;
	}

	return (
		<div className={ `statista-stats ss-editor sc-theme-${ theme } sc-cards-inline` }>
			{ cards.map( ( card, index ) => (
				<Card
					key={ index }
					card={ card }
					color={ colors[ index % colors.length ] }
					showBar={ showBar }
					onChange={ ( next ) => onChange( cards.map( ( item, i ) => ( i === index ? next : item ) ) ) }
					onRemove={ () => onChange( cards.filter( ( item, i ) => i !== index ) ) }
				/>
			) ) }

			{ isSelected && (
				<Button
					className="ss-card ss-card--add"
					icon="plus-alt2"
					onClick={ () => onChange( [ ...cards, { ...EMPTY_CARD } ] ) }
				>
					{ __( 'Add a card', 'statista-charts' ) }
				</Button>
			) }
		</div>
	);
}
