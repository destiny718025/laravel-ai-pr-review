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
        Schema::table('review_comment_drafts', function (Blueprint $table) {
            $table->string('github_comment_id')->nullable();
            $table->string('github_comment_html_url')->nullable();
            $table->timestamp('posted_at')->nullable();
            $table->string('publication_error_code')->nullable();
            $table->text('publication_error_message')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('review_comment_drafts', function (Blueprint $table) {
            $table->dropColumn([
                'github_comment_id',
                'github_comment_html_url',
                'posted_at',
                'publication_error_code',
                'publication_error_message',
            ]);
        });
    }
};
