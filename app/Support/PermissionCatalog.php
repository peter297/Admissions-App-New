<?php

namespace App\Support;

/** Plain-English names for the permissions, grouped the way the roles screen shows them. */
class PermissionCatalog
{
    /** Permissions that exist but are not offered on the roles screen. */
    public const HIDDEN = ['users.manage'];

    public static function groups(): array
    {
        $group = fn (string $label, array $items) => [
            'label' => $label,
            'items' => collect($items)->map(fn ($text, $name) => ['name' => $name, 'label' => $text])->values()->all(),
        ];

        return [
            $group('Dashboard', ['dashboard.view' => 'View the dashboard']),
            $group('Learners', [
                'learners.view' => 'Find and view learners',
                'learners.create' => 'Add learners',
                'learners.edit' => 'Edit learners',
                'learners.move' => 'Move learners between classes',
                'learners.import' => 'Import learners from Excel',
                'learners.export' => 'Download class lists and alumni lists',
            ]),
            $group('Classes', ['classes.view' => 'View classes']),
            $group('Admissions', [
                'admissions.view' => 'View applicants',
                'admissions.manage' => 'Add, edit and delete applicants',
                'admissions.promote' => 'Admit applicants into a class',
                'admissions.export' => 'Download admissions reports',
            ]),
            $group('Alumni', ['alumni.view' => 'View alumni']),
            $group('History and reports', [
                'history.view' => 'View historical data (learners who left)',
                'reports.view' => 'Generate and download reports',
            ]),
            $group('Transport', [
                'transport.view' => 'View buses and routes',
                'transport.manage' => 'Add, edit and delete buses and routes',
                'transport.export' => 'Download transport lists',
            ]),
            $group('Administration', [
                'academic-years.manage' => 'Manage academic years and the year-end rollover',
                'settings.manage' => 'Manage classes and streams',
            ]),
        ];
    }

    public static function names(): array
    {
        return collect(self::groups())->pluck('items')->flatten(1)->pluck('name')->all();
    }
}
