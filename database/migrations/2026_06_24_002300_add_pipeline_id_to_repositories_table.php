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
        Schema::table('repositories', function (Blueprint $table) {
            $table->unsignedBigInteger('pipeline_id')->nullable()->after('project_id');
            $table->foreign('pipeline_id')->references('id')->on('pipelines')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('repositories', function (Blueprint $table) {
            $table->dropForeign(['pipeline_id']);
            $table->dropColumn('pipeline_id');
        });
    }
};
