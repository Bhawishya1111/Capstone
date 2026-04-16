<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Pivot: which non-admin users may run/view scans for a given website.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('website_user', function (Blueprint $table) {
            $table->id();
            $table->foreignId('website_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['website_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('website_user');
    }
};
