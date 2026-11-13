<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Données de démonstration (DON-09), construites module par module ; jamais en production.
     */
    public function run(): void
    {
        $this->call([
            DemoUsersSeeder::class,
        ]);
    }
}
