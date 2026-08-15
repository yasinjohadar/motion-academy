import { useForm } from '@inertiajs/react';
import { useRef } from 'react';

export default function UpdatePasswordForm() {
    const passwordInput = useRef();
    const currentPasswordInput = useRef();

    const {
        data,
        setData,
        errors,
        put,
        reset,
        processing,
        recentlySuccessful,
    } = useForm({
        current_password: '',
        password: '',
        password_confirmation: '',
    });

    const updatePassword = (e) => {
        e.preventDefault();

        put(route('password.update'), {
            preserveScroll: true,
            onSuccess: () => reset(),
            onError: (formErrors) => {
                if (formErrors.password) {
                    reset('password', 'password_confirmation');
                    passwordInput.current?.focus();
                }

                if (formErrors.current_password) {
                    reset('current_password');
                    currentPasswordInput.current?.focus();
                }
            },
        });
    };

    return (
        <form onSubmit={updatePassword}>
            <div className="mb-3">
                <label htmlFor="current_password" className="form-label">
                    كلمة المرور الحالية
                </label>
                <input
                    id="current_password"
                    ref={currentPasswordInput}
                    value={data.current_password}
                    onChange={(e) =>
                        setData('current_password', e.target.value)
                    }
                    type="password"
                    className={`form-control${errors.current_password ? ' is-invalid' : ''}`}
                    autoComplete="current-password"
                />
                {errors.current_password && (
                    <div className="invalid-feedback">
                        {errors.current_password}
                    </div>
                )}
            </div>

            <div className="mb-3">
                <label htmlFor="password" className="form-label">
                    كلمة المرور الجديدة
                </label>
                <input
                    id="password"
                    ref={passwordInput}
                    value={data.password}
                    onChange={(e) => setData('password', e.target.value)}
                    type="password"
                    className={`form-control${errors.password ? ' is-invalid' : ''}`}
                    autoComplete="new-password"
                />
                {errors.password && (
                    <div className="invalid-feedback">{errors.password}</div>
                )}
            </div>

            <div className="mb-3">
                <label htmlFor="password_confirmation" className="form-label">
                    تأكيد كلمة المرور
                </label>
                <input
                    id="password_confirmation"
                    value={data.password_confirmation}
                    onChange={(e) =>
                        setData('password_confirmation', e.target.value)
                    }
                    type="password"
                    className={`form-control${errors.password_confirmation ? ' is-invalid' : ''}`}
                    autoComplete="new-password"
                />
                {errors.password_confirmation && (
                    <div className="invalid-feedback">
                        {errors.password_confirmation}
                    </div>
                )}
            </div>

            <div className="d-flex align-items-center gap-3">
                <button
                    type="submit"
                    className="btn btn-primary"
                    disabled={processing}
                >
                    حفظ
                </button>
                {recentlySuccessful && (
                    <span className="small text-success">تم الحفظ.</span>
                )}
            </div>
        </form>
    );
}
