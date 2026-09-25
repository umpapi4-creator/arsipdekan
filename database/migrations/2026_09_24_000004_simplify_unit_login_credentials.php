<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

return new class extends Migration {
    public function up(): void
    {
        $accounts = [
            'd3-kebidanan' => ['name' => 'D3 Kebidanan', 'username' => 'd3kebidanan'],
            'd3-keperawatan' => ['name' => 'D3 Keperawatan', 'username' => 'd3keperawatan'],
            'd4-mikes' => ['name' => 'D4 MIKES', 'username' => 'd4mikes'],
            's1-farmasi' => ['name' => 'S1 Farmasi', 'username' => 's1farmasi'],
            's1-kebidanan' => ['name' => 'S1 Kebidanan', 'username' => 's1kebidanan'],
            's1-keperawatan' => ['name' => 'S1 Keperawatan', 'username' => 's1keperawatan'],
            's1-teknologi-informasi' => ['name' => 'S1 Teknologi Informasi', 'username' => 's1teknologiinformasi'],
            'arsip-dekan' => ['name' => 'Arsip Dekan', 'username' => 'dekan'],
        ];

        foreach ($accounts as $prodiKey => $account) {
            $existing = DB::table('users')->where('prodi_key', $prodiKey)->first();

            if ($existing) {
                DB::table('users')->where('id', $existing->id)->update([
                    'name' => $account['name'],
                    'username' => $account['username'],
                    'password' => Hash::make('123456'),
                    'role' => 'prodi',
                    'updated_at' => now(),
                ]);
                continue;
            }

            DB::table('users')->insert([
                'name' => $account['name'],
                'username' => $account['username'],
                'password' => Hash::make('123456'),
                'role' => 'prodi',
                'prodi_key' => $prodiKey,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        // Kredensial lama tidak dipulihkan agar rollback tidak mengganti password pengguna secara diam-diam.
    }
};
