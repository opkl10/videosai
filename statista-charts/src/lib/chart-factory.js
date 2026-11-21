/**
 * Turns a plugin configuration into a Chart.js instance.
 *
 * Shared by the editor preview and the front-end view script, so what an author
 * sees while writing is what a reader gets.
 */
import {
	ArcElement,
	BarController,
	BarElement,
	CategoryScale,
	Chart,
	DoughnutController,
	Filler,
	Legend,
	LineController,
	LineElement,
	LinearScale,
	PieController,
	PointElement,
	Tooltip,
} from 'chart.js';

import { usableSeries } from './csv';
import { getColors } from './palettes';

Chart.register(
	ArcElement,
	BarController,
	BarElement,
	CategoryScale,
	DoughnutController,
	Filler,
	Legend,
	LineController,
	LineElement,
	LinearScale,
	PieController,
	PointElement,
	Tooltip
);

const ROUND_TYPES = [ 'pie', 'doughnut' ];

const THEMES = {
	light: {
		text: '#111827',
		muted: '#6b7280',
		grid: 'rgba(17, 24, 39, 0.08)',
		surface: '#ffffff',
		tooltipBg: 'rgba(17, 24, 39, 0.92)',
		tooltipText: '#f9fafb',
	},
	dark: {
		text: '#e5e7eb',
		muted: '#9ca3af',
		grid: 'rgba(229, 231, 235, 0.12)',
		surface: '#111827',
		tooltipBg: 'rgba(249, 250, 251, 0.94)',
		tooltipText: '#111827',
	},
};

function prefersDark() {
	return (
		typeof window !== 'undefined' &&
		typeof window.matchMedia === 'function' &&
		window.matchMedia( '(prefers-color-scheme: dark)' ).matches
	);
}

export function resolveTheme( theme ) {
	if ( theme === 'dark' ) {
		return 'dark';
	}

	if ( theme === 'auto' ) {
		return prefersDark() ? 'dark' : 'light';
	}

	return 'light';
}

function alpha( hex, opacity ) {
	const clean = String( hex ).replace( '#', '' );
	const full = clean.length === 3 ? clean.replace( /./g, ( c ) => c + c ) : clean;
	const int = parseInt( full, 16 );

	if ( Number.isNaN( int ) ) {
		return hex;
	}

	/* eslint-disable no-bitwise */
	const r = ( int >> 16 ) & 255;
	const g = ( int >> 8 ) & 255;
	const b = int & 255;
	/* eslint-enable no-bitwise */

	return `rgba(${ r }, ${ g }, ${ b }, ${ opacity })`;
}

/**
 * Keeps category names in left-to-right order on an RTL page so punctuation
 * such as "Call of Duty:" does not flip.
 *
 * @param {string} label
 * @return {string}
 */
function isolateTickLabel( label ) {
	return `\u2066${ String( label ?? '' ).trim() }\u2069`;
}

export function createFormatter( config ) {
	const decimals = Math.max( 0, Math.min( 4, Number( config.decimals ) || 0 ) );
	let formatter;

	try {
		formatter = new Intl.NumberFormat( undefined, {
			minimumFractionDigits: decimals,
			maximumFractionDigits: decimals,
		} );
	} catch ( error ) {
		formatter = { format: ( value ) => Number( value ).toFixed( decimals ) };
	}

	return ( value ) => {
		if ( value === null || value === undefined || value === '' || Number.isNaN( Number( value ) ) ) {
			return '—';
		}

		return `${ config.prefix || '' }${ formatter.format( Number( value ) ) }${ config.suffix || '' }`;
	};
}

/**
 * Draws the value of every data point on top of it.
 */
function valueLabelsPlugin( format, palette ) {
	return {
		id: 'statistaValueLabels',
		afterDatasetsDraw( chart ) {
			const { ctx } = chart;

			ctx.save();
			ctx.font = '600 12px -apple-system, BlinkMacSystemFont, "Segoe UI", Rubik, Arial, sans-serif';
			ctx.fillStyle = palette.text;
			ctx.textAlign = 'center';
			ctx.textBaseline = 'bottom';

			chart.data.datasets.forEach( ( dataset, datasetIndex ) => {
				const meta = chart.getDatasetMeta( datasetIndex );

				if ( meta.hidden ) {
					return;
				}

				meta.data.forEach( ( element, index ) => {
					const value = dataset.data[ index ];

					if ( value === null || value === undefined ) {
						return;
					}

					const isRound = ROUND_TYPES.includes( meta.type );
					const position = element.tooltipPosition();
					const text = format( value );

					if ( isRound ) {
						ctx.textBaseline = 'middle';
						ctx.fillStyle = '#ffffff';
						ctx.strokeStyle = 'rgba(0, 0, 0, 0.35)';
						ctx.lineWidth = 3;
						ctx.strokeText( text, position.x, position.y );
						ctx.fillText( text, position.x, position.y );
						ctx.fillStyle = palette.text;
						ctx.textBaseline = 'bottom';
						return;
					}

					ctx.fillText( text, position.x, position.y - 6 );
				} );
			} );

			ctx.restore();
		},
	};
}

