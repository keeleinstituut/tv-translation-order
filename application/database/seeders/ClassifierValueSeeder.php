<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class ClassifierValueSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->call([
            TranslationDomainClassifierValueSeeder::class,
            FileTypeClassifierValueSeeder::class,
            LanguageClassifierValueSeeder::class,
            ProjectTypeClassifierValueSeeder::class,
        ]);
    }
}
