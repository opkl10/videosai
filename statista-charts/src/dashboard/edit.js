/**
 * Editor UI for the dashboard block: the banner, the KPI row, the chart grid
 * and the side panel are all edited straight on the page.
 */
import {
	InspectorControls,
	MediaUpload,
	MediaUploadCheck,
	store as blockEditorStore,
	useBlockProps,
	useInnerBlocksProps,
} from '@wordpress/block-editor';
import { createBlock } from '@wordpress/blocks';
import { BaseControl, Button, PanelBody, RangeControl, SelectControl, TextControl, ToggleControl } from '@wordpress/components';
import { useDispatch, useSelect } from '@wordpress/data';
import { useEffect } from '@wordpress/element';
import { __ } from '@wordpress/i18n';

import KpiList from './kpi-list';
import { getPalettes } from '../lib/palettes';
import { resolveTheme } from '../lib/chart-factory';

const TEMPLATE = [
	[
		'statista/chart',
		{
			type: 'bar',
			title: __( 'Revenue', 'statista-charts' ),
			labels: [ 'Q1', 'Q2', 'Q3', 'Q4' ],
			series: [ { label: '', data: [ 320, 480, 610, 840 ] } ],
		},
	],
	[
		'statista/chart',
		{
			type: 'line',
			title: __( 'Units sold', 'statista-charts' ),
			labels: [ 'Q1', 'Q2', 'Q3', 'Q4' ],
			series: [ { label: '', data: [ 4.2, 7.1, 9.6, 12.7 ] } ],
		},
	],
];

function defaultChartBlocks() {
	return TEMPLATE.map( ( [ name, attrs ] ) => createBlock( name, attrs ) );
}

function exampleContent() {
	return {
		brandName: __( 'Statista', 'statista-charts' ),
		title: __( 'Product launch', 'statista-charts' ),
		titleAccent: __( '— live tracker', 'statista-charts' ),
		meta: __( 'Updated daily • Web and mobile', 'statista-charts' ),
		updated: __( 'today', 'statista-charts' ),
		kpis: [
			{ icon: '🟦', label: __( 'Split by platform', 'statista-charts' ), value: '75%', icon2: '🟩', value2: '25%' },
			{ icon: '📈', label: __( 'Revenue (net)', 'statista-charts' ), value: '$1.65B', icon2: '', value2: '' },
			{ icon: '📦', label: __( 'Units sold', 'statista-charts' ), value: '17.07M', icon2: '', value2: '' },
		],
		showPanel: true,
		panelKicker: __( 'The full story', 'statista-charts' ),
		panelTitle: __( 'What the numbers mean', 'statista-charts' ),
		panelText: __( 'A short paragraph that explains the dashboard and invites the reader to keep going.', 'statista-charts' ),
		panelButtonText: __( 'Download the report', 'statista-charts' ),
	};
}

function ImageField( { label, url, onSelect, onClear } ) {
	return (
		<div className="sd-image-field">
			<TextControl
				__nextHasNoMarginBottom
				label={ label }
				value={ url }
				placeholder="https://…"
				onChange={ ( value ) => onSelect( value ) }
			/>
			<div className="sd-image-field__actions">
				<MediaUploadCheck>
					<MediaUpload
						allowedTypes={ [ 'image' ] }
						onSelect={ ( media ) => onSelect( media.url ) }
						render={ ( { open } ) => (
							<Button variant="secondary" size="compact" onClick={ open }>
								{ __( 'Choose image', 'statista-charts' ) }
							</Button>
						) }
					/>
				</MediaUploadCheck>
				{ url !== '' && (
					<Button variant="tertiary" size="compact" onClick={ onClear }>
						{ __( 'Remove', 'statista-charts' ) }
					</Button>
				) }
			</div>
		</div>
	);
}

function LogoImage( { url } ) {
	if ( ! url ) {
		return null;
	}

	return <img className="sd-brand__logo" src={ url } alt="" />;
}

