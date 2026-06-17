<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('outreach_emails', function (Blueprint $table) {
            $table->string('channel', 20)->default('email')->after('pitch_angle');
            $table->string('subject_line')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('outreach_emails', function (Blueprint $table) {
            $table->dropColumn('channel');
            $table->string('subject_line')->nullable(false)->change();
        });
    }
};
