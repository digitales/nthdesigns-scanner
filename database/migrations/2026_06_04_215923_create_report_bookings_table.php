<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('report_bookings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('prospect_report_id')->constrained()->cascadeOnDelete();
            $table->foreignId('prospect_id')->constrained()->cascadeOnDelete();
            $table->timestamp('starts_at');
            $table->timestamp('ends_at');
            $table->string('attendee_name');
            $table->string('attendee_email');
            $table->string('attendee_phone')->nullable();
            $table->text('note')->nullable();
            $table->string('calendar_event_uid')->nullable();
            $table->string('status')->default('confirmed');
            $table->timestamp('confirmation_sent_at')->nullable();
            $table->timestamps();

            $table->unique(['prospect_report_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('report_bookings');
    }
};
