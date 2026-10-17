import { createPortal } from 'react-dom';

/**
 * Renders its children into <body>.
 *
 * Use it for every modal/overlay: the admin page container is animated (it has a
 * transform), and a `position: fixed` element inside a transformed ancestor is
 * positioned against that ancestor instead of the screen — the dialog then scrolls
 * away with the page and its buttons fall below the fold.
 */
export default function Portal({ children }) {
    return createPortal(children, document.body);
}
