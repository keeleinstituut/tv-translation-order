<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('external_translation_request_recipients', function (Blueprint $table): void {
            $table->text('rejection_comment')->nullable()->after('decline_comment');
        });

        DB::statement('ALTER TABLE external_translation_request_recipients DROP CONSTRAINT external_translation_request_recipients_status_check');
        DB::statement("ALTER TABLE external_translation_request_recipients ADD CONSTRAINT external_translation_request_recipients_status_check CHECK (status::text = ANY (ARRAY['PENDING', 'NOTIFIED', 'ACCEPTED', 'DECLINED', 'EXPIRED', 'SELECTED', 'REJECTED']::text[]))");
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE external_translation_request_recipients DROP CONSTRAINT external_translation_request_recipients_status_check');
        DB::statement("ALTER TABLE external_translation_request_recipients ADD CONSTRAINT external_translation_request_recipients_status_check CHECK (status::text = ANY (ARRAY['PENDING', 'NOTIFIED', 'ACCEPTED', 'DECLINED', 'EXPIRED', 'SELECTED']::text[]))");

        Schema::table('external_translation_request_recipients', function (Blueprint $table): void {
            $table->dropColumn('rejection_comment');
        });
    }
};
