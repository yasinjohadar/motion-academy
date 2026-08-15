export default function AdminFlashAlerts({ flash }) {
    if (!flash?.success && !flash?.error && !flash?.warning) {
        return null;
    }

    return (
        <>
            {flash?.success && (
                <div className="alert alert-success alert-dismissible fade show" role="alert">
                    {flash.success}
                    <button type="button" className="btn-close" data-bs-dismiss="alert" aria-label="Close" />
                </div>
            )}
            {flash?.warning && (
                <div className="alert alert-warning alert-dismissible fade show" role="alert">
                    {flash.warning}
                    <button type="button" className="btn-close" data-bs-dismiss="alert" aria-label="Close" />
                </div>
            )}
            {flash?.error && (
                <div className="alert alert-danger alert-dismissible fade show" role="alert">
                    {flash.error}
                    <button type="button" className="btn-close" data-bs-dismiss="alert" aria-label="Close" />
                </div>
            )}
        </>
    );
}
