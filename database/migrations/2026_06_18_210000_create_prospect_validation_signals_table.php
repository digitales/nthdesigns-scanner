<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('prospect_validation_signals', function (Blueprint $table) {
            $table->id();
            $table->string('pattern');
            $table->string('label');
            $table->boolean('active')->default(true);
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->timestamps();

            $table->unique('pattern');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('prospect_validation_signals');
    }
};
