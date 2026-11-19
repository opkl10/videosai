/**
 * One editable KPI card. Plain short text fields, nothing else.
 */
import { Button } from '@wordpress/components';
import { __ } from '@wordpress/i18n';

export default function KpiCard( { kpi, onChange, onRemove } ) {
	const isSplit = kpi.value2 !== '';
	const update = ( changes ) => onChange( { ...kpi, ...changes } );

	return (
		<div className={ `sd-kpi${ isSplit ? ' sd-kpi--split' : '' } sd-kpi--editing` }>
			<div className="sd-kpi__toolbar">
				<Button
					size="small"
					icon={ isSplit ? 'minus' : 'plus-alt2' }
					label={ isSplit ? __( 'Back to a single value', 'statista-charts' ) : __( 'Split into two values', 'statista-charts' ) }
					showTooltip
					onClick={ () => update( isSplit ? { value2: '', icon2: '' } : { value2: '25%', icon2: '🟩' } ) }
				/>
				<Button
					size="small"
					icon="no-alt"
					label={ __( 'Remove card', 'statista-charts' ) }
					showTooltip
					onClick={ onRemove }
				/>
			</div>

			{ ! isSplit && (
				<input
					type="text"
					className="sd-kpi__icon sd-input"
					value={ kpi.icon }
					placeholder="📈"
					aria-label={ __( 'Icon', 'statista-charts' ) }
					onChange={ ( event ) => update( { icon: event.target.value } ) }
				/>
			) }

			<input
				type="text"
				className="sd-kpi__label sd-input"
				value={ kpi.label }
				placeholder={ __( 'Metric name', 'statista-charts' ) }
				aria-label={ __( 'Metric name', 'statista-charts' ) }
				onChange={ ( event ) => update( { label: event.target.value } ) }
			/>

			<div className="sd-kpi__values">
				<span className="sd-kpi__value">
					{ isSplit && (
						<input
							type="text"
							className="sd-kpi__badge sd-input"
							value={ kpi.icon }
							placeholder="🟦"
							aria-label={ __( 'Icon', 'statista-charts' ) }
							onChange={ ( event ) => update( { icon: event.target.value } ) }
						/>
					) }
					<input
						type="text"
						dir="ltr"
						className="sd-kpi__number sd-input"
						value={ kpi.value }
						placeholder="75%"
						aria-label={ __( 'Value', 'statista-charts' ) }
						onChange={ ( event ) => update( { value: event.target.value } ) }
					/>
				</span>

				{ isSplit && (
					<span className="sd-kpi__value">
						<input
							type="text"
							className="sd-kpi__badge sd-input"
							value={ kpi.icon2 }
							placeholder="🟩"
							aria-label={ __( 'Second icon', 'statista-charts' ) }
							onChange={ ( event ) => update( { icon2: event.target.value } ) }
						/>
						<input
							type="text"
							dir="ltr"
							className="sd-kpi__number sd-input"
							value={ kpi.value2 }
							placeholder="25%"
							aria-label={ __( 'Second value', 'statista-charts' ) }
							onChange={ ( event ) => update( { value2: event.target.value } ) }
						/>
					</span>
				) }
			</div>
		</div>
	);
}
