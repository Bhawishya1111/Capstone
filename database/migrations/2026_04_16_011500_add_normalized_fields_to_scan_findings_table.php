<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('scan_findings', function (Blueprint $table) {
            $table->string('tool_rule_id', 128)->nullable()->after('source');
            $table->string('confidence', 32)->nullable()->after('severity');
            $table->string('normalized_severity', 16)->nullable()->after('confidence');
            $table->text('evidence')->nullable()->after('description');
            $table->json('raw_metadata')->nullable()->after('endpoint');

            $table->index(['scan_id', 'normalized_severity']);
        });
    }

    public function down(): void
    {
        Schema::table('scan_findings', function (Blueprint $table) {
            $table->dropIndex(['scan_id', 'normalized_severity']);
            $table->dropColumn([
                'tool_rule_id',
                'confidence',
                'normalized_severity',
                'evidence',
                'raw_metadata',
            ]);
        });
    }
};
