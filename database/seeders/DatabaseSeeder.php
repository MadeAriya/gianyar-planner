<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     * Idempoten: Semua sub-seeder menggunakan updateOrCreate.
     */
    public function run(): void
    {
        $this->call([
            DestinasiSeeder::class,
            SuperAdminAndDemoUserSeeder::class,
            TourGuideSeeder::class,
            GearCategoryAndMitraSeeder::class,
        ]);
    }
}
