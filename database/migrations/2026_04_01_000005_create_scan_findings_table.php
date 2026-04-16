<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Optional normalized findings (populated from ZAP/Nikto or Gemini in later phases).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('scan_findings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('scan_id')->constrained()->cascadeOnDelete();
            $table->string('source', 16);
            $table->string('title', 512);
            $table->string('severity', 32)->nullable();
            $table->text('description')->nullable();
            $table->text('remediation')->nullable();
            $table->string('endpoint', 2048)->nullable();
            $table->timestamps();

            $table->index(['scan_id', 'severity']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('scan_findings');
    }
};
