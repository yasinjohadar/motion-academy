export default function Footer() {
    return (
        <footer className="footer mt-auto py-3 bg-white text-center">
            <div className="container">
                <span className="text-muted">
                    © <span id="year">{new Date().getFullYear()}</span>{' '}
                    جميع الحقوق محفوظة — تطوير{' '}
                    <span className="fw-semibold text-dark">ياسين جوخدار</span>
                    {' · '}
                    <span className="fw-semibold text-primary">
                        شركة كلاودسوفت للحلول البرمجية والتقنية
                    </span>
                </span>
            </div>
        </footer>
    );
}
