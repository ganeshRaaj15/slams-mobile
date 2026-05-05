<?php

declare(strict_types=1);

namespace Config;

use CodeIgniter\Shield\Config\AuthGroups as ShieldAuthGroups;

class AuthGroups extends ShieldAuthGroups
{
    public string $defaultGroup = 'external';

    public array $groups = [

        'admin' => [
            'title'       => 'Administrator',
            'description' => 'Full access to system features.',
        ],

        'pic' => [
            'title'       => 'Person In Charge',
            'description' => 'Approves and manages laboratory bookings.',
        ],

        'manager' => [
            'title'       => 'Lab Manager',
            'description' => 'Second-stage approver for non-FKMP bookings.',
        ],

        'technician' => [
            'title'       => 'Technician',
            'description' => 'Manages maintenance and lab equipment.',
        ],

        'staff' => [
            'title'       => 'Staff',
            'description' => 'UTHM staff users.',
        ],

        'student' => [
            'title'       => 'Student',
            'description' => 'UTHM students.',
        ],

        'external' => [
            'title'       => 'External User',
            'description' => 'Public or non-UTHM external users.',
        ],
    ];

    public array $permissions = [
        'admin.access'        => 'Can access the sites admin area',
        'admin.settings'      => 'Can access the main site settings',
        'users.manage-admins' => 'Can manage other admins',
        'users.create'        => 'Can create new non-admin users',
        'users.edit'          => 'Can edit existing non-admin users',
        'users.delete'        => 'Can delete existing non-admin users',
        'beta.access'         => 'Can access beta-level features',
    ];

    public array $matrix = [
        'superadmin' => [
            'admin.*',
            'users.*',
            'beta.*',
        ],
        'admin' => [
            'admin.access',
            'users.create',
            'users.edit',
            'users.delete',
            'beta.access',
        ],
        'developer' => [
            'admin.access',
            'admin.settings',
            'users.create',
            'users.edit',
            'beta.access',
        ],
        'user' => [],
        'beta' => [
            'beta.access',
        ],
    ];
}
