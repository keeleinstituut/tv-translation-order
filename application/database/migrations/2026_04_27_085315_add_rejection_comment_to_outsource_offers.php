<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('outsource_offers', function (Blueprint $table): void {
            $table->text('rejection_comment')->nullable()->after('decline_comment');
        });

        DB::statement('ALTER TABLE outsource_offers DROP CONSTRAINT outsource_offers_status_check');
        DB::statement("ALTER TABLE outsource_offers ADD CONSTRAINT outsource_offers_status_check CHECK (status::text = ANY (ARRAY['PENDING', 'NOTIFIED', 'ACCEPTED', 'DECLINED', 'EXPIRED', 'SELECTED', 'REJECTED']::text[]))");
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE outsource_offers DROP CONSTRAINT outsource_offers_status_check');
        DB::statement("ALTER TABLE outsource_offers ADD CONSTRAINT outsource_offers_status_check CHECK (status::text = ANY (ARRAY['PENDING', 'NOTIFIED', 'ACCEPTED', 'DECLINED', 'EXPIRED', 'SELECTED']::text[]))");

        Schema::table('outsource_offers', function (Blueprint $table): void {
            $table->dropColumn('rejection_comment');
        });
    }
};
