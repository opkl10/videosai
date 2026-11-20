/**
 * Paste-CSV panel. Parsing happens on the server (quotes, delimiters, Excel BOM),
 * with a local fallback when the request cannot be made.
 */
import { Button, Notice, TextareaControl } from '@wordpress/components';
import { useState } from '@wordpress/element';
import apiFetch from '@wordpress/api-fetch';
import { __ } from '@wordpress/i18n';

import { parseCsv, toCsv } from '../lib/csv';

export default function CsvImport( { labels, series, onImport } ) {
	const [ text, setText ] = useState( '' );
	const [ busy, setBusy ] = useState( false );
	const [ notice, setNotice ] = useState( null );

	const apply = ( parsed ) => {
		if ( ! parsed || ! parsed.labels.length || ! parsed.series.length ) {
			setNotice( {
				status: 'warning',
				text: __( 'No rows were found. The first line must hold the column names.', 'statista-charts' ),
			} );
			return;
		}

		onImport( parsed );
		setNotice( {
			status: 'success',
			/* translators: %d: number of imported rows. */
			text: __( 'Data imported.', 'statista-charts' ),
		} );
	};

	const importCsv = async () => {
		if ( ! text.trim() ) {
			return;
		}

		setBusy( true );
		setNotice( null );

		try {
			const parsed = await apiFetch( {
				path: '/statista-charts/v1/parse-csv',
				method: 'POST',
				data: { csv: text },
			} );

			apply( parsed );
		} catch ( error ) {
			apply( parseCsv( text ) );
		} finally {
			setBusy( false );
		}
	};

	return (
		<div className="sc-csv-import">
			<TextareaControl
				__nextHasNoMarginBottom
				label={ __( 'Paste CSV', 'statista-charts' ) }
				help={ __( 'A name column (Title, Name, …) labels the chart. Only numeric columns become series — Category, Year, Source and similar are skipped.', 'statista-charts' ) }
				value={ text }
				rows={ 6 }
				onChange={ setText }
				placeholder={ 'Title,Revenue,Category\nItem A,120,Game\nItem B,180,Movie' }
			/>

			<div className="sc-csv-import__actions">
				<Button variant="primary" size="compact" isBusy={ busy } disabled={ busy || ! text.trim() } onClick={ importCsv }>
					{ __( 'Import data', 'statista-charts' ) }
				</Button>
				<Button
					variant="tertiary"
					size="compact"
					onClick={ () => {
						setText( toCsv( labels, series ) );
						setNotice( null );
					} }
				>
					{ __( 'Fill from current table', 'statista-charts' ) }
				</Button>
			</div>

			{ notice && (
				<Notice status={ notice.status } isDismissible onRemove={ () => setNotice( null ) }>
					{ notice.text }
				</Notice>
			) }
		</div>
	);
}
