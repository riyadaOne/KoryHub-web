<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Données de base (catégories), puis données de démonstration (DON-09), construites module
     * par module ; la démonstration n'est jamais lancée en production (DemoUsersSeeder refuse).
     */
    public function run(): void
    {
        $this->call([
            CategoriesSeeder::class,
            DemoUsersSeeder::class,
            DemoModulesSeeder::class,
        ]);
    }
}
