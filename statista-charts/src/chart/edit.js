/**
 * Block editor UI for the Statista Chart block.
 */
import {
	BlockControls,
	InspectorControls,
	useBlockProps,
} from '@wordpress/block-editor';
import {
	Button,
	PanelBody,
	PanelRow,
	RangeControl,
	SelectControl,
	TextControl,
	ToggleControl,
	ToolbarButton,
	ToolbarGroup,
} from '@wordpress/components';
import { useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';

import AdvancedData from '../components/advanced-data';
import ChartPreview from '../components/chart-preview';
import QuickEntry from '../components/quick-entry';
import StatCard from '../components/stat-card';
import { getPalettes } from '../lib/palettes';
import { resolveTheme } from '../lib/chart-factory';

const CHART_TYPES = [
	{ value: 'bar', icon: 'chart-bar', label: __( 'Bars', 'statista-charts' ) },
	{ value: 'line', icon: 'chart-line', label: __( 'Lines', 'statista-charts' ) },
	{ value: 'area', icon: 'chart-area', label: __( 'Area', 'statista-charts' ) },
	{ value: 'pie', icon: 'chart-pie', label: __( 'Pie', 'statista-charts' ) },
	{ value: 'doughnut', icon: 'marker', label: __( 'Doughnut', 'statista-charts' ) },
	{ value: 'stat', icon: 'performance', label: __( 'Single number', 'statista-charts' ) },
];

export default function Edit( { attributes, setAttributes, isSelected } ) {
	const [ showEntry, setShowEntry ] = useState( true );
	const [ advancedOpen, setAdvancedOpen ] = useState( false );
	const palettes = getPalettes();

	const {
		type,
		title,
		showTitle,
		source,
		showSource,
		showChart,
		showLegend,
		showValues,
		showGrid,
		showTable,
		stacked,
		smooth,
		palette,
		theme,
		direction,
		height,
		decimals,
		prefix,
		suffix,
		labels,
		series,
		statValue,
		statLabel,
		statDelta,
		statTrend,
		statCaption,
	} = attributes;

	const resolvedDirection =
		direction === 'auto' ? ( document.dir === 'rtl' ? 'rtl' : 'ltr' ) : direction;

	const config = {
		...attributes,
		colors: ( palettes[ palette ] || palettes.statista ).colors,
		resolvedDirection,
	};

	const blockProps = useBlockProps( {
		className: [
			'statista-chart',
			'sc-editor',
			`sc-type-${ type }`,
			`sc-theme-${ resolveTheme( theme ) }`,
			`sc-dir-${ resolvedDirection }`,
			! showChart ? 'sc-chart-hidden' : '',
		]
			.filter( Boolean )
			.join( ' ' ),
		dir: resolvedDirection,
		style: {
			'--sc-accent': config.colors[ 0 ],
			'--sc-accent-2': config.colors[ 1 ],
		},
	} );

	const isRound = type === 'pie' || type === 'doughnut';
	const isStat = type === 'stat';

	return (
		<>
			<BlockControls>
				<ToolbarGroup>
					<ToolbarButton
						icon="edit"
						label={ __( 'Edit the data', 'statista-charts' ) }
						isPressed={ showEntry }
						onClick={ () => setShowEntry( ! showEntry ) }
					/>
				</ToolbarGroup>
				<ToolbarGroup>
					<ToolbarButton
						icon={ showChart ? 'visibility' : 'hidden' }
						label={ showChart ? __( 'Hide chart', 'statista-charts' ) : __( 'Show chart', 'statista-charts' ) }
						onClick={ () => setAttributes( { showChart: ! showChart } ) }
					/>
				</ToolbarGroup>
			</BlockControls>

			<InspectorControls>
				<PanelBody title={ __( 'Chart', 'statista-charts' ) } initialOpen>
					<div className="sc-type-grid">
						{ CHART_TYPES.map( ( item ) => (
							<Button
								key={ item.value }
								icon={ item.icon }
								className="sc-type-grid__button"
								variant={ type === item.value ? 'primary' : 'secondary' }
								onClick={ () => setAttributes( { type: item.value } ) }
							>
								{ item.label }
							</Button>
						) ) }
					</div>

					<ToggleControl
						__nextHasNoMarginBottom
						label={ __( 'Show chart', 'statista-charts' ) }
						help={ __( 'Turn off to keep the data in the post without drawing it.', 'statista-charts' ) }
						checked={ showChart }
						onChange={ ( value ) => setAttributes( { showChart: value } ) }
					/>

					{ ! isStat && (
						<RangeControl
							__nextHasNoMarginBottom
							label={ __( 'Height (px)', 'statista-charts' ) }
							value={ height }
							min={ 160 }
							max={ 800 }
							step={ 10 }
							onChange={ ( value ) => setAttributes( { height: value || 360 } ) }
						/>
					) }
				</PanelBody>

				<PanelBody title={ __( 'Content', 'statista-charts' ) } initialOpen={ false }>
					<TextControl
						__nextHasNoMarginBottom
						label={ __( 'Title', 'statista-charts' ) }
						value={ title }
						onChange={ ( value ) => setAttributes( { title: value } ) }
					/>
					<ToggleControl
						__nextHasNoMarginBottom
						label={ __( 'Show title', 'statista-charts' ) }
						checked={ showTitle }
						onChange={ ( value ) => setAttributes( { showTitle: value } ) }
					/>
					<TextControl
						__nextHasNoMarginBottom
						label={ __( 'Data source', 'statista-charts' ) }
						value={ source }
						onChange={ ( value ) => setAttributes( { source: value } ) }
					/>
					<ToggleControl
						__nextHasNoMarginBottom
						label={ __( 'Show data source', 'statista-charts' ) }
						checked={ showSource }
						onChange={ ( value ) => setAttributes( { showSource: value } ) }
					/>
				</PanelBody>

				<PanelBody title={ __( 'Display options', 'statista-charts' ) } initialOpen={ false }>
					{ ! isStat && (
						<>
							<ToggleControl
								__nextHasNoMarginBottom
								label={ __( 'Legend', 'statista-charts' ) }
								checked={ showLegend }
								onChange={ ( value ) => setAttributes( { showLegend: value } ) }
							/>
							<ToggleControl
								__nextHasNoMarginBottom
								label={ __( 'Value labels', 'statista-charts' ) }
								checked={ showValues }
								onChange={ ( value ) => setAttributes( { showValues: value } ) }
							/>
							{ ! isRound && (
								<ToggleControl
									__nextHasNoMarginBottom
									label={ __( 'Grid lines', 'statista-charts' ) }
									checked={ showGrid }
									onChange={ ( value ) => setAttributes( { showGrid: value } ) }
								/>
							) }
							{ type === 'bar' && (
								<ToggleControl
									__nextHasNoMarginBottom
									label={ __( 'Stacked bars', 'statista-charts' ) }
									checked={ stacked }
									onChange={ ( value ) => setAttributes( { stacked: value } ) }
								/>
							) }
							{ ( type === 'line' || type === 'area' ) && (
								<ToggleControl
									__nextHasNoMarginBottom
									label={ __( 'Smooth lines', 'statista-charts' ) }
									checked={ smooth }
									onChange={ ( value ) => setAttributes( { smooth: value } ) }
								/>
							) }
							<ToggleControl
								__nextHasNoMarginBottom
								label={ __( 'Data table below the chart', 'statista-charts' ) }
								help={ __( 'The table is always in the markup for screen readers; this shows it to everyone.', 'statista-charts' ) }
								checked={ showTable }
								onChange={ ( value ) => setAttributes( { showTable: value } ) }
							/>
						</>
					) }

					<RangeControl
						__nextHasNoMarginBottom
						label={ __( 'Decimals', 'statista-charts' ) }
						value={ decimals }
						min={ 0 }
						max={ 4 }
						onChange={ ( value ) => setAttributes( { decimals: value || 0 } ) }
					/>
					<PanelRow>
						<TextControl
							__nextHasNoMarginBottom
							label={ __( 'Value prefix', 'statista-charts' ) }
							value={ prefix }
							onChange={ ( value ) => setAttributes( { prefix: value } ) }
						/>
						<TextControl
							__nextHasNoMarginBottom
							label={ __( 'Value suffix', 'statista-charts' ) }
							value={ suffix }
							onChange={ ( value ) => setAttributes( { suffix: value } ) }
						/>
					</PanelRow>
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

				{ isStat && (
					<PanelBody title={ __( 'Single number', 'statista-charts' ) } initialOpen>
						<TextControl
							__nextHasNoMarginBottom
							label={ __( 'Value', 'statista-charts' ) }
							help={ __( 'Leave empty to use the first value of the table.', 'statista-charts' ) }
							value={ statValue }
							onChange={ ( value ) => setAttributes( { statValue: value } ) }
						/>
						<TextControl
							__nextHasNoMarginBottom
							label={ __( 'Label', 'statista-charts' ) }
							value={ statLabel }
							onChange={ ( value ) => setAttributes( { statLabel: value } ) }
						/>
						<TextControl
							__nextHasNoMarginBottom
							label={ __( 'Change', 'statista-charts' ) }
							value={ statDelta }
							onChange={ ( value ) => setAttributes( { statDelta: value } ) }
						/>
						<SelectControl
							__nextHasNoMarginBottom
							label={ __( 'Trend', 'statista-charts' ) }
							value={ statTrend }
							options={ [
								{ value: 'up', label: __( 'Up', 'statista-charts' ) },
								{ value: 'down', label: __( 'Down', 'statista-charts' ) },
								{ value: 'flat', label: __( 'Flat', 'statista-charts' ) },
							] }
							onChange={ ( value ) => setAttributes( { statTrend: value } ) }
						/>
						<TextControl
							__nextHasNoMarginBottom
							label={ __( 'Caption', 'statista-charts' ) }
							value={ statCaption }
							onChange={ ( value ) => setAttributes( { statCaption: value } ) }
						/>
					</PanelBody>
				) }

				<PanelBody title={ __( 'Data', 'statista-charts' ) } initialOpen={ false }>
					<p className="sc-sidebar-hint">
						{ __( 'The data is written straight under the chart, one line per item.', 'statista-charts' ) }
					</p>
					<Button
						variant="secondary"
						size="compact"
						onClick={ () => {
							setShowEntry( true );
							setAdvancedOpen( false );
						} }
					>
						{ __( 'Edit the data', 'statista-charts' ) }
					</Button>
					<Button
						variant="tertiary"
						size="compact"
						onClick={ () => {
							setShowEntry( true );
							setAdvancedOpen( true );
						} }
					>
						{ __( 'Advanced options', 'statista-charts' ) }
					</Button>
				</PanelBody>
			</InspectorControls>

			<figure { ...blockProps }>
				{ showTitle && (
					<figcaption className="sc-header">
						<input
							type="text"
							className="sc-title sc-title-input"
							value={ title }
							placeholder={ __( 'Chart title…', 'statista-charts' ) }
							aria-label={ __( 'Chart title', 'statista-charts' ) }
							onChange={ ( event ) => setAttributes( { title: event.target.value } ) }
						/>
					</figcaption>
				) }

				{ ! showChart && (
					<p className="sc-empty">{ __( 'The chart is hidden on the front end.', 'statista-charts' ) }</p>
				) }
				{ showChart && isStat && <StatCard config={ config } /> }
				{ showChart && ! isStat && <ChartPreview config={ config } /> }

				{ showSource && (
					<p className="sc-source">
						<span className="sc-source-label">{ __( 'Source:', 'statista-charts' ) }</span>
						<input
							type="text"
							className="sc-source-input"
							value={ source }
							placeholder={ __( 'Data source…', 'statista-charts' ) }
							aria-label={ __( 'Data source', 'statista-charts' ) }
							onChange={ ( event ) => setAttributes( { source: event.target.value } ) }
						/>
					</p>
				) }

				{ isSelected && showEntry && (
					<div className="sc-editor-panel">
						<QuickEntry
							attributes={ attributes }
							setAttributes={ setAttributes }
							onOpenAdvanced={ () => setAdvancedOpen( true ) }
						/>
						<AdvancedData
							attributes={ attributes }
							setAttributes={ setAttributes }
							isOpen={ advancedOpen }
							onToggle={ () => setAdvancedOpen( ! advancedOpen ) }
						/>
					</div>
				) }
			</figure>
		</>
	);
}
