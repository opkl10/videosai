/**
 * Spreadsheet-like editor for the chart data.
 */
import { Button } from '@wordpress/components';
import { __, sprintf } from '@wordpress/i18n';

import { toNumber } from '../lib/csv';

function emptyRow( count ) {
	return new Array( count ).fill( null );
}

export default function DataTable( { labels, series, onChange } ) {
	const seriesCount = series.length;

	const update = ( nextLabels, nextSeries ) => onChange( { labels: nextLabels, series: nextSeries } );

	const setLabel = ( row, value ) => {
		const next = [ ...labels ];
		next[ row ] = value;
		update( next, series );
	};

	const setSeriesName = ( column, value ) => {
		const next = series.map( ( set, index ) => ( index === column ? { ...set, label: value } : set ) );
		update( labels, next );
	};

	const setCell = ( row, column, value ) => {
		const next = series.map( ( set, index ) => {
			if ( index !== column ) {
				return set;
			}

			const data = [ ...set.data ];
			data[ row ] = value === '' ? null : toNumber( value );

			return { ...set, data };
		} );

		update( labels, next );
	};

	const addRow = () => {
		update(
			[ ...labels, '' ],
			series.map( ( set ) => ( { ...set, data: [ ...set.data, null ] } ) )
		);
	};

	const removeRow = ( row ) => {
		update(
			labels.filter( ( label, index ) => index !== row ),
			series.map( ( set ) => ( { ...set, data: set.data.filter( ( value, index ) => index !== row ) } ) )
		);
	};

	const addColumn = () => {
		update( labels, [
			...series,
			{
				/* translators: %d: series number. */
				label: sprintf( __( 'Series %d', 'statista-charts' ), seriesCount + 1 ),
				data: emptyRow( labels.length ),
			},
		] );
	};

	const removeColumn = ( column ) => {
		update(
			labels,
			series.filter( ( set, index ) => index !== column )
		);
	};

	return (
		<div className="sc-data-editor">
			<div className="sc-data-editor__scroll">
				<table className="sc-data-editor__table">
					<thead>
						<tr>
							<th className="sc-data-editor__corner">{ __( 'Category', 'statista-charts' ) }</th>
							{ series.map( ( set, column ) => (
								<th key={ column }>
									<div className="sc-data-editor__head">
										<input
											type="text"
											className="sc-data-editor__input"
											value={ set.label }
											placeholder={ __( 'Series name', 'statista-charts' ) }
											aria-label={ __( 'Series name', 'statista-charts' ) }
											onChange={ ( event ) => setSeriesName( column, event.target.value ) }
										/>
										<Button
											icon="no-alt"
											size="small"
											className="sc-data-editor__remove"
											label={ __( 'Remove column', 'statista-charts' ) }
											showTooltip
											disabled={ seriesCount <= 1 }
											onClick={ () => removeColumn( column ) }
										/>
									</div>
								</th>
							) ) }
							<th className="sc-data-editor__add-col">
								<Button
									icon="plus-alt2"
									size="small"
									label={ __( 'Add column', 'statista-charts' ) }
									showTooltip
									onClick={ addColumn }
								/>
							</th>
						</tr>
					</thead>
					<tbody>
						{ labels.map( ( label, row ) => (
							<tr key={ row }>
								<th scope="row">
									<input
										type="text"
										className="sc-data-editor__input"
										value={ label }
										placeholder={ __( 'Label', 'statista-charts' ) }
										aria-label={ __( 'Category label', 'statista-charts' ) }
										onChange={ ( event ) => setLabel( row, event.target.value ) }
									/>
								</th>
								{ series.map( ( set, column ) => (
									<td key={ column }>
										<input
											type="text"
											inputMode="decimal"
											className="sc-data-editor__input sc-data-editor__input--number"
											value={ set.data[ row ] === null || set.data[ row ] === undefined ? '' : set.data[ row ] }
											placeholder="0"
											aria-label={ __( 'Value', 'statista-charts' ) }
											onChange={ ( event ) => setCell( row, column, event.target.value ) }
										/>
									</td>
								) ) }
								<td className="sc-data-editor__row-actions">
									<Button
										icon="trash"
										size="small"
										label={ __( 'Remove row', 'statista-charts' ) }
										showTooltip
										disabled={ labels.length <= 1 }
										onClick={ () => removeRow( row ) }
									/>
								</td>
							</tr>
						) ) }
					</tbody>
				</table>
			</div>

			<div className="sc-data-editor__actions">
				<Button variant="secondary" size="compact" icon="plus-alt2" onClick={ addRow }>
					{ __( 'Add row', 'statista-charts' ) }
				</Button>
				<Button variant="secondary" size="compact" icon="plus-alt2" onClick={ addColumn }>
					{ __( 'Add column', 'statista-charts' ) }
				</Button>
			</div>
		</div>
	);
}
