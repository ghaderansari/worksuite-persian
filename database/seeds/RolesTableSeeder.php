<?php

use Illuminate\Database\Seeder;
use App\Role;
use App\User;
use Illuminate\Support\Facades\App;

class RolesTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */

    public function run() {
        $admin = new Role();
        $admin->name = 'admin';
        $admin->display_name = 'App Administrator'; // optional
        $admin->description = 'Admin is allowed to manage everything of the app.'; // optional
        $admin->save();

        $employee = new Role();
        $employee->name = 'employee';
        $employee->display_name = 'Employee'; // optional
        $employee->description = 'Employee can see tasks and projects assigned to him.'; // optional
        $employee->save();

        $client = new Role();
        $client->name = 'client';
        $client->display_name = 'Client'; // optional
        $client->description = 'Client can see own tasks and projects.'; // optional
        $client->save();

    }

}
