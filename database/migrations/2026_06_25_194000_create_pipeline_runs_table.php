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
        Schema::create('pipeline_runs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('repository_id');
            $table->unsignedBigInteger('pipeline_id')->nullable();
            $table->unsignedBigInteger('user_id');
            $table->string('status')->default('pending'); // pending, running, success, failed
            $table->json('stage_statuses')->nullable(); // JSON array of statuses for each stage
            $table->longText('logs')->nullable(); // Accumulated logs (written on completion)
            $table->json('variables')->nullable(); // JSON key-value of substituted variables
            $table->timestamps();

            $table->foreign('repository_id')->references('id')->on('repositories')->onDelete('cascade');
            $table->foreign('pipeline_id')->references('id')->on('pipelines')->onDelete('set null');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pipeline_runs');
    }
};
