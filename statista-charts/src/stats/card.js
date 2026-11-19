/**
 * One editable stat card: an icon, a short label, the figure, and the optional
 * change line and note. Everything is a plain text field.
 */
import { Button } from '@wordpress/components';
import { __ } from '@wordpress/i18n';

import { percentOf } from './percent';

const NEXT_TREND = { none: 'up', up: 'down', down: 'none' };

const TREND_ICON = { none: 'minus', up: 'arrow-up-alt', down: 'arrow-down-alt' };

export default function Card( { card, color, showBar, onChange, onRemove } ) {
	const update = ( changes ) => onChange( { ...card, ...changes } );
	const percent = percentOf( card.value );

	return (
		<div className="ss-card ss-card--editing" style={ { '--ss-color': color } }>
			<div className="ss-card__toolbar">
				<Button
					size="small"
					icon={ TREND_ICON[ card.trend ] || 'minus' }
					label={ __( 'Change direction: up, down or none', 'statista-charts' ) }
					showTooltip
					onClick={ () => update( { trend: NEXT_TREND[ card.trend ] || 'up' } ) }
				/>
				<Button
					size="small"
					icon="no-alt"
					label={ __( 'Remove card', 'statista-charts' ) }
					showTooltip
					onClick={ onRemove }
				/>
			</div>

			<input
				type="text"
				className="ss-card__icon sc-input"
				value={ card.icon }
				placeholder="📊"
				aria-label={ __( 'Icon', 'statista-charts' ) }
				onChange={ ( event ) => update( { icon: event.target.value } ) }
			/>

			<input
				type="text"
				className="ss-card__label sc-input"
				value={ card.label }
				placeholder={ __( 'What it measures', 'statista-charts' ) }
				aria-label={ __( 'What it measures', 'statista-charts' ) }
				onChange={ ( event ) => update( { label: event.target.value } ) }
			/>

			<input
				type="text"
				dir="ltr"
				className="ss-card__value sc-input"
				value={ card.value }
				placeholder="75%"
				aria-label={ __( 'Figure', 'statista-charts' ) }
				onChange={ ( event ) => update( { value: event.target.value } ) }
			/>

			<span className={ `ss-card__delta ss-trend-${ card.trend }` }>
				<span className="ss-card__arrow" aria-hidden="true">
					{ card.trend === 'up' && '▲' }
					{ card.trend === 'down' && '▼' }
				</span>
				<input
					type="text"
					dir="ltr"
					className="sc-input"
					value={ card.delta }
					placeholder={ __( '+3.2%', 'statista-charts' ) }
					aria-label={ __( 'Change', 'statista-charts' ) }
					onChange={ ( event ) => update( { delta: event.target.value } ) }
				/>
			</span>

			{ showBar && percent !== null && (
				<span className="ss-card__bar">
					<span className="ss-card__bar-fill" style={ { width: `${ percent }%` } } />
				</span>
			) }

			<input
				type="text"
				className="ss-card__note sc-input"
				value={ card.note }
				placeholder={ __( 'A short note (optional)', 'statista-charts' ) }
				aria-label={ __( 'Note', 'statista-charts' ) }
				onChange={ ( event ) => update( { note: event.target.value } ) }
			/>
		</div>
	);
}
