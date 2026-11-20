/**
 * Drag-to-swap wrapper around the dashboard KPI cards.
 *
 * Dropping a card onto another card exchanges those two. The rest stay put.
 */
import { useRef, useState } from '@wordpress/element';
import { Button } from '@wordpress/components';
import { __ } from '@wordpress/i18n';

import KpiCard from './kpi-card';

const EMPTY_KPI = { icon: '', label: '', value: '', icon2: '', value2: '' };

export function swapItems( items, from, to ) {
	if (
		from === to ||
		from < 0 ||
		to < 0 ||
		from >= items.length ||
		to >= items.length
	) {
		return items;
	}

	const next = items.slice();
	const held = next[ from ];
	next[ from ] = next[ to ];
	next[ to ] = held;

	return next;
}

function indexAtPoint( list, clientX, clientY ) {
	const cards = list ? [ ...list.querySelectorAll( '.sd-kpi--editing' ) ] : [];

	for ( let index = 0; index < cards.length; index++ ) {
		const rect = cards[ index ].getBoundingClientRect();
		const inside =
			clientX >= rect.left &&
			clientX <= rect.right &&
			clientY >= rect.top &&
			clientY <= rect.bottom;

		if ( inside ) {
			return index;
		}
	}

	return null;
}

export default function KpiList( { kpis = [], layout = 'row', rtl = false, onChange } ) {
	const listRef = useRef( null );
	const dragRef = useRef( null );
	const [ drag, setDrag ] = useState( null );

	const swap = ( from, to ) => {
		const next = swapItems( kpis, from, to );

		if ( next !== kpis ) {
			onChange( next );
		}
	};

	const endDrag = ( event ) => {
		const current = dragRef.current;

		if ( ! current ) {
			return;
		}

		if ( event && event.currentTarget.releasePointerCapture ) {
			try {
				event.currentTarget.releasePointerCapture( event.pointerId );
			} catch ( error ) {
				// Capture was already released.
			}
		}

		if ( current.to !== null && current.to !== current.from ) {
			swap( current.from, current.to );
		}

		dragRef.current = null;
		setDrag( null );
	};

	const onHandlePointerDown = ( index, event ) => {
		if ( event.pointerType === 'mouse' && event.button !== 0 ) {
			return;
		}

		event.preventDefault();
		event.stopPropagation();
		event.currentTarget.setPointerCapture( event.pointerId );

		const next = { from: index, to: null };
		dragRef.current = next;
		setDrag( next );
	};

	const onHandlePointerMove = ( event ) => {
		if ( ! dragRef.current ) {
			return;
		}

		const to = indexAtPoint( listRef.current, event.clientX, event.clientY );
		const target = to === dragRef.current.from ? null : to;

		if ( target === dragRef.current.to ) {
			return;
		}

		const next = { ...dragRef.current, to: target };
		dragRef.current = next;
		setDrag( next );
	};

	return (
		<div
			ref={ listRef }
			className={ `sd-kpis${ drag ? ' sd-kpis--sorting' : '' }` }
		>
			{ kpis.map( ( kpi, index ) => (
				<KpiCard
					key={ index }
					kpi={ kpi }
					dragging={ drag && drag.from === index }
					dropTarget={ drag && drag.to === index }
					layout={ layout }
					rtl={ rtl }
					canMoveEarlier={ index > 0 }
					canMoveLater={ index < kpis.length - 1 }
					onChange={ ( next ) => onChange( kpis.map( ( item, i ) => ( i === index ? next : item ) ) ) }
					onRemove={ () => onChange( kpis.filter( ( item, i ) => i !== index ) ) }
					onMoveEarlier={ () => swap( index, index - 1 ) }
					onMoveLater={ () => swap( index, index + 1 ) }
					onDragHandlePointerDown={ ( event ) => onHandlePointerDown( index, event ) }
					onDragHandlePointerMove={ onHandlePointerMove }
					onDragHandlePointerUp={ endDrag }
					onDragHandlePointerCancel={ endDrag }
				/>
			) ) }

			<Button
				className="sd-kpi sd-kpi--add"
				icon="plus-alt2"
				onClick={ () => onChange( [ ...kpis, { ...EMPTY_KPI } ] ) }
			>
				{ __( 'Add a metric card', 'statista-charts' ) }
			</Button>
		</div>
	);
}
