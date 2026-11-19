/**
 * Editor UI for the dashboard block: the banner, the KPI row, the chart grid
 * and the side panel are all edited straight on the page.
 */
import {
	InspectorControls,
	MediaUpload,
	MediaUploadCheck,
	useBlockProps,
	useInnerBlocksProps,
} from '@wordpress/block-editor';
import { Button, PanelBody, RangeControl, SelectControl, TextControl, ToggleControl } from '@wordpress/components';
import { __ } from '@wordpress/i18n';

import KpiCard from './kpi-card';
import { getPalettes } from '../lib/palettes';
import { resolveTheme } from '../lib/chart-factory';

const EMPTY_KPI = { icon: '', label: '', value: '', icon2: '', value2: '' };

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

export default function Edit( { attributes, setAttributes } ) {
	const {
		brandName,
		logoUrl,
		logoSize,
		title,
		titleAccent,
		meta,
		updated,
		bannerImage,
		artworkUrl,
		palette,
		theme,
		direction,
		kpis,
		showPanel,
		showPanelButton,
		panelImage,
		panelKicker,
		panelTitle,
		panelText,
		panelButtonText,
		panelButtonUrl,
	} = attributes;

	const palettes = getPalettes();
	const colors = ( palettes[ palette ] || palettes.statista ).colors;
	const resolvedDirection = direction === 'auto' ? ( document.dir === 'rtl' ? 'rtl' : 'ltr' ) : direction;

	const banner = bannerImage
		? `linear-gradient(90deg, ${ colors[ 0 ] }f5 0%, ${ colors[ 0 ] }b8 45%, ${ colors[ 0 ] }00 85%), url(${ bannerImage })`
		: `linear-gradient(115deg, ${ colors[ 0 ] } 0%, ${ colors[ 4 ] } 55%, ${ colors[ 1 ] } 100%)`;

	const blockProps = useBlockProps( {
		className: [
			'statista-dashboard',
			'sd-editor',
			`sc-theme-${ resolveTheme( theme ) }`,
			`sc-dir-${ resolvedDirection }`,
			showPanel ? '' : 'sd-no-panel',
		]
			.filter( Boolean )
			.join( ' ' ),
		dir: resolvedDirection,
		style: {
			'--sc-accent': colors[ 0 ],
			'--sc-accent-2': colors[ 1 ],
			'--sd-banner': banner,
			'--sd-logo-size': `${ logoSize }px`,
		},
	} );

	const innerBlocksProps = useInnerBlocksProps(
		{ className: 'sd-grid' },
		{
			allowedBlocks: [ 'statista/chart' ],
			template: TEMPLATE,
			templateLock: false,
		}
	);

	const updateKpi = ( index, next ) =>
		setAttributes( { kpis: kpis.map( ( kpi, i ) => ( i === index ? next : kpi ) ) } );

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
						<RangeControl
							__nextHasNoMarginBottom
							label={ __( 'Logo size (px)', 'statista-charts' ) }
							value={ logoSize }
							min={ 14 }
							max={ 160 }
							step={ 2 }
							onChange={ ( value ) => setAttributes( { logoSize: value || 22 } ) }
						/>
					) }

					<ImageField
						label={ __( 'Banner background', 'statista-charts' ) }
						url={ bannerImage }
						onSelect={ ( url ) => setAttributes( { bannerImage: url } ) }
						onClear={ () => setAttributes( { bannerImage: '' } ) }
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
				<header className="sd-banner">
					<div className="sd-banner__text">
						<p className="sd-brand">
							{ logoUrl && <img className="sd-brand__logo" src={ logoUrl } alt="" /> }
							<input
								type="text"
								className="sd-brand__name sd-input"
								value={ brandName }
								placeholder={ __( 'Brand', 'statista-charts' ) }
								aria-label={ __( 'Brand', 'statista-charts' ) }
								onChange={ ( event ) => setAttributes( { brandName: event.target.value } ) }
							/>
						</p>

						<h2 className="sd-title">
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
						</h2>

						<p className="sd-meta">
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
						</p>
					</div>

					{ artworkUrl && (
						<div className="sd-artwork">
							<img src={ artworkUrl } alt="" />
						</div>
					) }
				</header>

				<div className="sd-body">
					<div className="sd-main">
						<div className="sd-kpis">
							{ kpis.map( ( kpi, index ) => (
								<KpiCard
									key={ index }
									kpi={ kpi }
									onChange={ ( next ) => updateKpi( index, next ) }
									onRemove={ () => setAttributes( { kpis: kpis.filter( ( item, i ) => i !== index ) } ) }
								/>
							) ) }

							<Button
								className="sd-kpi sd-kpi--add"
								icon="plus-alt2"
								onClick={ () => setAttributes( { kpis: [ ...kpis, { ...EMPTY_KPI } ] } ) }
							>
								{ __( 'Add a metric card', 'statista-charts' ) }
							</Button>
						</div>

						<div { ...innerBlocksProps } />
					</div>

					{ showPanel && (
						<aside className="sd-panel">
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
