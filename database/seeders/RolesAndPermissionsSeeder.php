<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use App\Models\User;

class RolesAndPermissionsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Reset cached roles and permissions
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        $roles = [
            'super-admin',
            'manager',
            'employee',
            'fellow'
        ];

        $actions = [
            'create',
            'view',
            'update',
            'delete'
        ];

        $manager_permissions = [];
        $employee_permissions = [];
        $fellow_permissions = [];

        // loop for register permissions
        foreach ($roles AS $role) {
            foreach ($actions AS $action) {
                if ($role != 'super-admin') {
                    $permission = Permission::create(['name' => $action.'.'.$role]);

                    // collect permissions by defined role and regulations
                    switch ($role) {
                        case 'manager':
                            array_push($manager_permissions, $permission);
                            break;
                        case 'employee':
                            array_push($manager_permissions, $permission);
                            break;
                        case 'fellow':
                            array_push($manager_permissions, $permission);
                            if (!in_array($action, ['create','update','delete'])) {
                                array_push($employee_permissions, $permission);
                            }
                            break;
                        default:
                            break;
                    }
                }
            }
        }

        // create permission for company data
        $company_permissions = [];
        foreach ($actions AS $action) {
            $permission = Permission::create(['name' => $action.'.company']);
            array_push($company_permissions, $permission);
        }

        // create roles and assign created permissions
        foreach ($roles AS $role_name) {
            $role = Role::create(['name' => $role_name]);
            switch ($role_name) {
                case 'employee':
                    $role->syncPermissions($employee_permissions);
                    break;
                case 'manager':
                    $role->syncPermissions($manager_permissions);
                    break;
                case 'super-admin':
                    $role->syncPermissions($company_permissions);
                    break;
                default:
                    break;
            }
        }

        $superadmin = User::where('email', 'superadmin@simplecrm.com')->first();
        if ($superadmin) {
            $superadmin->assignRole('super-admin');
        }

        $manager = User::where('email', 'manager@simplecrm.com')->first();
        if ($manager) {
            $manager->assignRole('manager');
        }

        $employee = User::where('email', 'employee@simplecrm.com')->first();
        if ($employee) {
            $employee->assignRole('employee');
        }

        $fellow = User::where('email', 'fellow@simplecrm.com')->first();
        if ($fellow) {
            $fellow->assignRole('fellow');
        }
    }
}
