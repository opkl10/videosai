/**
 * Editor UI for the stat cards block: the cards are filled in straight on the
 * page, the sidebar only holds the layout and the colours.
 */
import { InspectorControls, useBlockProps } from '@wordpress/block-editor';
import { Button, PanelBody, SelectControl, ToggleControl } from '@wordpress/components';
import { __ } from '@wordpress/i18n';

import Card from './card';
import { getPalettes } from '../lib/palettes';
import { resolveTheme } from '../lib/chart-factory';

const EMPTY_CARD = { icon: '', label: '', value: '', delta: '', note: '', trend: 'none' };

function exampleCards() {
	return [
		{ icon: '📈', label: __( 'Conversion rate', 'statista-charts' ), value: '27.4%', delta: '+3.2%', note: __( 'vs. last quarter', 'statista-charts' ), trend: 'up' },
		{ icon: '🛒', label: __( 'Cart abandonment', 'statista-charts' ), value: '68%', delta: '-1.5%', note: __( 'vs. last quarter', 'statista-charts' ), trend: 'down' },
		{ icon: '💰', label: __( 'Average order', 'statista-charts' ), value: '₪312', delta: '+8%', note: __( 'Across all channels', 'statista-charts' ), trend: 'up' },
	];
}

export default function Edit( { attributes, setAttributes } ) {
	const { cards, columns, showBar, palette, theme, direction } = attributes;

	const palettes = getPalettes();
	const colors = ( palettes[ palette ] || palettes.statista ).colors;
	const resolvedDirection = direction === 'auto' ? ( document.dir === 'rtl' ? 'rtl' : 'ltr' ) : direction;

	const blockProps = useBlockProps( {
		className: [
			'statista-stats',
			'ss-editor',
			`sc-theme-${ resolveTheme( theme ) }`,
			`sc-dir-${ resolvedDirection }`,
			columns > 0 ? `ss-cols-${ columns }` : 'ss-cols-auto',
		].join( ' ' ),
		dir: resolvedDirection,
		style: { '--sc-accent': colors[ 0 ] },
	} );

	const addCard = () => setAttributes( { cards: [ ...cards, { ...EMPTY_CARD } ] } );

	return (
		<>
			<InspectorControls>
				<PanelBody title={ __( 'Cards', 'statista-charts' ) } initialOpen>
					<p className="sc-sidebar-hint">
						{ __( 'Write the figure straight into the card — a percentage, a sum or any short number.', 'statista-charts' ) }
					</p>
					<Button variant="secondary" size="compact" onClick={ () => setAttributes( { cards: exampleCards() } ) }>
						{ __( 'Fill in example cards', 'statista-charts' ) }
					</Button>
				</PanelBody>

				<PanelBody title={ __( 'Layout', 'statista-charts' ) } initialOpen={ false }>
					<SelectControl
						__nextHasNoMarginBottom
						label={ __( 'Cards per row', 'statista-charts' ) }
						value={ String( columns ) }
						options={ [
							{ value: '0', label: __( 'Fit automatically', 'statista-charts' ) },
							{ value: '2', label: '2' },
							{ value: '3', label: '3' },
							{ value: '4', label: '4' },
						] }
						onChange={ ( value ) => setAttributes( { columns: Number( value ) } ) }
					/>
					<ToggleControl
						__nextHasNoMarginBottom
						label={ __( 'Show a bar for percentages', 'statista-charts' ) }
						help={ __( 'Drawn only for values written with a percent sign.', 'statista-charts' ) }
						checked={ showBar }
						onChange={ ( value ) => setAttributes( { showBar: value } ) }
					/>
				</PanelBody>

				<PanelBody title={ __( 'Style', 'statista-charts' ) } initialOpen={ false }>
					<p className="sc-field-label">{ __( 'Color palette', 'statista-charts' ) }</p>
					<div className="sc-palette-grid">
						{ Object.keys( palettes ).map( ( slug ) => (
							<button
								type="button"
								key={ slug }
								className={ `sc-palette-grid__item${ palette === slug ? ' is-selected' : '' }` }
								aria-pressed={ palette === slug }
								onClick={ () => setAttributes( { palette: slug } ) }
							>
								<span className="sc-palette-grid__swatches">
									{ palettes[ slug ].colors.slice( 0, 4 ).map( ( color ) => (
										<span key={ color } style={ { background: color } } />
									) ) }
								</span>
								<span className="sc-palette-grid__label">{ palettes[ slug ].label }</span>
							</button>
						) ) }
					</div>

					<SelectControl
						__nextHasNoMarginBottom
						label={ __( 'Theme', 'statista-charts' ) }
						value={ theme }
						options={ [
							{ value: 'light', label: __( 'Light', 'statista-charts' ) },
							{ value: 'dark', label: __( 'Dark', 'statista-charts' ) },
							{ value: 'auto', label: __( 'Follow the reader’s device', 'statista-charts' ) },
						] }
						onChange={ ( value ) => setAttributes( { theme: value } ) }
					/>

					<SelectControl
						__nextHasNoMarginBottom
						label={ __( 'Text direction', 'statista-charts' ) }
						value={ direction }
						options={ [
							{ value: 'auto', label: __( 'Follow the site', 'statista-charts' ) },
							{ value: 'rtl', label: __( 'Right to left', 'statista-charts' ) },
							{ value: 'ltr', label: __( 'Left to right', 'statista-charts' ) },
						] }
						onChange={ ( value ) => setAttributes( { direction: value } ) }
					/>
				</PanelBody>
			</InspectorControls>

			<div { ...blockProps }>
				{ cards.map( ( card, index ) => (
					<Card
						key={ index }
						card={ card }
						color={ colors[ index % colors.length ] }
						showBar={ showBar }
						onChange={ ( next ) => setAttributes( { cards: cards.map( ( item, i ) => ( i === index ? next : item ) ) } ) }
						onRemove={ () => setAttributes( { cards: cards.filter( ( item, i ) => i !== index ) } ) }
					/>
				) ) }

				<Button className="ss-card ss-card--add" icon="plus-alt2" onClick={ addCard }>
					{ __( 'Add a card', 'statista-charts' ) }
				</Button>
			</div>
		</>
	);
}
