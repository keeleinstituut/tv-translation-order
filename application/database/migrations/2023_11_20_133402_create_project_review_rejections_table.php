<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $syncSchema = Config::get('pgsql-connection.sync.properties.schema');
        Schema::create('project_review_rejections', function (Blueprint $table) use ($syncSchema) {
            $table->uuid('id')->primary();
            $table->foreignUuid('project_id')
                ->constrained('projects');
            $table->text('description');
            $table->jsonb('sub_project_ids');
            $table->string('file_collection');
            $table->foreignUuid('institution_user_id')
                ->constrained("$syncSchema.cached_institution_users");
            $table->timestampsTz();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('project_review_rejections');
    }
};