/**
 * Turns a 3- or 6-digit hex into #rrggbb, or falls back.
 *
 * @param {string} value
 * @param {string} fallback
 * @return {string}
 */
function normalizeHex( value, fallback ) {
	let hex = String( value || '' ).trim();

	if ( /^#[0-9A-Fa-f]{3}$/.test( hex ) ) {
		hex = '#' + hex[ 1 ] + hex[ 1 ] + hex[ 2 ] + hex[ 2 ] + hex[ 3 ] + hex[ 3 ];
	}

	return /^#[0-9A-Fa-f]{6}$/.test( hex ) ? hex : fallback;
}

/**
 * Banner background: custom colours, or the palette, plus an optional wallpaper.
 *
 * @param {string[]} colors
 * @param {string}   bannerImage
 * @param {string}   bannerColor
 * @param {string}   bannerColor2
 * @return {string}
 */
function bannerBackground( colors, bannerImage, bannerColor, bannerColor2 ) {
	const start = normalizeHex( bannerColor, colors[ 0 ] );
	const mid = normalizeHex( bannerColor2, bannerColor ? start : colors[ 4 ] );
	const end = normalizeHex( bannerColor2, bannerColor ? start : colors[ 1 ] );

	if ( bannerImage ) {
		return `linear-gradient(90deg, ${ start }f5 0%, ${ start }b8 45%, ${ start }00 85%), url(${ bannerImage })`;
	}

	return `linear-gradient(115deg, ${ start } 0%, ${ mid } 55%, ${ end } 100%)`;
}

function ColorField( { label, help, value, onChange } ) {
	const hex = normalizeHex( value, '#ffffff' );

	return (
		<BaseControl label={ label } help={ help } __nextHasNoMarginBottom className="sd-color-field">
			<div className="sd-color-field__row">
				<input
					type="color"
					value={ hex }
					aria-label={ label }
					onChange={ ( event ) => onChange( event.target.value ) }
				/>
				<input
					type="text"
					className="sd-color-field__hex"
					value={ value }
					placeholder="#1f6feb"
					onChange={ ( event ) => onChange( event.target.value ) }
				/>
				{ value !== '' && (
					<Button variant="tertiary" size="compact" onClick={ () => onChange( '' ) }>
						{ __( 'Reset', 'statista-charts' ) }
					</Button>
				) }
			</div>
		</BaseControl>
	);
}

