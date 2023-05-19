<?php

use App\Enums\TagType;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('tags', function (Blueprint $table) {
            $table->uuid('id');
            $table->string('name');
            $table->enum('type', TagType::values());
            $table->foreignUuid('institution_id')->nullable(); // TODO: add constraint
            $table->timestampsTz();
            $table->softDeletesTz();
        });

        DB::statement(
            'create unique index tags_name_type_institution_id_unique '.
            'on tags (name, type, institution_id) '.
            'where (deleted_at is null AND institution_id is not null)'
        );

        DB::statement(
            'create unique index tags_name_type_with_empty_institution_id_unique '.
            'on tags (name, type) '.
            'where (deleted_at is null AND institution_id is null)'
        );
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tags');
    }
};
