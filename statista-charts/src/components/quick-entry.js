/**
 * The default way to enter data: one line per item, `label: value`.
 *
 * The chart follows every keystroke, so there is nothing to confirm.
 */
import { Button, Notice, TextControl } from '@wordpress/components';
import { useEffect, useRef, useState } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';

import { isSameList, parseSimpleList, serializeSimpleList } from '../lib/simple-list';
import { getExample } from '../lib/templates';

export default function QuickEntry( { attributes, setAttributes, onOpenAdvanced } ) {
	const { type, labels, series, statValue, statLabel, prefix, suffix } = attributes;
	const seriesName = series[ 0 ] ? series[ 0 ].label : '';
	const currentData = series[ 0 ] ? series[ 0 ].data : [];

	const [ text, setText ] = useState( () => serializeSimpleList( labels, currentData ) );
	const [ errors, setErrors ] = useState( [] );
	const textareaRef = useRef( null );

	// Pick up changes made elsewhere (CSV import, the table, a template) without
	// fighting the field while it is being typed in.
	useEffect( () => {
		const typed = parseSimpleList( text );

		if ( ! isSameList( typed, { labels, data: currentData } ) ) {
			setText( serializeSimpleList( labels, currentData ) );
			setErrors( [] );
		}
		// eslint-disable-next-line react-hooks/exhaustive-deps
	}, [ JSON.stringify( labels ), JSON.stringify( currentData ) ] );

	const commit = ( value ) => {
		setText( value );

		const parsed = parseSimpleList( value );
		setErrors( parsed.errors );
		setAttributes( {
			labels: parsed.labels,
			series: [ { label: seriesName, data: parsed.data } ],
		} );
	};

	if ( type === 'stat' ) {
		return (
			<div className="sc-quick-entry">
				<div className="sc-quick-entry__stat">
					<TextControl
						__nextHasNoMarginBottom
						label={ __( 'The number', 'statista-charts' ) }
						value={ statValue }
						placeholder="27.4"
						onChange={ ( value ) => setAttributes( { statValue: value } ) }
					/>
					<TextControl
						__nextHasNoMarginBottom
						label={ __( 'What it means', 'statista-charts' ) }
						value={ statLabel }
						placeholder={ __( 'Growth versus last year', 'statista-charts' ) }
						onChange={ ( value ) => setAttributes( { statLabel: value } ) }
					/>
					<TextControl
						__nextHasNoMarginBottom
						label={ __( 'Before the number', 'statista-charts' ) }
						value={ prefix }
						placeholder="₪"
						onChange={ ( value ) => setAttributes( { prefix: value } ) }
					/>
					<TextControl
						__nextHasNoMarginBottom
						label={ __( 'After the number', 'statista-charts' ) }
						value={ suffix }
						placeholder="%"
						onChange={ ( value ) => setAttributes( { suffix: value } ) }
					/>
				</div>
			</div>
		);
	}

	if ( series.length > 1 ) {
		return (
			<div className="sc-quick-entry">
				<Notice status="info" isDismissible={ false }>
					{ __( 'This chart holds more than one series, so it is edited in the table.', 'statista-charts' ) }
				</Notice>
				<Button variant="secondary" size="compact" onClick={ onOpenAdvanced }>
					{ __( 'Open the table', 'statista-charts' ) }
				</Button>
			</div>
		);
	}

	const example = getExample( type );

	return (
		<div className="sc-quick-entry">
			<label className="sc-quick-entry__label" htmlFor="sc-quick-entry-field">
				{ __( 'Your data — one line per item', 'statista-charts' ) }
			</label>

			<textarea
				id="sc-quick-entry-field"
				ref={ textareaRef }
				className="sc-quick-entry__field"
				value={ text }
				rows={ Math.min( 12, Math.max( 5, text.split( '\n' ).length + 1 ) ) }
				spellCheck="false"
				placeholder={ example }
				onChange={ ( event ) => commit( event.target.value ) }
			/>

			<p className="sc-quick-entry__help">
				{ __( 'Write a name, then its number — for example “January: 120”. A comma, a tab or a dash work just as well.', 'statista-charts' ) }
			</p>

			<div className="sc-quick-entry__example">
				<pre className="sc-quick-entry__example-text">{ example }</pre>
				<Button variant="secondary" size="compact" onClick={ () => commit( example ) }>
					{ __( 'Fill in this example', 'statista-charts' ) }
				</Button>
			</div>

			{ errors.length > 0 && (
				<Notice status="warning" isDismissible={ false }>
					<strong>{ __( 'A few lines have no number in them:', 'statista-charts' ) }</strong>
					<ul className="sc-quick-entry__errors">
						{ errors.slice( 0, 3 ).map( ( error ) => (
							<li key={ error.line }>
								{ sprintf(
									/* translators: 1: line number, 2: the line itself. */
									__( 'Line %1$d: “%2$s”', 'statista-charts' ),
									error.line,
									error.text
								) }
							</li>
						) ) }
					</ul>
				</Notice>
			) }
		</div>
	);
}
