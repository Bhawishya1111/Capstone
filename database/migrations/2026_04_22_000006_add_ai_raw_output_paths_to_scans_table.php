<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('scans', function (Blueprint $table) {
            $table->string('gemini_raw_output_path', 1024)
                ->nullable()
                ->after('nuclei_raw_output_path');
            $table->string('groq_raw_output_path', 1024)
                ->nullable()
                ->after('gemini_raw_output_path');
        });
    }

    public function down(): void
    {
        Schema::table('scans', function (Blueprint $table) {
            $table->dropColumn(['gemini_raw_output_path', 'groq_raw_output_path']);
        });
    }
};
