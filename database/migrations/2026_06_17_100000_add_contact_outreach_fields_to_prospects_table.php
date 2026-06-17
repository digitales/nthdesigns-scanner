<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('prospects', function (Blueprint $table) {
            $table->string('linkedin_url')->nullable()->after('email');
            $table->string('contact_page_url', 500)->nullable()->after('linkedin_url');
            $table->string('use_form_outreach', 10)->default('auto')->after('contact_page_url');
            $table->string('outreach_channel', 15)->default('auto')->after('use_form_outreach');
            $table->json('contact_signals')->nullable()->after('cms_detection');
        });
    }

    public function down(): void
    {
        Schema::table('prospects', function (Blueprint $table) {
            $table->dropColumn([
                'linkedin_url',
                'contact_page_url',
                'use_form_outreach',
                'outreach_channel',
                'contact_signals',
            ]);
        });
    }
};
