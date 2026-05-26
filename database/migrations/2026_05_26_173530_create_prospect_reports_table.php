<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('prospect_reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('prospect_id')->constrained()->cascadeOnDelete();
            $table->uuid('token')->unique();
            $table->string('benchmark_place_id')->nullable();
            $table->json('screenshot_paths')->nullable();
            $table->json('report_data')->nullable();
            $table->timestamp('viewed_at')->nullable();
            $table->unsignedInteger('view_count')->default(0);
            $table->string('viewer_ip')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();

            $table->index('token');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('prospect_reports');
    }
};
