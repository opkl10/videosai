/**
 * Color palettes.
 *
 * PHP owns the list and injects it before the editor script runs; the copy below
 * only keeps the editor usable if that inline script is ever stripped.
 */
import { __ } from '@wordpress/i18n';

const FALLBACK_PALETTES = {
	statista: {
		label: __( 'Statista', 'statista-charts' ),
		colors: [ '#1f6feb', '#00b3a4', '#ffb020', '#ef476f', '#7c5cff', '#2bb3ff', '#5dd39e', '#f78c6b' ],
	},
	ocean: {
		label: __( 'Ocean', 'statista-charts' ),
		colors: [ '#0ea5e9', '#2563eb', '#14b8a6', '#6366f1', '#22d3ee', '#3b82f6', '#0d9488', '#818cf8' ],
	},
	sunset: {
		label: __( 'Sunset', 'statista-charts' ),
		colors: [ '#f97316', '#ef4444', '#f59e0b', '#db2777', '#fb7185', '#facc15', '#c2410c', '#9d174d' ],
	},
	forest: {
		label: __( 'Forest', 'statista-charts' ),
		colors: [ '#16a34a', '#65a30d', '#0d9488', '#4d7c0f', '#22c55e', '#84cc16', '#047857', '#a3e635' ],
	},
	grape: {
		label: __( 'Grape', 'statista-charts' ),
		colors: [ '#7c3aed', '#a855f7', '#d946ef', '#6366f1', '#c084fc', '#e879f9', '#4f46e5', '#f0abfc' ],
	},
	mono: {
		label: __( 'Monochrome', 'statista-charts' ),
		colors: [ '#111827', '#374151', '#4b5563', '#6b7280', '#9ca3af', '#c0c6cf', '#d1d5db', '#e5e7eb' ],
	},
};

export function getPalettes() {
	const injected = window.statistaCharts && window.statistaCharts.palettes;

	return injected && Object.keys( injected ).length ? injected : FALLBACK_PALETTES;
}

export function getColors( slug ) {
	const palettes = getPalettes();
	const palette = palettes[ slug ] || palettes.statista || FALLBACK_PALETTES.statista;

	return palette.colors;
}
