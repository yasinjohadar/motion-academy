export default function AdminPageCard({ toolbar = null, children, className = '' }) {
    return (
        <div className={`admin-page-card ${className}`.trim()}>
            {toolbar && <div className="card-toolbar">{toolbar}</div>}
            {children}
        </div>
    );
}
