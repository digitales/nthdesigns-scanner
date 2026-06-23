<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('prospects', function (Blueprint $table) {
            $table->string('companies_house_number')->nullable()->after('validator_override_at');
            $table->string('companies_house_status')->nullable()->after('companies_house_number');
            $table->text('companies_house_summary')->nullable()->after('companies_house_status');
            $table->json('companies_house_flags')->nullable()->after('companies_house_summary');
            $table->json('raw_companies_house_payload')->nullable()->after('companies_house_flags');
            $table->timestamp('companies_house_checked_at')->nullable()->after('raw_companies_house_payload');

            $table->index('companies_house_status');
        });
    }

    public function down(): void
    {
        Schema::table('prospects', function (Blueprint $table) {
            $table->dropIndex(['companies_house_status']);
            $table->dropColumn([
                'companies_house_number',
                'companies_house_status',
                'companies_house_summary',
                'companies_house_flags',
                'raw_companies_house_payload',
                'companies_house_checked_at',
            ]);
        });
    }
};
