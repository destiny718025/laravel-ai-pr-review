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
        Schema::table('review_runs', function (Blueprint $table) {
            $table->string('github_title')->nullable()->after('safe_error_message');
            $table->string('github_state')->nullable()->after('github_title');
            $table->string('github_head_sha')->nullable()->after('github_state');
            $table->timestamp('github_fetched_at')->nullable()->after('github_head_sha');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('review_runs', function (Blueprint $table) {
            $table->dropColumn([
                'github_title',
                'github_state',
                'github_head_sha',
                'github_fetched_at',
            ]);
        });
    }
};
