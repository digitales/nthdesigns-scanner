<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('outreach_emails', function (Blueprint $table) {
            $table->string('generated_subject')->nullable()->after('subject_line');
            $table->text('generated_body')->nullable()->after('email_body');
            $table->string('sent_subject')->nullable()->after('generated_body');
            $table->text('sent_body')->nullable()->after('sent_subject');
            $table->foreignId('from_mailbox_id')->nullable()->after('sent_body')
                ->constrained('warmup_mailboxes')->nullOnDelete();
            $table->string('smtp_message_id')->nullable()->after('from_mailbox_id');
            $table->string('send_source')->nullable()->after('smtp_message_id');
        });

        DB::table('outreach_emails')->whereNull('generated_body')->update([
            'generated_subject' => DB::raw('subject_line'),
            'generated_body' => DB::raw('email_body'),
        ]);

        DB::table('outreach_emails')
            ->whereNotNull('sent_at')
            ->whereNull('sent_body')
            ->update([
                'sent_subject' => DB::raw('subject_line'),
                'sent_body' => DB::raw('email_body'),
                'send_source' => 'manual',
            ]);
    }

    public function down(): void
    {
        Schema::table('outreach_emails', function (Blueprint $table) {
            $table->dropConstrainedForeignId('from_mailbox_id');
            $table->dropColumn([
                'generated_subject', 'generated_body', 'sent_subject', 'sent_body',
                'smtp_message_id', 'send_source',
            ]);
        });
    }
};
