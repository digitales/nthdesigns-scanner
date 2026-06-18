<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('warmup_alerts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('warmup_mailbox_id')->constrained()->cascadeOnDelete();
            $table->enum('type', ['connection_failed', 'ready', 'at_risk', 'pool_excluded']);
            $table->text('message');
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('read_at')->nullable();

            $table->index(['warmup_mailbox_id', 'read_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('warmup_alerts');
    }
};
