import { Link, usePage } from '@inertiajs/react';

import { Button } from '@/components/ui/button';

/** Title, the three settings tabs, and the green / red message banners for every settings page. */
export default function SettingsHeader({ title, isSuperAdmin }: { title: string; isSuperAdmin: boolean }) {
    const { url, props } = usePage<{ flash?: { success?: string }; errors?: Record<string, string> }>();

    const tabs = [
        ...(isSuperAdmin
            ? [
                  { href: '/settings/roles', label: 'Roles & permissions' },
                  { href: '/settings/users', label: 'Users' },
              ]
            : []),
        { href: '/settings/classes', label: 'Classes & streams' },
    ];

    return (
        <div className="flex flex-col gap-4">
            <h1 className="text-xl font-semibold">{title}</h1>

            <div className="flex flex-wrap gap-2 border-b pb-3">
                {tabs.map((t) => (
                    <Button key={t.href} asChild size="sm" variant={url.startsWith(t.href) ? 'default' : 'outline'}>
                        <Link href={t.href}>{t.label}</Link>
                    </Button>
                ))}
            </div>

            {props.flash?.success && (
                <div className="rounded-md border border-green-600/30 bg-green-50 p-3 text-sm text-green-800 dark:bg-green-950 dark:text-green-200">
                    {props.flash.success}
                </div>
            )}

            {/* Problems with an action such as a delete come back under the key "action". */}
            {props.errors?.action && (
                <div className="rounded-md border border-destructive/40 p-3 text-sm text-destructive">{props.errors.action}</div>
            )}
        </div>
    );
}
