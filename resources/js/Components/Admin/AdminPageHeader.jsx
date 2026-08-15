export default function AdminPageHeader({ title, description, actions = null }) {
    return (
        <div className="admin-page-header">
            <div className="page-title-wrap">
                <h1>{title}</h1>
                {description && <p>{description}</p>}
            </div>
            {actions}
        </div>
    );
}
