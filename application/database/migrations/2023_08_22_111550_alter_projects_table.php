<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $syncSchema = Config::get('pgsql-connection.sync.properties.schema');

        Schema::table('projects', function (Blueprint $table) use ($syncSchema) {
            $table->softDeletesTz();
            $table->timestampTz('event_start_at')->nullable();
            $table->foreignUuid('translation_domain_classifier_value_id')
                ->constrained("$syncSchema.cached_classifier_values");
            $table->foreignUuid('client_institution_user_id')
                ->constrained("$syncSchema.cached_institution_users");
            $table->foreignUuid('manager_institution_user_id')
                ->nullable()
                ->constrained("$syncSchema.cached_institution_users");

            $table->string('reference_number')->nullable()->change();
            $table->text('comments')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->dropSoftDeletesTz();
            $table->dropColumn('event_start_at');
            $table->dropColumn('translation_domain_classifier_value_id');
            $table->dropColumn('client_institution_user_id');
            $table->dropColumn('manager_institution_user_id');
            $table->string('reference_number')->change();
            $table->text('comments')->change();
        });
    }
};
