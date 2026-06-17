<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('prospects', function (Blueprint $table) {
            $table->string('qualification_status')->nullable()->after('cms_detection');
            $table->text('qualification_summary')->nullable()->after('qualification_status');
            $table->json('qualification_flags')->nullable()->after('qualification_summary');
            $table->timestamp('qualification_ran_at')->nullable()->after('qualification_flags');
        });
    }

    public function down(): void
    {
        Schema::table('prospects', function (Blueprint $table) {
            $table->dropColumn([
                'qualification_status',
                'qualification_summary',
                'qualification_flags',
                'qualification_ran_at',
            ]);
        });
    }
};
