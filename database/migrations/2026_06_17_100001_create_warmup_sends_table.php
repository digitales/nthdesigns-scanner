<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('warmup_sends', function (Blueprint $table) {
            $table->id();
            $table->foreignId('from_mailbox_id')->constrained('warmup_mailboxes')->cascadeOnDelete();
            $table->foreignId('to_mailbox_id')->constrained('warmup_mailboxes')->cascadeOnDelete();
            $table->string('message_id')->index();
            $table->string('subject');
            $table->timestamp('sent_at');
            $table->timestamp('opened_at')->nullable();
            $table->timestamp('replied_at')->nullable();
            $table->timestamp('rescued_from_spam_at')->nullable();
            $table->enum('status', ['sent', 'opened', 'replied', 'rescued', 'bounced'])->default('sent');
            $table->timestamps();

            $table->index(['from_mailbox_id', 'sent_at']);
            $table->index(['to_mailbox_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('warmup_sends');
    }
};
