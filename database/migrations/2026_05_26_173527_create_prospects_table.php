<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('prospects', function (Blueprint $table) {
            $table->id();
            $table->foreignId('search_id')->constrained()->cascadeOnDelete();
            $table->string('place_id');
            $table->string('business_name');
            $table->string('phone')->nullable();
            $table->string('website_url')->nullable();
            $table->string('address')->nullable();
            $table->decimal('rating', 2, 1)->nullable();
            $table->unsignedInteger('review_count')->default(0);
            $table->unsignedInteger('photo_count')->default(0);
            $table->boolean('has_description')->default(false);
            $table->boolean('hours_complete')->default(false);

            $table->unsignedSmallInteger('gbp_score')->default(0);
            $table->json('gbp_flags')->nullable();

            $table->unsignedSmallInteger('a11y_score')->default(0);
            $table->json('a11y_flags')->nullable();
            $table->unsignedSmallInteger('performance_score')->default(0);

            $table->unsignedSmallInteger('combined_score')->default(0)->index();
            $table->enum('dominant_angle', ['gbp', 'accessibility', 'both'])->default('gbp');

            $table->enum('audit_status', ['pending', 'complete', 'failed', 'skipped'])->default('pending');

            $table->json('raw_gbp_payload')->nullable();
            $table->json('raw_a11y_payload')->nullable();
            $table->json('raw_lighthouse_payload')->nullable();

            $table->timestamp('expires_at')->nullable();
            $table->timestamps();

            $table->unique(['search_id', 'place_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('prospects');
    }
};
