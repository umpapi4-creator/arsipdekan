<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('letter_numbers', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('number')->unique();
            $table->string('prodi_key');
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('subject')->nullable();
            $table->string('attachment_path')->nullable();
            $table->timestamps();

            $table->index(['prodi_key', 'number']);
        });

        DB::table('settings')->updateOrInsert(
            ['key' => 'letter_number_last'],
            ['value' => '973']
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('letter_numbers');
        DB::table('settings')->where('key', 'letter_number_last')->delete();
    }
};
