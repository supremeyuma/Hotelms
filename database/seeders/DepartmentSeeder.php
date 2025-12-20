<?php
// database/seeders/DepartmentSeeder.php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Department;

class DepartmentSeeder extends Seeder
{
    public function run(): void
    {
        collect([
            'Administration',
            'Front Desk',
            'Security',
            'Cleaning',
            'Kitchen'
        ])->each(fn ($name) =>
            Department::firstOrCreate(['name' => $name])
        );
    }
}
