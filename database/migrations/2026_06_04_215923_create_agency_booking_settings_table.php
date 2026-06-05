<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('agency_booking_settings', function (Blueprint $table) {
            $table->id();
            $table->boolean('enabled')->default(false);
            $table->string('fastmail_username')->nullable();
            $table->text('fastmail_app_password')->nullable();
            $table->string('caldav_calendar_url')->nullable();
            $table->string('timezone')->default('Europe/London');
            $table->unsignedSmallInteger('event_duration_minutes')->default(30);
            $table->unsignedSmallInteger('min_notice_hours')->default(24);
            $table->unsignedSmallInteger('buffer_minutes')->default(0);
            $table->json('working_hours')->nullable();
            $table->string('confirmation_from_email')->nullable();
            $table->string('confirmation_from_name')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('agency_booking_settings');
    }
};