export default function Edit( { attributes, setAttributes, clientId } ) {
	const {
		brandName,
		logoUrl,
		logoSize,
		showBrandName,
		logoPosition,
		title,
		titleAccent,
		meta,
		updated,
		bannerImage,
		bannerColor,
		bannerColor2,
		bannerTextColor,
		artworkUrl,
		palette,
		theme,
		direction,
		kpis,
		showPanel,
		showPanelButton,
		panelPosition,
		kpiPosition,
		kpiLayout,
		kpiPerRow,
		kpiOddWide,
		panelImage,
		panelKicker,
		panelTitle,
		panelText,
		panelButtonText,
		panelButtonUrl,
	} = attributes;

	const { replaceInnerBlocks } = useDispatch( blockEditorStore );
	const justInserted = useSelect(
		( select ) => select( blockEditorStore ).wasBlockJustInserted( clientId ),
		[ clientId ]
	);
	const innerCount = useSelect(
		( select ) => select( blockEditorStore ).getBlockCount( clientId ),
		[ clientId ]
	);

	useEffect( () => {
		if ( ! justInserted || innerCount > 0 ) {
			return;
		}

		replaceInnerBlocks( clientId, defaultChartBlocks(), false );
	}, [ justInserted, innerCount, clientId, replaceInnerBlocks ] );

	const palettes = getPalettes();
	const colors = ( palettes[ palette ] || palettes.statista ).colors;
	const resolvedDirection = direction === 'auto' ? ( document.dir === 'rtl' ? 'rtl' : 'ltr' ) : direction;

	const banner = bannerBackground( colors, bannerImage, bannerColor, bannerColor2 );

	const blockProps = useBlockProps( {
		className: [
			'statista-dashboard',
			'sd-editor',
			`sc-theme-${ resolveTheme( theme ) }`,
			`sc-dir-${ resolvedDirection }`,
			showPanel ? `sd-panel-pos-${ panelPosition || 'end' }` : 'sd-no-panel',
			`sd-kpis-pos-${ kpiPosition || 'top' }`,
			`sd-kpis-layout-${ kpiLayout || 'row' }`,
			kpiLayout !== 'column' && ( kpiPerRow || 0 ) >= 1 ? 'sd-kpis-cols' : '',
		]
			.filter( Boolean )
			.join( ' ' ),
		dir: resolvedDirection,
		style: {
			'--sc-accent': colors[ 0 ],
			'--sc-accent-2': colors[ 1 ],
			'--sd-banner': banner,
			'--sd-logo-size': `${ logoSize }px`,
			...( normalizeHex( bannerTextColor, '' )
				? { '--sd-banner-fg': normalizeHex( bannerTextColor, '' ) }
				: {} ),
			...( kpiLayout !== 'column' && ( kpiPerRow || 0 ) >= 1
				? { '--sd-kpi-cols': String( kpiPerRow ) }
				: {} ),
		},
	} );

	// No `template` here: Gutenberg re-fills an empty inner area from the
	// template on every load, so deleted charts came back after updates.
	const innerBlocksProps = useInnerBlocksProps(
		{ className: 'sd-grid' },
		{
			allowedBlocks: [ 'statista/chart' ],
			templateLock: false,
		}
	);

	return (
		<>
			<InspectorControls>
				<PanelBody title={ __( 'Dashboard', 'statista-charts' ) } initialOpen>
					<p className="sc-sidebar-hint">
						{ __( 'The texts are edited straight on the dashboard. Images are chosen here.', 'statista-charts' ) }
					</p>
					<Button variant="secondary" size="compact" onClick={ () => setAttributes( exampleContent() ) }>
						{ __( 'Fill in example content', 'statista-charts' ) }
					</Button>
				</PanelBody>

				<PanelBody title={ __( 'Images', 'statista-charts' ) } initialOpen={ false }>
					<ImageField
						label={ __( 'Logo', 'statista-charts' ) }
						url={ logoUrl }
						onSelect={ ( url ) => setAttributes( { logoUrl: url } ) }
						onClear={ () => setAttributes( { logoUrl: '' } ) }
					/>
					{ logoUrl !== '' && (
						<>
							<RangeControl
								__nextHasNoMarginBottom
								label={ __( 'Logo size (px)', 'statista-charts' ) }
								value={ logoSize }
								min={ 14 }
								max={ 320 }
								step={ 2 }
								onChange={ ( value ) => setAttributes( { logoSize: value || 22 } ) }
							/>
							<SelectControl
								__nextHasNoMarginBottom
								label={ __( 'Logo position', 'statista-charts' ) }
								value={ logoPosition || 'start' }
								options={ [
									{ value: 'start', label: __( 'Next to the name, before it', 'statista-charts' ) },
									{ value: 'end', label: __( 'Next to the name, after it', 'statista-charts' ) },
									{ value: 'top', label: __( 'Above the titles', 'statista-charts' ) },
									{ value: 'bottom', label: __( 'Below the titles', 'statista-charts' ) },
									{ value: 'left', label: __( 'Left', 'statista-charts' ) },
									{ value: 'right', label: __( 'Right', 'statista-charts' ) },
								] }
								onChange={ ( value ) => setAttributes( { logoPosition: value } ) }
							/>
						</>
					) }
					<ToggleControl
						__nextHasNoMarginBottom
						label={ __( 'Show the name next to the logo', 'statista-charts' ) }
						help={ __( 'Turn off to hide the brand sentence without deleting it.', 'statista-charts' ) }
						checked={ showBrandName !== false }
						onChange={ ( value ) => setAttributes( { showBrandName: value } ) }
					/>

					<ImageField
						label={ __( 'Banner wallpaper', 'statista-charts' ) }
						url={ bannerImage }
						onSelect={ ( url ) => setAttributes( { bannerImage: url } ) }
						onClear={ () => setAttributes( { bannerImage: '' } ) }
					/>
					<ColorField
						label={ __( 'Banner colour', 'statista-charts' ) }
						help={ __( 'Leave empty to use the palette. With a wallpaper this tints the left side so the title stays readable.', 'statista-charts' ) }
						value={ bannerColor || '' }
						onChange={ ( value ) => setAttributes( { bannerColor: value } ) }
					/>
					<ColorField
						label={ __( 'Second banner colour', 'statista-charts' ) }
						help={ __( 'Optional. Makes a gradient with the first colour.', 'statista-charts' ) }
						value={ bannerColor2 || '' }
						onChange={ ( value ) => setAttributes( { bannerColor2: value } ) }
					/>
					<ColorField
						label={ __( 'Banner text colour', 'statista-charts' ) }
						help={ __( 'Title, brand and meta in the banner. Leave empty for white.', 'statista-charts' ) }
						value={ bannerTextColor || '' }
						onChange={ ( value ) => setAttributes( { bannerTextColor: value } ) }
					/>
					<ImageField
						label={ __( 'Banner artwork', 'statista-charts' ) }
						url={ artworkUrl }
						onSelect={ ( url ) => setAttributes( { artworkUrl: url } ) }
						onClear={ () => setAttributes( { artworkUrl: '' } ) }
					/>
					<ImageField
						label={ __( 'Side panel image', 'statista-charts' ) }
						url={ panelImage }
						onSelect={ ( url ) => setAttributes( { panelImage: url } ) }
						onClear={ () => setAttributes( { panelImage: '' } ) }
					/>
				</PanelBody>

				<PanelBody title={ __( 'Side panel', 'statista-charts' ) } initialOpen={ false }>
					<ToggleControl
						__nextHasNoMarginBottom
						label={ __( 'Show the side panel', 'statista-charts' ) }
						checked={ showPanel }
						onChange={ ( value ) => setAttributes( { showPanel: value } ) }
					/>
					{ showPanel && (
						<SelectControl
							__nextHasNoMarginBottom
							label={ __( 'Panel position', 'statista-charts' ) }
							help={ __( 'Left and right stay put even when the page is in Hebrew.', 'statista-charts' ) }
							value={ panelPosition || 'end' }
							options={ [
								{ value: 'end', label: __( 'Beside the charts, after them', 'statista-charts' ) },
								{ value: 'start', label: __( 'Beside the charts, before them', 'statista-charts' ) },
								{ value: 'right', label: __( 'Right', 'statista-charts' ) },
								{ value: 'left', label: __( 'Left', 'statista-charts' ) },
								{ value: 'bottom', label: __( 'Below the charts', 'statista-charts' ) },
							] }
							onChange={ ( value ) => setAttributes( { panelPosition: value } ) }
						/>
					) }
					<ToggleControl
						__nextHasNoMarginBottom
						label={ __( 'Show the button', 'statista-charts' ) }
						help={ __( 'Turn off to keep the panel without a call to action.', 'statista-charts' ) }
						checked={ showPanelButton }
						onChange={ ( value ) => setAttributes( { showPanelButton: value } ) }
					/>
					{ showPanelButton && (
						<TextControl
							__nextHasNoMarginBottom
							label={ __( 'Button link', 'statista-charts' ) }
							value={ panelButtonUrl }
							placeholder="https://…"
							onChange={ ( value ) => setAttributes( { panelButtonUrl: value } ) }
						/>
					) }
				</PanelBody>

				<PanelBody title={ __( 'Metric cards', 'statista-charts' ) } initialOpen={ false }>
					<SelectControl
						__nextHasNoMarginBottom
						label={ __( 'Cards position', 'statista-charts' ) }
						value={ kpiPosition || 'top' }
						options={ [
							{ value: 'top', label: __( 'Above the charts', 'statista-charts' ) },
							{ value: 'bottom', label: __( 'Below the charts', 'statista-charts' ) },
							{ value: 'start', label: __( 'Beside the charts, before them', 'statista-charts' ) },
							{ value: 'end', label: __( 'Beside the charts, after them', 'statista-charts' ) },
						] }
						onChange={ ( value ) => setAttributes( { kpiPosition: value } ) }
					/>
					<SelectControl
						__nextHasNoMarginBottom
						label={ __( 'Cards layout', 'statista-charts' ) }
						help={ __( 'A column stacks the cards. A row places them next to each other. Drag a card onto another card to swap them, or onto empty space to move it and leave a gap behind.', 'statista-charts' ) }
						value={ kpiLayout || 'row' }
						options={ [
							{ value: 'row', label: __( 'In a row', 'statista-charts' ) },
							{ value: 'column', label: __( 'In a column', 'statista-charts' ) },
						] }
						onChange={ ( value ) => setAttributes( { kpiLayout: value } ) }
					/>
					{ kpiLayout !== 'column' && (
						<SelectControl
							__nextHasNoMarginBottom
							label={ __( 'Cards per row', 'statista-charts' ) }
							help={ __( 'How many cards sit side by side. Auto fits as many as the width allows.', 'statista-charts' ) }
							value={ String( kpiPerRow || 0 ) }
							options={ [
								{ value: '0', label: __( 'Auto', 'statista-charts' ) },
								{ value: '1', label: '1' },
								{ value: '2', label: '2' },
								{ value: '3', label: '3' },
								{ value: '4', label: '4' },
								{ value: '5', label: '5' },
								{ value: '6', label: '6' },
							] }
							onChange={ ( value ) => setAttributes( { kpiPerRow: parseInt( value, 10 ) || 0 } ) }
						/>
					) }
					{ kpiLayout !== 'column' && (
						<ToggleControl
							__nextHasNoMarginBottom
							label={ __( 'Leftover card fills the row', 'statista-charts' ) }
							help={ __( 'When a card sits alone on the last row, stretch it across the row. You can also tap the width button on any card. With Auto, leftover is counted as if two cards sat per row.', 'statista-charts' ) }
							checked={ Boolean( kpiOddWide ) }
							onChange={ ( value ) => setAttributes( { kpiOddWide: value } ) }
						/>
					) }
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

			<section { ...blockProps }>
				<header
					className={ `sd-banner${ logoUrl ? ` sd-logo-pos-${ logoPosition || 'start' }` : '' }` }
					dir={ logoUrl && [ 'left', 'right' ].includes( logoPosition ) ? 'ltr' : undefined }
				>
					{ logoUrl && logoPosition === 'left' && <LogoImage url={ logoUrl } /> }

					<div
						className="sd-banner__text"
						dir={ logoUrl && [ 'left', 'right' ].includes( logoPosition ) ? resolvedDirection : undefined }
					>
						{ logoUrl && logoPosition === 'top' && <LogoImage url={ logoUrl } /> }

						{ ( showBrandName !== false || ( logoUrl && [ 'start', 'end' ].includes( logoPosition || 'start' ) ) ) && (
							<div className="sd-brand">
								{ logoUrl && ( logoPosition || 'start' ) === 'start' && <LogoImage url={ logoUrl } /> }
								{ showBrandName !== false && (
									<input
										type="text"
										className="sd-brand__name sd-input"
										value={ brandName }
										placeholder={ __( 'Brand', 'statista-charts' ) }
										aria-label={ __( 'Brand', 'statista-charts' ) }
										onChange={ ( event ) => setAttributes( { brandName: event.target.value } ) }
									/>
								) }
								{ logoUrl && logoPosition === 'end' && <LogoImage url={ logoUrl } /> }
							</div>
						) }

						<div className="sd-title">
							<input
								type="text"
								className="sd-title__main sd-input"
								value={ title }
								placeholder={ __( 'Dashboard title', 'statista-charts' ) }
								aria-label={ __( 'Dashboard title', 'statista-charts' ) }
								onChange={ ( event ) => setAttributes( { title: event.target.value } ) }
							/>
							<input
								type="text"
								className="sd-title__accent sd-input"
								value={ titleAccent }
								placeholder={ __( '— subtitle', 'statista-charts' ) }
								aria-label={ __( 'Subtitle', 'statista-charts' ) }
								onChange={ ( event ) => setAttributes( { titleAccent: event.target.value } ) }
							/>
						</div>

						<div className="sd-meta">
							<input
								type="text"
								className="sd-meta__item sd-input"
								value={ meta }
								placeholder={ __( 'Updated daily • Source', 'statista-charts' ) }
								aria-label={ __( 'Meta line', 'statista-charts' ) }
								onChange={ ( event ) => setAttributes( { meta: event.target.value } ) }
							/>
							<span className="sd-meta__item sd-meta__updated">
								<span className="sd-meta__prefix">{ __( 'Last updated', 'statista-charts' ) }</span>
								<input
									type="text"
									className="sd-input"
									value={ updated }
									placeholder={ __( 'today', 'statista-charts' ) }
									aria-label={ __( 'Last updated', 'statista-charts' ) }
									onChange={ ( event ) => setAttributes( { updated: event.target.value } ) }
								/>
							</span>
						</div>

						{ logoUrl && logoPosition === 'bottom' && <LogoImage url={ logoUrl } /> }
					</div>

					{ artworkUrl && (
						<div className="sd-artwork">
							<img src={ artworkUrl } alt="" />
						</div>
					) }

					{ logoUrl && logoPosition === 'right' && <LogoImage url={ logoUrl } /> }
				</header>

				<div
					className="sd-body"
					dir={ showPanel && [ 'left', 'right' ].includes( panelPosition ) ? 'ltr' : undefined }
				>
					<div
						className="sd-main"
						dir={ showPanel && [ 'left', 'right' ].includes( panelPosition ) ? resolvedDirection : undefined }
					>
						<KpiList
							kpis={ kpis }
							layout={ kpiLayout || 'row' }
							rtl={ resolvedDirection === 'rtl' }
							perRow={ kpiPerRow || 0 }
							oddWide={ Boolean( kpiOddWide ) }
							onChange={ ( next ) => setAttributes( { kpis: next } ) }
						/>

						<div { ...innerBlocksProps } />
					</div>

					{ showPanel && (
						<aside
							className="sd-panel"
							dir={ [ 'left', 'right' ].includes( panelPosition ) ? resolvedDirection : undefined }
						>
							{ panelImage && <img className="sd-panel__image" src={ panelImage } alt="" /> }
							<input
								type="text"
								className="sd-panel__kicker sd-input"
								value={ panelKicker }
								placeholder={ __( 'Small line above', 'statista-charts' ) }
								aria-label={ __( 'Small line above', 'statista-charts' ) }
								onChange={ ( event ) => setAttributes( { panelKicker: event.target.value } ) }
							/>
							<input
								type="text"
								className="sd-panel__title sd-input"
								value={ panelTitle }
								placeholder={ __( 'Panel title', 'statista-charts' ) }
								aria-label={ __( 'Panel title', 'statista-charts' ) }
								onChange={ ( event ) => setAttributes( { panelTitle: event.target.value } ) }
							/>
							<textarea
								className="sd-panel__text sd-input"
								value={ panelText }
								rows={ 4 }
								placeholder={ __( 'A couple of sentences for the reader.', 'statista-charts' ) }
								aria-label={ __( 'Panel text', 'statista-charts' ) }
								onChange={ ( event ) => setAttributes( { panelText: event.target.value } ) }
							/>
							{ showPanelButton && (
								<input
									type="text"
									className="sd-panel__button sd-input"
									value={ panelButtonText }
									placeholder={ __( 'Button text', 'statista-charts' ) }
									aria-label={ __( 'Button text', 'statista-charts' ) }
									onChange={ ( event ) => setAttributes( { panelButtonText: event.target.value } ) }
								/>
							) }
						</aside>
					) }
				</div>
			</section>
		</>
	);
}
