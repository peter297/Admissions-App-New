import { usePage } from '@inertiajs/react';

/**
 * The logged-in user's permissions, shared from HandleInertiaRequests.
 * It fails closed: if the permissions are missing for any reason, the user can do nothing,
 * so a mistake hides things instead of showing buttons that end in a 403.
 */
export function useCan() {
    const { auth } = usePage<{ auth?: { permissions?: string[] } }>().props;
    const permissions = auth?.permissions ?? [];

    return (permission: string) => permissions.includes(permission);
}
