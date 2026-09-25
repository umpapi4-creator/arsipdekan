<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

return new class extends Migration {
    public function up(): void
    {
        $existing = DB::table('users')->where('username', 'dekan')->first();

        if (!$existing) {
            DB::table('users')->insert([
                'name' => 'Arsip Dekan',
                'username' => 'dekan',
                'password' => Hash::make(env('SEED_DEKAN_PASSWORD', '123456')),
                'role' => 'prodi',
                'prodi_key' => 'arsip-dekan',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } else {
            DB::table('users')->where('username', 'dekan')->update([
                'name' => 'Arsip Dekan',
                'role' => 'prodi',
                'prodi_key' => 'arsip-dekan',
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        DB::table('users')->where('username', 'dekan')->delete();
    }
};
