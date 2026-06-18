<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('warmup_mailboxes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('email');
            $table->enum('provider', ['fastmail', 'gmail', 'outlook', 'generic']);
            $table->string('imap_host');
            $table->unsignedSmallInteger('imap_port')->default(993);
            $table->string('smtp_host');
            $table->unsignedSmallInteger('smtp_port')->default(587);
            $table->string('username');
            $table->text('password_encrypted');
            $table->boolean('is_outreach_mailbox')->default(false);
            $table->boolean('is_seed_mailbox')->default(false);
            $table->boolean('is_pool_participant')->default(true);
            $table->boolean('warmup_enabled')->default(false);
            $table->timestamp('warmup_started_at')->nullable();
            $table->unsignedSmallInteger('warmup_target_volume')->default(50);
            $table->unsignedSmallInteger('warmup_ramp_days')->default(14);
            $table->time('send_window_start')->default('08:00:00');
            $table->time('send_window_end')->default('18:00:00');
            $table->boolean('send_on_weekends')->default(true);
            $table->enum('status', ['pending', 'warming', 'ready', 'at_risk', 'paused', 'failed'])->default('pending');
            $table->unsignedSmallInteger('deliverability_score')->nullable();
            $table->timestamp('last_imap_check_at')->nullable();
            $table->unsignedSmallInteger('consecutive_failures')->default(0);
            $table->timestamps();

            $table->index(['user_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('warmup_mailboxes');
    }
};
