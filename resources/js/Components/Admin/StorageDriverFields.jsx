export const emptyDriverConfig = (driver = 'local') => {
    switch (driver) {
        case 's3':
            return {
                access_key_id: '',
                secret_access_key: '',
                region: 'us-east-1',
                bucket: '',
                url: '',
                endpoint: '',
                use_path_style: false,
            };
        case 'cloudflare_r2':
            return {
                account_id: '',
                access_key_id: '',
                secret_access_key: '',
                bucket: '',
                public_url: '',
            };
        case 'idrive_e2':
            return {
                access_key_id: '',
                secret_access_key: '',
                region: 'us-east-1',
                bucket: '',
                endpoint: '',
                url: '',
                use_path_style: true,
            };
        case 'local':
        default:
            return { path: 'public' };
    }
};

export default function StorageDriverFields({
    driver,
    config = {},
    errors = {},
    onChange,
    editing = false,
    hasSecretAccessKey = false,
}) {
    const setField = (key, value) => {
        onChange({ ...config, [key]: value });
    };

    if (driver === 'local') {
        return (
            <div className="col-md-6">
                <label className="form-label">المسار داخل storage/app</label>
                <input
                    type="text"
                    className={`form-control${errors['config.path'] ? ' is-invalid' : ''}`}
                    value={config.path || 'public'}
                    onChange={(e) => setField('path', e.target.value)}
                    placeholder="public"
                />
                {errors['config.path'] && (
                    <div className="invalid-feedback">{errors['config.path']}</div>
                )}
            </div>
        );
    }

    return (
        <>
            {driver === 'cloudflare_r2' && (
                <div className="col-md-6">
                    <label className="form-label">Account ID</label>
                    <input
                        type="text"
                        className={`form-control${errors['config.account_id'] ? ' is-invalid' : ''}`}
                        value={config.account_id || ''}
                        onChange={(e) => setField('account_id', e.target.value)}
                    />
                    {errors['config.account_id'] && (
                        <div className="invalid-feedback">{errors['config.account_id']}</div>
                    )}
                </div>
            )}

            <div className="col-md-6">
                <label className="form-label">Access Key ID</label>
                <input
                    type="text"
                    className={`form-control${errors['config.access_key_id'] ? ' is-invalid' : ''}`}
                    value={config.access_key_id || ''}
                    onChange={(e) => setField('access_key_id', e.target.value)}
                    placeholder={editing ? 'اتركه كما هو أو أدخل مفتاحاً جديداً' : ''}
                    autoComplete="off"
                />
                {errors['config.access_key_id'] && (
                    <div className="invalid-feedback">{errors['config.access_key_id']}</div>
                )}
            </div>

            <div className="col-md-6">
                <label className="form-label">Secret Access Key</label>
                <input
                    type="password"
                    className={`form-control${errors['config.secret_access_key'] ? ' is-invalid' : ''}`}
                    value={config.secret_access_key || ''}
                    onChange={(e) => setField('secret_access_key', e.target.value)}
                    placeholder={
                        editing && hasSecretAccessKey
                            ? 'اتركه فارغاً للإبقاء على السر الحالي'
                            : ''
                    }
                    autoComplete="new-password"
                />
                {errors['config.secret_access_key'] && (
                    <div className="invalid-feedback">{errors['config.secret_access_key']}</div>
                )}
            </div>

            {driver !== 'cloudflare_r2' && (
                <div className="col-md-6">
                    <label className="form-label">Region</label>
                    <input
                        type="text"
                        className={`form-control${errors['config.region'] ? ' is-invalid' : ''}`}
                        value={config.region || ''}
                        onChange={(e) => setField('region', e.target.value)}
                        placeholder="us-east-1"
                    />
                    {errors['config.region'] && (
                        <div className="invalid-feedback">{errors['config.region']}</div>
                    )}
                </div>
            )}

            <div className="col-md-6">
                <label className="form-label">Bucket</label>
                <input
                    type="text"
                    className={`form-control${errors['config.bucket'] ? ' is-invalid' : ''}`}
                    value={config.bucket || ''}
                    onChange={(e) => setField('bucket', e.target.value)}
                />
                {errors['config.bucket'] && (
                    <div className="invalid-feedback">{errors['config.bucket']}</div>
                )}
            </div>

            {driver === 'idrive_e2' && (
                <div className="col-md-12">
                    <label className="form-label">Endpoint (مطلوب)</label>
                    <input
                        type="url"
                        className={`form-control${errors['config.endpoint'] ? ' is-invalid' : ''}`}
                        value={config.endpoint || ''}
                        onChange={(e) => setField('endpoint', e.target.value)}
                        placeholder="https://xxxx.idrivee2.com"
                    />
                    {errors['config.endpoint'] && (
                        <div className="invalid-feedback">{errors['config.endpoint']}</div>
                    )}
                </div>
            )}

            {driver === 's3' && (
                <div className="col-md-6">
                    <label className="form-label">Endpoint (اختياري)</label>
                    <input
                        type="url"
                        className={`form-control${errors['config.endpoint'] ? ' is-invalid' : ''}`}
                        value={config.endpoint || ''}
                        onChange={(e) => setField('endpoint', e.target.value)}
                        placeholder="لتوافق MinIO وغيره"
                    />
                    {errors['config.endpoint'] && (
                        <div className="invalid-feedback">{errors['config.endpoint']}</div>
                    )}
                </div>
            )}

            {(driver === 's3' || driver === 'idrive_e2') && (
                <div className="col-md-6">
                    <label className="form-label">URL عام (اختياري)</label>
                    <input
                        type="url"
                        className={`form-control${errors['config.url'] ? ' is-invalid' : ''}`}
                        value={config.url || ''}
                        onChange={(e) => setField('url', e.target.value)}
                    />
                </div>
            )}

            {driver === 'cloudflare_r2' && (
                <div className="col-md-6">
                    <label className="form-label">Public URL (اختياري)</label>
                    <input
                        type="url"
                        className={`form-control${errors['config.public_url'] ? ' is-invalid' : ''}`}
                        value={config.public_url || ''}
                        onChange={(e) => setField('public_url', e.target.value)}
                        placeholder="https://cdn.example.com"
                    />
                </div>
            )}

            {(driver === 's3' || driver === 'idrive_e2') && (
                <div className="col-md-6 d-flex align-items-end">
                    <div className="form-check mb-2">
                        <input
                            id="use_path_style"
                            type="checkbox"
                            className="form-check-input"
                            checked={Boolean(config.use_path_style)}
                            onChange={(e) => setField('use_path_style', e.target.checked)}
                        />
                        <label className="form-check-label" htmlFor="use_path_style">
                            استخدام Path Style Endpoint
                        </label>
                    </div>
                </div>
            )}
        </>
    );
}
