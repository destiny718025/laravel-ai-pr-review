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
        Schema::create('review_run_files', function (Blueprint $table) {
            $table->id();
            $table->foreignId('review_run_id')->constrained('review_runs')->cascadeOnDelete();
            $table->string('filename');
            $table->text('patch');
            $table->string('sha', 40);
            $table->timestamps();

            $table->index(['review_run_id', 'filename']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('review_run_files');
    }
};
