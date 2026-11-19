/**
 * Live Chart.js preview inside the editor canvas.
 */
import { useEffect, useRef } from '@wordpress/element';
import { __ } from '@wordpress/i18n';

import { createChart } from '../lib/chart-factory';

export default function ChartPreview( { config } ) {
	const canvasRef = useRef( null );
	const chartRef = useRef( null );
	const signature = JSON.stringify( config );

	useEffect( () => {
		if ( chartRef.current ) {
			chartRef.current.destroy();
			chartRef.current = null;
		}

		chartRef.current = createChart( canvasRef.current, config );

		return () => {
			if ( chartRef.current ) {
				chartRef.current.destroy();
				chartRef.current = null;
			}
		};
		// eslint-disable-next-line react-hooks/exhaustive-deps
	}, [ signature ] );

	const hasData = ( config.labels || [] ).length > 0 && ( config.series || [] ).length > 0;

	if ( ! hasData ) {
		return <p className="sc-empty">{ __( 'Add rows in the Data tab to draw the chart.', 'statista-charts' ) }</p>;
	}

	return (
		<div className="sc-canvas-wrap" style={ { height: `${ config.height }px` } }>
			<canvas className="sc-canvas" ref={ canvasRef } />
		</div>
	);
}
