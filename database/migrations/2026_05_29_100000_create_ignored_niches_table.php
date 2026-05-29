<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ignored_niches', function (Blueprint $table) {
            $table->id();
            $table->string('niche')->unique();
            $table->enum('reason', ['manual', 'low_results']);
            $table->timestamps();
        });

        Schema::create('niche_inclusion_overrides', function (Blueprint $table) {
            $table->id();
            $table->string('niche')->unique();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('niche_inclusion_overrides');
        Schema::dropIfExists('ignored_niches');
    }
};
