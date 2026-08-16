/**
 * The "nothing here" row inside an admin table.
 *
 * Twenty-one tables had one of these and they had drifted into two different looks:
 * eight used `dashboard-table-empty`, thirteen used `text-center text-muted py-4`.
 * Same meaning, two appearances, depending on which page someone copied from.
 *
 * `colSpan` has to be passed because it must match the header of the table it sits
 * in — there is no way to infer it, and getting it wrong leaves the cell floating in
 * the first column.
 */
export default function TableEmptyRow({ colSpan, icon = null, children }) {
    return (
        <tr>
            <td colSpan={colSpan} className="dashboard-table-empty">
                {icon && <i className={icon} />}
                {children}
            </td>
        </tr>
    );
}
