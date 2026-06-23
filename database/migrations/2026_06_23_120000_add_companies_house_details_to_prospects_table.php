<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('prospects', function (Blueprint $table) {
            $table->json('companies_house_details')->nullable()->after('registered_company_cleared_at');
            $table->timestamp('companies_house_details_loaded_at')->nullable()->after('companies_house_details');
        });
    }

    public function down(): void
    {
        Schema::table('prospects', function (Blueprint $table) {
            $table->dropColumn([
                'companies_house_details',
                'companies_house_details_loaded_at',
            ]);
        });
    }
};
