<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tags', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('color')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'name']);
        });

        Schema::create('niche_notes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('niche_label');
            $table->string('city')->nullable();
            $table->text('body');
            $table->timestamps();

            $table->index(['user_id', 'niche_label', 'city']);
        });

        Schema::create('niche_tag_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('tag_id')->constrained()->cascadeOnDelete();
            $table->string('niche_label');
            $table->string('city')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'tag_id', 'niche_label', 'city'], 'niche_tag_unique');
        });

        Schema::create('prospect_tag_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('prospect_id')->constrained()->cascadeOnDelete();
            $table->foreignId('tag_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['prospect_id', 'tag_id']);
        });

        Schema::create('prospect_lists', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('type');
            $table->text('description')->nullable();
            $table->json('filter')->nullable();
            $table->timestamps();
        });

        Schema::create('prospect_list_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('prospect_list_id')->constrained()->cascadeOnDelete();
            $table->foreignId('prospect_id')->constrained()->cascadeOnDelete();
            $table->string('status')->default('new');
            $table->timestamp('follow_up_at')->nullable();
            $table->timestamps();

            $table->unique(['prospect_list_id', 'prospect_id']);
        });

        Schema::create('shared_lists', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('prospect_list_id')->constrained()->cascadeOnDelete();
            $table->string('token')->unique();
            $table->json('snapshot')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shared_lists');
        Schema::dropIfExists('prospect_list_items');
        Schema::dropIfExists('prospect_lists');
        Schema::dropIfExists('prospect_tag_assignments');
        Schema::dropIfExists('niche_tag_assignments');
        Schema::dropIfExists('niche_notes');
        Schema::dropIfExists('tags');
    }
};
