import { useEffect, useRef, useState } from 'react';

/** Move `dragId` to just before/after `targetId` within a list of ids. */
export function placeId(ids, dragId, targetId, position) {
    if (dragId === targetId || !ids.includes(dragId)) {
        return ids;
    }
    const rest = ids.filter((id) => id !== dragId);
    const index = rest.indexOf(targetId);
    if (index === -1) {
        return ids;
    }
    rest.splice(position === 'after' ? index + 1 : index, 0, dragId);
    return rest;
}

const sameOrder = (a, b) => a.join() === b.join();

/**
 * Native HTML5 drag-and-drop reordering of sibling items.
 *
 * - Only a pressed handle arms its row, so links/images inside a row never start a drag.
 * - Rows stay put while dragging; a drop marker (`drop-before` / `drop-after`) shows
 *   where the item will land, and the order changes only on drop. Moving DOM nodes
 *   mid-drag is what makes browsers cancel drags.
 * - Items are grouped (e.g. "sections", "section-5"); a drag only accepts targets from
 *   its own group. Nested groups work because handled events stop propagating.
 *
 * `onReorder(group, orderedIds, movedId)` is called once per successful drop.
 */
export default function useSortable({ onReorder }) {
    const dragging = useRef(null);
    const [armedKey, setArmedKey] = useState(null);
    const [draggingKey, setDraggingKey] = useState(null);
    const [draggingGroup, setDraggingGroup] = useState(null);
    const [dropTarget, setDropTarget] = useState(null);
    const [justMovedKey, setJustMovedKey] = useState(null);

    useEffect(() => {
        if (justMovedKey == null) {
            return undefined;
        }
        const timer = setTimeout(() => setJustMovedKey(null), 1400);
        return () => clearTimeout(timer);
    }, [justMovedKey]);

    useEffect(() => {
        const disarm = () => setArmedKey(null);
        window.addEventListener('mouseup', disarm);
        return () => window.removeEventListener('mouseup', disarm);
    }, []);

    const reset = () => {
        dragging.current = null;
        setArmedKey(null);
        setDraggingKey(null);
        setDraggingGroup(null);
        setDropTarget(null);
    };

    const keyOf = (group, id) => `${group}|${id}`;

    const handleProps = (id, group) => ({
        onMouseDown: () => setArmedKey(keyOf(group, id)),
    });

    /**
     * @param {number} id        the item's id
     * @param {string} group     sibling group key
     * @param {number[]} siblings ordered ids of the item's group
     */
    const itemProps = (id, group, siblings) => {
        const key = keyOf(group, id);
        const classes = [
            draggingKey === key && 'is-dragging',
            dropTarget?.key === key && `drop-${dropTarget.position}`,
            justMovedKey === key && 'just-moved',
        ].filter(Boolean);

        return {
            className: classes.join(' '),
            draggable: armedKey === key,
            onDragStart: (event) => {
                if (armedKey !== key) {
                    if (!dragging.current) {
                        event.preventDefault();
                    }
                    return;
                }
                event.stopPropagation();
                dragging.current = { id, group };
                event.dataTransfer.effectAllowed = 'move';
                event.dataTransfer.setData('text/plain', key);
                event.dataTransfer.setDragImage(event.currentTarget, 24, 24);
                // Changing the DOM inside dragstart makes browsers abort the drag, so defer it.
                setTimeout(() => {
                    setDraggingKey(key);
                    setDraggingGroup(group);
                }, 0);
            },
            onDragOver: (event) => {
                const drag = dragging.current;
                if (!drag || drag.group !== group) {
                    return; // let a parent group handle it, or keep the "not allowed" cursor
                }
                event.preventDefault();
                event.stopPropagation();
                event.dataTransfer.dropEffect = 'move';

                const rect = event.currentTarget.getBoundingClientRect();
                const position = event.clientY < rect.top + rect.height / 2 ? 'before' : 'after';
                const isNoop = sameOrder(siblings, placeId(siblings, drag.id, id, position));
                const next = isNoop ? null : { key, id, position };

                if (next?.key !== dropTarget?.key || next?.position !== dropTarget?.position) {
                    setDropTarget(next);
                }
            },
            onDrop: (event) => {
                const drag = dragging.current;
                if (!drag || drag.group !== group) {
                    return;
                }
                event.preventDefault();
                event.stopPropagation();
                const target = dropTarget;
                reset();
                if (!target) {
                    return;
                }
                const reordered = placeId(siblings, drag.id, target.id, target.position);
                if (!sameOrder(siblings, reordered)) {
                    setJustMovedKey(keyOf(group, drag.id));
                    onReorder(group, reordered, drag.id);
                }
            },
            onDragEnd: reset,
        };
    };

    const containerProps = {
        onDragLeave: (event) => {
            if (!event.currentTarget.contains(event.relatedTarget)) {
                setDropTarget(null);
            }
        },
    };

    return {
        handleProps,
        itemProps,
        containerProps,
        draggingGroup,
        isDragging: draggingKey !== null,
    };
}
