<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RolesAndPermissionsSeeder extends Seeder
{
    /**
     * App permissions (spatie) + the super-admin account.
     * Sport roles (player/coach/scorer/fan) stay as profile tags
     * in users.sport_roles — they are display metadata, not permissions.
     */
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $permissions = [
            'access admin',
            'manage users',
            'manage flags',
            'manage tournaments',
            'moderate content',
        ];

        foreach ($permissions as $name) {
            Permission::findOrCreate($name, 'web');
        }

        $member = Role::findOrCreate('member', 'web');

        $superAdmin = Role::findOrCreate('super-admin', 'web');
        $superAdmin->syncPermissions($permissions);

        // Super admin — full access via Gate::before in AppServiceProvider.
        $admin = User::firstOrCreate(
            ['email' => 'admin@cricketamigo.com'],
            [
                'name' => 'Super Admin',
                'username' => 'admin',
                'password' => 'Password@123#',
                'onboarded_at' => now(),
            ],
        );

        $admin->syncRoles(['super-admin', 'member']);
    }
}
