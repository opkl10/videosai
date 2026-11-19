/**
 * Ready-made starting points, so nobody has to face an empty chart.
 */
import { __ } from '@wordpress/i18n';

export function getExample( type ) {
	if ( type === 'pie' || type === 'doughnut' ) {
		return [
			`${ __( 'Mobile', 'statista-charts' ) }: 58`,
			`${ __( 'Desktop', 'statista-charts' ) }: 21`,
			`${ __( 'Tablet', 'statista-charts' ) }: 21`,
		].join( '\n' );
	}

	return [
		`${ __( 'January', 'statista-charts' ) }: 120`,
		`${ __( 'February', 'statista-charts' ) }: 138`,
		`${ __( 'March', 'statista-charts' ) }: 151`,
	].join( '\n' );
}

export function getTemplates() {
	return [
		{
			type: 'bar',
			name: __( 'Quarterly comparison', 'statista-charts' ),
			attributes: {
				type: 'bar',
				title: __( 'Views per quarter', 'statista-charts' ),
				labels: [
					__( 'Q1', 'statista-charts' ),
					__( 'Q2', 'statista-charts' ),
					__( 'Q3', 'statista-charts' ),
					__( 'Q4', 'statista-charts' ),
				],
				series: [
					{ label: __( 'This year', 'statista-charts' ), data: [ 38, 52, 61, 74 ] },
					{ label: __( 'Last year', 'statista-charts' ), data: [ 31, 44, 47, 58 ] },
				],
			},
		},
		{
			type: 'line',
			name: __( 'Monthly trend', 'statista-charts' ),
			attributes: {
				type: 'line',
				title: __( 'Monthly active users', 'statista-charts' ),
				labels: [
					__( 'January', 'statista-charts' ),
					__( 'February', 'statista-charts' ),
					__( 'March', 'statista-charts' ),
					__( 'April', 'statista-charts' ),
					__( 'May', 'statista-charts' ),
					__( 'June', 'statista-charts' ),
				],
				series: [ { label: '', data: [ 120, 138, 151, 166, 181, 204 ] } ],
			},
		},
		{
			type: 'doughnut',
			name: __( 'Share of total', 'statista-charts' ),
			attributes: {
				type: 'doughnut',
				title: __( 'Share by platform', 'statista-charts' ),
				suffix: '%',
				labels: [
					__( 'Mobile', 'statista-charts' ),
					__( 'Desktop', 'statista-charts' ),
					__( 'Smart TV', 'statista-charts' ),
					__( 'Tablet', 'statista-charts' ),
				],
				series: [ { label: __( 'Share', 'statista-charts' ), data: [ 58, 21, 14, 7 ] } ],
			},
		},
		{
			type: 'stat',
			name: __( 'Single number', 'statista-charts' ),
			attributes: {
				type: 'stat',
				title: __( 'Yearly growth', 'statista-charts' ),
				statValue: '27.4',
				suffix: '%',
				statLabel: __( 'Growth versus last year', 'statista-charts' ),
				statDelta: __( '5.2 points', 'statista-charts' ),
				statTrend: 'up',
			},
		},
	];
}
