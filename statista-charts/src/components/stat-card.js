/**
 * Editor preview of the single-number card. Mirrors the PHP markup.
 */
const ARROWS = {
	up: '▲',
	down: '▼',
	flat: '■',
};

function firstValue( config ) {
	const data = ( config.series[ 0 ] || {} ).data || [];
	const value = data.find( ( item ) => item !== null && item !== undefined );

	return value === undefined ? '—' : String( value );
}

export default function StatCard( { config } ) {
	const value = config.statValue !== '' ? config.statValue : firstValue( config );

	return (
		<div className="sc-stat">
			<div className="sc-stat-value" dir="ltr">
				{ config.prefix && <span className="sc-stat-affix">{ config.prefix }</span> }
				<span className="sc-stat-number">{ value }</span>
				{ config.suffix && <span className="sc-stat-affix">{ config.suffix }</span> }
			</div>
			{ config.statLabel && <div className="sc-stat-label">{ config.statLabel }</div> }
			{ config.statDelta && (
				<div className={ `sc-stat-delta sc-trend-${ config.statTrend }` }>
					<span className="sc-stat-arrow" aria-hidden="true">
						{ ARROWS[ config.statTrend ] || ARROWS.up }
					</span>
					<span>{ config.statDelta }</span>
				</div>
			) }
			{ config.statCaption && <p className="sc-stat-caption">{ config.statCaption }</p> }
		</div>
	);
}
