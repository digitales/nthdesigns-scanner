<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('niche_scans', function (Blueprint $table) {
            $table->id();
            $table->string('niche');
            $table->string('niche_query');
            $table->string('city');
            $table->string('country', 2)->default('GB');
            $table->date('scan_date');
            $table->unsignedInteger('result_count')->default(0);
            $table->unsignedInteger('sampled_count')->default(0);
            $table->decimal('avg_gbp_score', 5, 2)->nullable();
            $table->decimal('pct_no_website', 5, 2)->nullable();
            $table->decimal('pct_low_reviews', 5, 2)->nullable();
            $table->decimal('opportunity_score', 5, 2)->nullable();
            $table->enum('status', ['pending', 'complete', 'failed'])->default('pending');
            $table->timestamp('ran_at')->nullable();
            $table->timestamps();

            $table->unique(['niche', 'city', 'scan_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('niche_scans');
    }
};
