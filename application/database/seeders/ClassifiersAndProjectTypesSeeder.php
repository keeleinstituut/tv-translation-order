<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class ClassifiersAndProjectTypesSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->call([
            ClassifierValueSeeder::class,
            ProjectTypeConfigSeeder::class,
            JobDefinitionSeeder::class,
        ]);
    }
}
