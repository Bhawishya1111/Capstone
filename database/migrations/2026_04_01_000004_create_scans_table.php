<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Scan jobs: ZAP + Nikto + AI report pipeline.
 * Unauthorized scanning is illegal; this schema does not bypass that requirement.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('scans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('website_id')->nullable()->constrained()->nullOnDelete();
            $table->string('url', 2048);
            $table->string('status', 32)->default('queued');
            $table->unsignedTinyInteger('progress')->default(0);
            $table->string('phase', 64)->default('Starting');
            $table->string('zap_raw_output_path', 1024)->nullable();
            $table->string('nikto_raw_output_path', 1024)->nullable();
            $table->string('combined_raw_output_path', 1024)->nullable();
            $table->longText('ai_summary')->nullable();
            $table->string('report_path', 1024)->nullable();
            $table->text('error_message')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('scans');
    }
};
