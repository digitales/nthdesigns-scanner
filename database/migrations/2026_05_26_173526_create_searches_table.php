<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('searches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('niche');
            $table->string('city');
            $table->string('country', 2)->default('GB');
            $table->enum('scan_type', ['gbp_only', 'accessibility_only', 'combined'])->default('combined');
            $table->enum('status', ['pending', 'discovering', 'auditing', 'complete', 'failed'])->default('pending');
            $table->unsignedInteger('total_found')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('searches');
    }
};
