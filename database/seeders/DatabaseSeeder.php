<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $users = [
            ['name' => 'Administrator', 'username' => 'admin', 'role' => 'admin', 'prodi_key' => null, 'password' => env('SEED_ADMIN_PASSWORD', 'Admin@Arsip26')],
            ['name' => 'D3 Kebidanan', 'username' => 'd3kebidanan', 'role' => 'prodi', 'prodi_key' => 'd3-kebidanan', 'password' => env('SEED_D3_KEBIDANAN_PASSWORD', '123456')],
            ['name' => 'D3 Keperawatan', 'username' => 'd3keperawatan', 'role' => 'prodi', 'prodi_key' => 'd3-keperawatan', 'password' => env('SEED_D3_KEPERAWATAN_PASSWORD', '123456')],
            ['name' => 'D4 MIKES', 'username' => 'd4mikes', 'role' => 'prodi', 'prodi_key' => 'd4-mikes', 'password' => env('SEED_D4_MIKES_PASSWORD', '123456')],
            ['name' => 'S1 Farmasi', 'username' => 's1farmasi', 'role' => 'prodi', 'prodi_key' => 's1-farmasi', 'password' => env('SEED_S1_FARMASI_PASSWORD', '123456')],
            ['name' => 'S1 Kebidanan', 'username' => 's1kebidanan', 'role' => 'prodi', 'prodi_key' => 's1-kebidanan', 'password' => env('SEED_S1_KEBIDANAN_PASSWORD', '123456')],
            ['name' => 'S1 Keperawatan', 'username' => 's1keperawatan', 'role' => 'prodi', 'prodi_key' => 's1-keperawatan', 'password' => env('SEED_S1_KEPERAWATAN_PASSWORD', '123456')],
            ['name' => 'S1 Teknologi Informasi', 'username' => 's1teknologiinformasi', 'role' => 'prodi', 'prodi_key' => 's1-teknologi-informasi', 'password' => env('SEED_S1_TI_PASSWORD', '123456')],
            ['name' => 'Arsip Dekan', 'username' => 'dekan', 'role' => 'prodi', 'prodi_key' => 'arsip-dekan', 'password' => env('SEED_DEKAN_PASSWORD', '123456')],
        ];

        foreach ($users as $user) {
            User::query()->updateOrCreate(['username' => $user['username']], $user);
        }
    }
}
