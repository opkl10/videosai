/**
 * One editable KPI card. Plain short text fields, nothing else.
 */
import { Button } from '@wordpress/components';
import { __ } from '@wordpress/i18n';

/**
 * A swatch is a colour emoji or symbol. Anything with a letter or digit is a
 * platform name and should render at normal text size.
 *
 * @param {string} value
 * @return {boolean}
 */
export function isTextMark( value ) {
	return /[\p{L}\p{N}]/u.test( String( value || '' ) );
}

export default function KpiCard( {
	kpi,
	onChange,
	onRemove,
	dragging = false,
	dropBefore = false,
	dropAfter = false,
	layout = 'row',
	rtl = false,
	canMoveEarlier = false,
	canMoveLater = false,
	onMoveEarlier,
	onMoveLater,
	onDragHandlePointerDown,
	onDragHandlePointerMove,
	onDragHandlePointerUp,
	onDragHandlePointerCancel,
} ) {
	const isSplit = kpi.value2 !== '';
	const update = ( changes ) => onChange( { ...kpi, ...changes } );
	const iconIsText = isTextMark( kpi.icon );
	const icon2IsText = isTextMark( kpi.icon2 );

	const className = [
		'sd-kpi',
		isSplit ? 'sd-kpi--split' : '',
		'sd-kpi--editing',
		dragging ? 'sd-kpi--dragging' : '',
		dropBefore ? 'sd-kpi--drop-before' : '',
		dropAfter ? 'sd-kpi--drop-after' : '',
	]
		.filter( Boolean )
		.join( ' ' );

	return (
		<div className={ className }>
			<button
				type="button"
				className="sd-kpi__drag"
				aria-label={ __( 'Drag to reorder', 'statista-charts' ) }
				onPointerDown={ onDragHandlePointerDown }
				onPointerMove={ onDragHandlePointerMove }
				onPointerUp={ onDragHandlePointerUp }
				onPointerCancel={ onDragHandlePointerCancel }
			>
				<span className="sd-kpi__drag-dots" aria-hidden="true" />
			</button>

			<div className="sd-kpi__toolbar">
				<Button
					size="small"
					icon={ layout === 'column' ? 'arrow-up-alt2' : ( rtl ? 'arrow-right-alt2' : 'arrow-left-alt2' ) }
					label={ __( 'Move earlier', 'statista-charts' ) }
					showTooltip
					disabled={ ! canMoveEarlier }
					onClick={ onMoveEarlier }
				/>
				<Button
					size="small"
					icon={ layout === 'column' ? 'arrow-down-alt2' : ( rtl ? 'arrow-left-alt2' : 'arrow-right-alt2' ) }
					label={ __( 'Move later', 'statista-charts' ) }
					showTooltip
					disabled={ ! canMoveLater }
					onClick={ onMoveLater }
				/>
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
							className={ `sd-kpi__badge sd-input${ iconIsText ? ' sd-kpi__badge--text' : '' }` }
							value={ kpi.icon }
							placeholder={ __( 'iOS', 'statista-charts' ) }
							aria-label={ __( 'Platform name or icon', 'statista-charts' ) }
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
							className={ `sd-kpi__badge sd-input${ icon2IsText ? ' sd-kpi__badge--text' : '' }` }
							value={ kpi.icon2 }
							placeholder={ __( 'Android', 'statista-charts' ) }
							aria-label={ __( 'Second platform name or icon', 'statista-charts' ) }
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
