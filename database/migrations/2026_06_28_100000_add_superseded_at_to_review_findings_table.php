<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('review_findings', function (Blueprint $table) {
            $table->timestamp('superseded_at')->nullable()->after('suggested_comment_text');
            $table->index(['review_run_id', 'superseded_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('review_findings', function (Blueprint $table) {
            $table->dropIndex(['review_run_id', 'superseded_at']);
            $table->dropColumn('superseded_at');
        });
    }
};
