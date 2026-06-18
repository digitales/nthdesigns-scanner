<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('prospects', function (Blueprint $table) {
            $table->string('validator_override_status')->nullable()->after('validator_ran_at');
            $table->text('validator_override_note')->nullable()->after('validator_override_status');
            $table->foreignId('validator_override_by')->nullable()->after('validator_override_note')->constrained('users')->nullOnDelete();
            $table->timestamp('validator_override_at')->nullable()->after('validator_override_by');
        });
    }

    public function down(): void
    {
        Schema::table('prospects', function (Blueprint $table) {
            $table->dropConstrainedForeignId('validator_override_by');
            $table->dropColumn([
                'validator_override_status',
                'validator_override_note',
                'validator_override_at',
            ]);
        });
    }
};