function buildDatasets( config, colors, isRound ) {
	const smooth = config.smooth !== false;

	return ( config.series || [] ).map( ( series, index ) => {
		const base = {
			label: series.label || '',
			data: ( series.data || [] ).map( ( value ) => ( value === null || value === '' ? null : Number( value ) ) ),
			borderWidth: 2,
		};

		if ( isRound ) {
			return {
				...base,
				backgroundColor: ( config.labels || [] ).map( ( label, i ) => colors[ i % colors.length ] ),
				borderColor: '#ffffff',
				hoverOffset: 10,
			};
		}

		const color = colors[ index % colors.length ];

		if ( config.type === 'line' || config.type === 'area' ) {
			return {
				...base,
				borderColor: color,
				backgroundColor: config.type === 'area' ? alpha( color, 0.18 ) : color,
				fill: config.type === 'area',
				tension: smooth ? 0.35 : 0,
				pointRadius: 3,
				pointHoverRadius: 6,
				pointBackgroundColor: color,
				pointBorderColor: '#ffffff',
				pointBorderWidth: 2,
			};
		}

		return {
			...base,
			backgroundColor: color,
			hoverBackgroundColor: alpha( color, 0.85 ),
			borderColor: 'transparent',
			borderRadius: 8,
			borderSkipped: false,
			maxBarThickness: 64,
		};
	} );
}

/**
 * Creates a Chart.js instance on a canvas.
 *
 * @param {HTMLCanvasElement} canvas Target canvas.
 * @param {Object}            config Sanitized plugin configuration.
 * @return {Object|null} Chart instance, or null when there is nothing to draw.
 */
export function createChart( canvas, config ) {
	if ( ! canvas || config.type === 'stat' ) {
		return null;
	}

	config = { ...config, series: usableSeries( config.series ) };

	const labels = config.labels || [];
	const series = config.series || [];

	if ( ! labels.length || ! series.length ) {
		return null;
	}

	const colors = Array.isArray( config.colors ) && config.colors.length ? config.colors : getColors( config.palette );
	const palette = THEMES[ resolveTheme( config.theme ) ];
	const isRound = ROUND_TYPES.includes( config.type );
	const rtl = config.resolvedDirection === 'rtl' || ( config.direction === 'auto' && document.dir === 'rtl' );
	const format = createFormatter( config );
	const chartType = config.type === 'area' ? 'line' : config.type;

	const longestLabel = labels.reduce( ( max, label ) => Math.max( max, String( label || '' ).length ), 0 );
	const needsAngle = longestLabel > 12 && labels.length > 3;

	const scales = isRound
		? {}
		: {
				x: {
					stacked: !! config.stacked,
					reverse: rtl,
					grid: { display: false },
					border: { color: palette.grid },
					ticks: {
						color: palette.muted,
						font: { size: 11 },
						padding: 10,
						autoSkip: labels.length > 12,
						autoSkipPadding: 8,
						maxRotation: needsAngle ? 45 : 0,
						minRotation: needsAngle ? 35 : 0,
						callback( value ) {
							return isolateTickLabel( this.getLabelForValue( value ) );
						},
					},
					afterFit( scale ) {
						const extra = needsAngle ? Math.min( 110, 36 + longestLabel * 2.1 ) : 36;
						scale.height = Math.max( scale.height, extra );
					},
				},
				y: {
					stacked: !! config.stacked,
					position: rtl ? 'right' : 'left',
					beginAtZero: true,
					grid: {
						display: config.showGrid !== false,
						color: palette.grid,
						drawTicks: false,
					},
					border: { display: false },
					ticks: {
						color: palette.muted,
						font: { size: 12 },
						padding: 8,
						callback: ( value ) => format( value ),
					},
				},
		  };

	return new Chart( canvas, {
		type: chartType,
		data: {
			labels,
			datasets: buildDatasets( config, colors, isRound ),
		},
		options: {
			responsive: true,
			maintainAspectRatio: false,
			locale: document.documentElement.lang || undefined,
			clip: false,
			layout: {
				padding: {
					top: config.showValues ? 18 : 8,
					bottom: 24,
					left: 48,
					right: 36,
				},
			},
			interaction: { intersect: false, mode: isRound ? 'nearest' : 'index' },
			animation: { duration: 650, easing: 'easeOutQuart' },
			plugins: {
				legend: {
					display: config.showLegend !== false && ( isRound || series.length > 1 || !! series[ 0 ].label ),
					position: isRound ? 'bottom' : 'top',
					align: isRound ? 'center' : 'start',
					rtl,
					textDirection: rtl ? 'rtl' : 'ltr',
					labels: {
						color: palette.text,
						usePointStyle: true,
						pointStyle: 'circle',
						boxWidth: 8,
						boxHeight: 8,
						padding: 16,
						font: { size: 13 },
					},
				},
				tooltip: {
					rtl,
					textDirection: rtl ? 'rtl' : 'ltr',
					backgroundColor: palette.tooltipBg,
					titleColor: palette.tooltipText,
					bodyColor: palette.tooltipText,
					padding: 12,
					cornerRadius: 10,
					displayColors: true,
					usePointStyle: true,
					callbacks: {
						label: ( context ) => {
							const name = context.dataset.label || context.label || '';
							const value = format( isRound ? context.parsed : context.parsed.y );

							return name ? `${ name }: ${ value }` : value;
						},
					},
				},
			},
			scales,
			cutout: config.type === 'doughnut' ? '62%' : undefined,
		},
		plugins: config.showValues ? [ valueLabelsPlugin( format, palette ) ] : [],
	} );
}
