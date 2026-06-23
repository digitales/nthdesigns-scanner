<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('prospects', function (Blueprint $table) {
            $table->string('registered_company_name')->nullable()->after('companies_house_checked_at');
            $table->string('registered_company_number')->nullable()->after('registered_company_name');
            $table->text('registered_company_note')->nullable()->after('registered_company_number');
            $table->foreignId('registered_company_by')->nullable()->after('registered_company_note')->constrained('users')->nullOnDelete();
            $table->timestamp('registered_company_at')->nullable()->after('registered_company_by');
            $table->foreignId('registered_company_cleared_by')->nullable()->after('registered_company_at')->constrained('users')->nullOnDelete();
            $table->timestamp('registered_company_cleared_at')->nullable()->after('registered_company_cleared_by');
        });
    }

    public function down(): void
    {
        Schema::table('prospects', function (Blueprint $table) {
            $table->dropConstrainedForeignId('registered_company_by');
            $table->dropConstrainedForeignId('registered_company_cleared_by');
            $table->dropColumn([
                'registered_company_name',
                'registered_company_number',
                'registered_company_note',
                'registered_company_at',
                'registered_company_cleared_at',
            ]);
        });
    }
};
