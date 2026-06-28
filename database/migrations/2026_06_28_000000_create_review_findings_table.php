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
        Schema::create('review_findings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('review_run_id')->constrained('review_runs')->cascadeOnDelete();
            $table->string('severity');
            $table->string('category');
            $table->string('file_path');
            $table->string('line_reference')->nullable();
            $table->string('title');
            $table->text('rationale');
            $table->text('suggested_comment_text');
            $table->timestamps();

            $table->index(['review_run_id', 'severity']);
            $table->index(['review_run_id', 'category']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('review_findings');
    }
};
