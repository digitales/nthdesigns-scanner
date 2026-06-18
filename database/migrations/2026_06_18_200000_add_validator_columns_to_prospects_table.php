<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('prospects', function (Blueprint $table) {
            $table->string('validator_status')->nullable()->after('qualification_ran_at');
            $table->text('validator_summary')->nullable()->after('validator_status');
            $table->json('validator_flags')->nullable()->after('validator_summary');
            $table->timestamp('validator_ran_at')->nullable()->after('validator_flags');

            $table->index('validator_status');
        });
    }

    public function down(): void
    {
        Schema::table('prospects', function (Blueprint $table) {
            $table->dropIndex(['validator_status']);
            $table->dropColumn(['validator_status', 'validator_summary', 'validator_flags', 'validator_ran_at']);
        });
    }
};
