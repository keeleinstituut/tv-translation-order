<?php

namespace App\Console\Commands;

use Exception;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ConsolidateSchemas extends Command
{
    /**
     * @var string
     */
    protected $signature = 'db:consolidate-schemas';

    /**
     * @var string
     */
    protected $description = 'Consolidate all tables from application and entity_cache schemas to public schema';

    public function handle(): int
    {
        $this->info('Starting schema consolidation...');

        $sourceSchemas = config('schema-consolidation.source_schemas');
        $targetSchema = config('schema-consolidation.target_schema');

        $appSchemaName = $sourceSchemas['application'] ?? 'application';
        $cacheSchemaName = $sourceSchemas['entity_cache'] ?? 'entity_cache';

        $this->info("Source schemas: $appSchemaName, $cacheSchemaName");
        $this->info("Target schema: $targetSchema");

        $shouldMigrateAppSchema = $targetSchema !== $appSchemaName;
        $shouldMigrateCacheSchema = $targetSchema !== $cacheSchemaName;

        if (!$shouldMigrateAppSchema && !$shouldMigrateCacheSchema) {
            $this->info("✓ All source schemas are already the target schema. No migration needed.");
            return 0;
        }

        if (!$shouldMigrateAppSchema) {
            $this->info("Skipping $appSchemaName schema migration (already in target schema).");
        }

        if (!$shouldMigrateCacheSchema) {
            $this->info("Skipping $cacheSchemaName schema migration (already in target schema).");
        }

        $applicationTables = $shouldMigrateAppSchema ? $this->getSchemaTables($appSchemaName) : [];
        $entityCacheTables = $shouldMigrateCacheSchema ? $this->getSchemaTables($cacheSchemaName) : [];

        if (empty($applicationTables) && empty($entityCacheTables)) {
            $this->info("No tables found in schemas that need migration.");

            $shouldMigrateAppSchema && $this->dropSchemaIfEmpty($appSchemaName);
            $shouldMigrateCacheSchema && $this->dropSchemaIfEmpty($cacheSchemaName);

            $this->info("✓ Schemas already consolidated. All tables are in $targetSchema schema.");
            return 0;
        }

        try {
            DB::transaction(function () use ($applicationTables, $entityCacheTables, $appSchemaName, $cacheSchemaName, $targetSchema, $shouldMigrateAppSchema, $shouldMigrateCacheSchema) {
                $shouldMigrateAppSchema && $this->moveTablesToTargetSchema($applicationTables, $appSchemaName, $targetSchema);
                $shouldMigrateCacheSchema && $this->moveTablesToTargetSchema($entityCacheTables, $cacheSchemaName, $targetSchema);
            });

            $shouldMigrateAppSchema && $this->dropSchemaIfEmpty($appSchemaName);
            $shouldMigrateCacheSchema && $this->dropSchemaIfEmpty($cacheSchemaName);

            $this->info('Schema consolidation completed successfully!');
            return 0;
        } catch (Exception $e) {
            $this->error('Schema consolidation failed: ' . $e->getMessage());
            $this->error($e->getTraceAsString());
            return 1;
        }
    }

    /**
     * @param string $schema
     */
    public function dropSchemaIfEmpty(string $schema): void
    {
        try {
            $tablesInSchema = DB::select("
                            SELECT tablename
                            FROM pg_tables
                            WHERE schemaname = ?
                        ", [$schema]);

            if (empty($tablesInSchema)) {
                DB::statement("DROP SCHEMA IF EXISTS $schema CASCADE");
                $this->info("✓ Schema $schema dropped");
            } else {
                $this->warn("Schema $schema still contains tables, not dropping.");
            }
        } catch (Exception $e) {
            $this->warn("Could not drop schema $schema: " . $e->getMessage());
        }
    }

    /**
     * NOTE: Foreign keys are automatically preserved when moving tables (they use OIDs internally)
     *
     * @param array $tables
     * @param mixed $sourceSchema
     * @param mixed $targetSchema
     * @return void
     */
    function moveTablesToTargetSchema(array $tables, string $sourceSchema, string $targetSchema): void
    {
        if (!empty($tables)) {
            $this->info("Moving tables from $sourceSchema schema to $targetSchema...");
            foreach ($tables as $tableInfo) {
                $tableName = $tableInfo->tablename;

                $tableExists = DB::selectOne("
                            SELECT 1
                            FROM information_schema.tables
                            WHERE table_schema = ?
                            AND table_name = ?
                        ", [$sourceSchema, $tableName]);

                if (!$tableExists) {
                    $this->line("  Skipping $tableName (not found in $sourceSchema schema)...");
                    continue;
                }

                $this->line("  Moving $tableName...");
                DB::statement("ALTER TABLE $sourceSchema.$tableName SET SCHEMA $targetSchema");
            }
            $this->info("✓ $sourceSchema schema tables moved");
        }
    }

    function getSchemaTables(string $schema): array
    {
        try {
            return DB::select("
                    SELECT tablename
                    FROM pg_tables
                    WHERE schemaname = ?
                ", [$schema]);
        } catch (Exception $e) {
            $this->warn("Could not query $schema schema (may not have access or schema does not exist): " . $e->getMessage());
        }

        return [];
    }
}
