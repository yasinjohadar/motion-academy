import Portal from '@/Components/Admin/Portal';
import { useForm } from '@inertiajs/react';
import { useRef, useState } from 'react';

export default function DeleteUserForm() {
    const [confirmingUserDeletion, setConfirmingUserDeletion] = useState(false);
    const passwordInput = useRef();

    const {
        data,
        setData,
        delete: destroy,
        processing,
        reset,
        errors,
        clearErrors,
    } = useForm({
        password: '',
    });

    const closeModal = () => {
        setConfirmingUserDeletion(false);
        clearErrors();
        reset();
    };

    const deleteUser = (e) => {
        e.preventDefault();

        destroy(route('admin.profile.destroy'), {
            preserveScroll: true,
            onSuccess: () => closeModal(),
            onError: () => passwordInput.current?.focus(),
            onFinish: () => reset(),
        });
    };

    return (
        <div>
            <p className="text-secondary small mb-3">
                عند حذف الحساب سيتم حذف جميع بياناتك نهائياً. تأكد قبل المتابعة.
            </p>

            <button type="button" className="btn btn-danger" onClick={() => setConfirmingUserDeletion(true)}>
                حذف الحساب
            </button>

            {confirmingUserDeletion && (
                <Portal>
                    <div
                        className="modal fade show d-block"
                        tabIndex="-1"
                        role="dialog"
                        style={{ background: 'rgba(15, 23, 42, 0.45)' }}
                    >
                        <div className="modal-dialog modal-dialog-centered">
                            <div className="modal-content">
                                <form onSubmit={deleteUser}>
                                    <div className="modal-header">
                                        <h5 className="modal-title">تأكيد حذف الحساب</h5>
                                        <button
                                            type="button"
                                            className="btn-close"
                                            aria-label="إغلاق"
                                            onClick={closeModal}
                                        />
                                    </div>
                                    <div className="modal-body">
                                        <p className="small text-secondary">
                                            أدخل كلمة المرور لتأكيد حذف الحساب نهائياً.
                                        </p>
                                        <label htmlFor="password" className="form-label">
                                            كلمة المرور
                                        </label>
                                        <input
                                            id="password"
                                            type="password"
                                            name="password"
                                            ref={passwordInput}
                                            value={data.password}
                                            onChange={(e) => setData('password', e.target.value)}
                                            className={`form-control${errors.password ? ' is-invalid' : ''}`}
                                            autoFocus
                                        />
                                        {errors.password && (
                                            <div className="invalid-feedback">{errors.password}</div>
                                        )}
                                    </div>
                                    <div className="modal-footer">
                                        <button
                                            type="button"
                                            className="btn btn-outline-secondary"
                                            onClick={closeModal}
                                        >
                                            إلغاء
                                        </button>
                                        <button
                                            type="submit"
                                            className="btn btn-danger"
                                            disabled={processing}
                                        >
                                            حذف الحساب
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                </Portal>
            )}
        </div>
    );
}
