/**
 * Drag-to-reorder wrapper around the dashboard KPI cards.
 */
import { useRef, useState } from '@wordpress/element';
import { Button } from '@wordpress/components';
import { __ } from '@wordpress/i18n';

import KpiCard from './kpi-card';

const EMPTY_KPI = { icon: '', label: '', value: '', icon2: '', value2: '' };

export function reorderItems( items, from, insertBefore ) {
	if ( from === insertBefore || from === insertBefore - 1 || from < 0 || insertBefore < 0 ) {
		return items;
	}

	const next = items.slice();
	const [ item ] = next.splice( from, 1 );
	const at = from < insertBefore ? insertBefore - 1 : insertBefore;
	next.splice( at, 0, item );

	return next;
}

function insertBeforeAtPoint( list, clientX, clientY, layout, rtl ) {
	const cards = list ? [ ...list.querySelectorAll( '.sd-kpi--editing' ) ] : [];

	if ( ! cards.length ) {
		return 0;
	}

	for ( let index = 0; index < cards.length; index++ ) {
		const rect = cards[ index ].getBoundingClientRect();
		const inside =
			clientX >= rect.left &&
			clientX <= rect.right &&
			clientY >= rect.top &&
			clientY <= rect.bottom;

		if ( ! inside ) {
			continue;
		}

		if ( layout === 'column' ) {
			return clientY > rect.top + rect.height / 2 ? index + 1 : index;
		}

		const after = rtl
			? clientX < rect.left + rect.width / 2
			: clientX > rect.left + rect.width / 2;

		return after ? index + 1 : index;
	}

	const last = cards[ cards.length - 1 ].getBoundingClientRect();

	if ( layout === 'column' && clientY > last.bottom ) {
		return cards.length;
	}

	if ( layout !== 'column' ) {
		if ( rtl && clientX < last.left ) {
			return cards.length;
		}

		if ( ! rtl && clientX > last.right ) {
			return cards.length;
		}
	}

	return null;
}

export default function KpiList( { kpis = [], layout = 'row', rtl = false, onChange } ) {
	const listRef = useRef( null );
	const dragRef = useRef( null );
	const [ drag, setDrag ] = useState( null );

	const move = ( from, insertBefore ) => {
		const next = reorderItems( kpis, from, insertBefore );

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

		move( current.from, current.insertBefore );
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

		const next = { from: index, insertBefore: index };
		dragRef.current = next;
		setDrag( next );
	};

	const onHandlePointerMove = ( event ) => {
		if ( ! dragRef.current ) {
			return;
		}

		const insertBefore = insertBeforeAtPoint(
			listRef.current,
			event.clientX,
			event.clientY,
			layout,
			rtl
		);

		if ( insertBefore === null || insertBefore === dragRef.current.insertBefore ) {
			return;
		}

		const next = { ...dragRef.current, insertBefore };
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
					dropBefore={ drag && drag.insertBefore === index }
					dropAfter={ drag && index === kpis.length - 1 && drag.insertBefore === kpis.length }
					layout={ layout }
					rtl={ rtl }
					canMoveEarlier={ index > 0 }
					canMoveLater={ index < kpis.length - 1 }
					onChange={ ( next ) => onChange( kpis.map( ( item, i ) => ( i === index ? next : item ) ) ) }
					onRemove={ () => onChange( kpis.filter( ( item, i ) => i !== index ) ) }
					onMoveEarlier={ () => move( index, index - 1 ) }
					onMoveLater={ () => move( index, index + 2 ) }
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
