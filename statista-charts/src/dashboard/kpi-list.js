/**
 * KPI cards sit in a grid of slots. Dragging a card onto another card swaps
 * those two. Dragging it onto an empty slot (or the empty-space pad) moves it
 * and leaves a hole behind, so the other cards stay put.
 */
import { useRef, useState } from '@wordpress/element';
import { Button } from '@wordpress/components';
import { __ } from '@wordpress/i18n';

import KpiCard from './kpi-card';

const EMPTY_KPI = { icon: '', label: '', value: '', icon2: '', value2: '' };
const EMPTY_SLOT = { empty: true };

export function isEmptySlot( item ) {
	return Boolean( item && item.empty );
}

/**
 * Last slot on an incomplete row: remainder 1 when counting columns.
 * Auto (perRow 0) is treated as two cards per row.
 *
 * @param {number}  index
 * @param {number}  count
 * @param {number}  perRow
 * @param {boolean} oddWide
 * @param {string}  layout
 * @return {boolean}
 */
export function isLeftoverWide( index, count, perRow, oddWide, layout ) {
	if ( layout === 'column' || ! oddWide || count < 1 ) {
		return false;
	}

	const cols = perRow >= 1 ? perRow : 2;

	return index === count - 1 && ( index + 1 ) % cols === 1;
}

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

/**
 * Move a filled card to `to`. If `to` equals `items.length`, park it in a new
 * slot at the end. The origin becomes an empty slot.
 *
 * @param {Array}  items
 * @param {number} from
 * @param {number} to
 * @return {Array}
 */
export function moveToSlot( items, from, to ) {
	if ( from < 0 || from >= items.length || isEmptySlot( items[ from ] ) ) {
		return items;
	}

	if ( to === items.length ) {
		const next = items.slice();
		next[ from ] = { ...EMPTY_SLOT };
		next.push( items[ from ] );
		return next;
	}

	return swapItems( items, from, to );
}

function indexAtPoint( list, clientX, clientY ) {
	const cells = list ? [ ...list.querySelectorAll( '[data-kpi-index]' ) ] : [];

	for ( let i = 0; i < cells.length; i++ ) {
		const rect = cells[ i ].getBoundingClientRect();
		const inside =
			clientX >= rect.left &&
			clientX <= rect.right &&
			clientY >= rect.top &&
			clientY <= rect.bottom;

		if ( inside ) {
			return Number( cells[ i ].dataset.kpiIndex );
		}
	}

	return null;
}

function EmptySlot( { slotIndex, dropTarget, onFill, onRemove, wide = false } ) {
	return (
		<div
			className={ `sd-kpi sd-kpi--slot${ wide ? ' sd-kpi--wide' : '' }${ dropTarget ? ' sd-kpi--drop-target' : '' }` }
			data-kpi-index={ slotIndex }
		>
			<span className="sd-kpi__slot-label">
				{ __( 'Empty space', 'statista-charts' ) }
			</span>
			<div className="sd-kpi__toolbar">
				<Button
					size="small"
					icon="plus-alt2"
					label={ __( 'Add a card here', 'statista-charts' ) }
					showTooltip
					onClick={ onFill }
				/>
				<Button
					size="small"
					icon="no-alt"
					label={ __( 'Remove empty space', 'statista-charts' ) }
					showTooltip
					onClick={ onRemove }
				/>
			</div>
		</div>
	);
}

export default function KpiList( { kpis = [], layout = 'row', rtl = false, perRow = 0, oddWide = false, onChange } ) {
	const listRef = useRef( null );
	const dragRef = useRef( null );
	const [ drag, setDrag ] = useState( null );

	const place = ( from, to ) => {
		const next = moveToSlot( kpis, from, to );

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
			place( current.from, current.to );
		}

		dragRef.current = null;
		setDrag( null );
	};

	const onHandlePointerDown = ( index, event ) => {
		if ( event.pointerType === 'mouse' && event.button !== 0 ) {
			return;
		}

		if ( isEmptySlot( kpis[ index ] ) ) {
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
			{ kpis.map( ( kpi, index ) => {
				const leftover = isLeftoverWide( index, kpis.length, perRow, oddWide, layout );
				const wide = Boolean( kpi.wide ) || leftover;

				return isEmptySlot( kpi ) ? (
					<EmptySlot
						key={ index }
						slotIndex={ index }
						wide={ leftover }
						dropTarget={ drag && drag.to === index }
						onFill={ () => onChange( kpis.map( ( item, i ) => ( i === index ? { ...EMPTY_KPI } : item ) ) ) }
						onRemove={ () => onChange( kpis.filter( ( item, i ) => i !== index ) ) }
					/>
				) : (
					<KpiCard
						key={ index }
						slotIndex={ index }
						kpi={ kpi }
						wide={ wide }
						dragging={ drag && drag.from === index }
						dropTarget={ drag && drag.to === index }
						layout={ layout }
						rtl={ rtl }
						canMoveEarlier={ index > 0 }
						canMoveLater={ index < kpis.length - 1 }
						onChange={ ( next ) => onChange( kpis.map( ( item, i ) => ( i === index ? next : item ) ) ) }
						onRemove={ () => onChange( kpis.filter( ( item, i ) => i !== index ) ) }
						onMoveEarlier={ () => place( index, index - 1 ) }
						onMoveLater={ () => place( index, index + 1 ) }
						onDragHandlePointerDown={ ( event ) => onHandlePointerDown( index, event ) }
						onDragHandlePointerMove={ onHandlePointerMove }
						onDragHandlePointerUp={ endDrag }
						onDragHandlePointerCancel={ endDrag }
					/>
				);
			} ) }

			<div
				className={ `sd-kpi sd-kpi--slot sd-kpi--slot-end${ drag && drag.to === kpis.length ? ' sd-kpi--drop-target' : '' }` }
				data-kpi-index={ kpis.length }
			>
				<span className="sd-kpi__slot-label">
					{ __( 'Empty space', 'statista-charts' ) }
				</span>
				<Button
					className="sd-kpi__slot-add"
					size="small"
					variant="tertiary"
					onClick={ () => onChange( [ ...kpis, { ...EMPTY_SLOT } ] ) }
				>
					{ __( 'Add empty space', 'statista-charts' ) }
				</Button>
			</div>

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
