<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class SuperAdminAndDemoUserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     * Idempoten: menggunakan updateOrCreate berbasis email.
     */
    public function run(): void
    {
        // 1. Super Admin
        User::updateOrCreate(
            ['email' => 'admin@gianyarplanner.test'],
            [
                'name' => 'Super Admin Gianyar',
                'nama' => 'Super Admin Gianyar',
                'no_hp' => '081234567890',
                'password' => Hash::make('password123'),
                'role' => 'super_admin',
                'status' => 'active',
                'email_verified_at' => now(),
            ]
        );

        // 2. Demo Users (Wisatawan)
        $demoUsers = [
            [
                'name' => 'Budi Santoso',
                'nama' => 'Budi Santoso',
                'email' => 'user1@gianyarplanner.test',
                'no_hp' => '081298760001',
            ],
            [
                'name' => 'Siti Rahmawati',
                'nama' => 'Siti Rahmawati',
                'email' => 'user2@gianyarplanner.test',
                'no_hp' => '081298760002',
            ],
            [
                'name' => 'David Miller',
                'nama' => 'David Miller',
                'email' => 'user3@gianyarplanner.test',
                'no_hp' => '081298760003',
            ],
            [
                'name' => 'Ni Kadek Arisanti',
                'nama' => 'Ni Kadek Arisanti',
                'email' => 'user4@gianyarplanner.test',
                'no_hp' => '081298760004',
            ],
            [
                'name' => 'Eko Prasetyo',
                'nama' => 'Eko Prasetyo',
                'email' => 'user5@gianyarplanner.test',
                'no_hp' => '081298760005',
            ],
        ];

        foreach ($demoUsers as $u) {
            User::updateOrCreate(
                ['email' => $u['email']],
                [
                    'name' => $u['name'],
                    'nama' => $u['nama'],
                    'no_hp' => $u['no_hp'],
                    'password' => Hash::make('password123'),
                    'role' => 'user',
                    'status' => 'active',
                    'email_verified_at' => now(),
                ]
            );
        }
    }
}
