<?php

use App\Enums\TagType;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $allowedTypes = implode(', ', array_map(
            fn (string $value) => "'{$value}'::character varying",
            TagType::values()
        ));

        DB::statement('ALTER TABLE tags DROP CONSTRAINT tags_type_check');
        DB::statement("ALTER TABLE tags ADD CONSTRAINT tags_type_check CHECK (type::character varying IN ({$allowedTypes}))");
    }

    public function down(): void
    {
        $oldTypes = collect(TagType::values())
            ->reject(fn (string $value) => $value === TagType::TranslationDomain->value)
            ->map(fn (string $value) => "'{$value}'::character varying")
            ->implode(', ');

        DB::statement('ALTER TABLE tags DROP CONSTRAINT tags_type_check');
        DB::statement("ALTER TABLE tags ADD CONSTRAINT tags_type_check CHECK (type::character varying IN ({$oldTypes}))");
    }
};
