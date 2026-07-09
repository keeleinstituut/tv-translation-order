<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE outsource_offers DROP CONSTRAINT IF EXISTS outsource_offers_status_check');
        DB::statement("ALTER TABLE outsource_offers ADD CONSTRAINT outsource_offers_status_check CHECK (status IN ('REQUEST_PENDING', 'REQUEST_SENT', 'REQUEST_ACCEPTED', 'REQUEST_DECLINED', 'REQUEST_EXPIRED', 'OFFER_ACCEPTED', 'OFFER_DECLINED', 'REQUEST_CANCELLED'))");
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE outsource_offers DROP CONSTRAINT IF EXISTS outsource_offers_status_check');
        DB::statement("ALTER TABLE outsource_offers ADD CONSTRAINT outsource_offers_status_check CHECK (status IN ('REQUEST_PENDING', 'REQUEST_SENT', 'REQUEST_ACCEPTED', 'REQUEST_DECLINED', 'REQUEST_EXPIRED', 'OFFER_ACCEPTED', 'OFFER_DECLINED'))");
    }
};
