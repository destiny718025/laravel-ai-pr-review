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
        Schema::create('review_comment_drafts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('review_run_id')->constrained('review_runs')->cascadeOnDelete();
            $table->foreignId('source_review_finding_id')->constrained('review_findings')->cascadeOnDelete();
            $table->string('status');
            $table->text('body');
            $table->string('file_path');
            $table->string('line_reference')->nullable();
            $table->string('github_head_sha');
            $table->string('source_file_sha')->nullable();
            $table->timestamp('stale_at')->nullable();
            $table->timestamps();

            $table->unique('source_review_finding_id');
            $table->index(['review_run_id', 'status']);
            $table->index(['review_run_id', 'stale_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('review_comment_drafts');
    }
};
