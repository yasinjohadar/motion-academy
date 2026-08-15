import { usePage } from '@inertiajs/react';

export default function useCan() {
    const { auth } = usePage().props;

    const can = (permission) => {
        if (auth?.is_admin) {
            return true;
        }

        return Boolean(auth?.permissions?.includes(permission));
    };

    const canAny = (permissions = []) => permissions.some((permission) => can(permission));

    return { can, canAny, isAdmin: Boolean(auth?.is_admin) };
}
