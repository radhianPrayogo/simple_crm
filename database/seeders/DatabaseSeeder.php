<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Company;
use App\Models\Employee;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // create sample company
        $company = Company::create([
            'name' => 'PT Makmur Jaya',
            'email' => 'contact@makmurjaya.com',
            'phone' => '089222333444'
        ]);

        // create sample users
        $users = [
            [
                'email' => 'superadmin@simplecrm.com',
                'password' => Hash::make('S4dm!n123'),
                'name' => '',
                'phone' => ''
            ],
            [
                'email' => 'manager@simplecrm.com',
                'password' => Hash::make('M4n@ger!'),
                'name' => 'Manager',
                'phone' => '08822334455',
                'address' => 'Jl. Boulevard No.78'
            ],
            [
                'email' => 'employee@simplecrm.com',
                'password' => Hash::make('Empl0yee!'),
                'name' => 'Employee',
                'phone' => '08112234571',
                'address' => 'Jl. Simanjuntak No.112'
            ],
            [
                'email' => 'fellow@simplecrm.com',
                'password' => Hash::make('F@llow123'),
                'name' => 'Fellow',
                'phone' => '089123455689',
                'address' => 'Jl. Neptunus No.555'
            ]
        ];

        foreach ($users as $data) {
            $user = User::create([
                'email' => $data['email'],
                'password' => $data['password']
            ]);

            if ($data['name']) {
                $employee = Employee::create([
                    'user_id' => $user->id,
                    'company_id' => $company->id,
                    'name' => $data['name'],
                    'phone' => $data['phone'],
                    'address' => $data['address']
                ]);
            }
        }

        $this->call([
            RolesAndPermissionsSeeder::class
        ]);
    }
}
