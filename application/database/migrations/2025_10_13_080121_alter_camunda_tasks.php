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
        Schema::table('camunda_tasks', function (Blueprint $table) {
            $table->dropColumn("var_source_language_classifier_value_id");
            $table->dropColumn("var_destination_language_classifier_value_id");
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('camunda_tasks', function (Blueprint $table) {
            $table->uuid("var_source_language_classifier_value_id");
            $table->uuid("var_destination_language_classifier_value_id");
        });
    }
};
