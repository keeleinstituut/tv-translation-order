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
        $entityCacheTables = config('schema-consolidation.entity_cache_tables');

        // Get the database username from the current connection config
        $dbUsername = DB::connection()->getConfig('username');

        $appSchemaName = $sourceSchemas['application'] ?? 'application';
        $cacheSchemaName = $sourceSchemas['entity_cache'] ?? 'entity_cache';

        $this->info("Source schemas: $appSchemaName, $cacheSchemaName");
        $this->info("Target schema: $targetSchema");
        $this->info("Database user: $dbUsername");

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
        $entityCacheTableList = $shouldMigrateCacheSchema ? $entityCacheTables : [];

        if (empty($applicationTables) && empty($entityCacheTableList)) {
            $this->info("No tables found in schemas that need migration.");

            $shouldMigrateAppSchema && $this->dropSchemaIfEmpty($appSchemaName);
            $shouldMigrateCacheSchema && $this->dropSchemaIfEmpty($cacheSchemaName);

            $this->info("✓ Schemas already consolidated. All tables are in $targetSchema schema.");
            return 0;
        }

        try {
            DB::transaction(function () use ($applicationTables, $entityCacheTableList, $appSchemaName, $cacheSchemaName, $targetSchema, $shouldMigrateAppSchema, $shouldMigrateCacheSchema, $dbUsername) {
                if ($shouldMigrateAppSchema) {
                    $this->moveApplicationTablesToPublic($applicationTables, $appSchemaName, $targetSchema);
                }

                if ($shouldMigrateCacheSchema) {
                    $this->transferEntityCacheTableOwnership($entityCacheTableList, $cacheSchemaName, $dbUsername);
                    $this->moveEntityCacheTablesToPublic($entityCacheTableList, $cacheSchemaName, $targetSchema);
                }
            });

            $shouldMigrateAppSchema && $this->dropSchemaIfEmpty($appSchemaName);
            $shouldMigrateCacheSchema && $this->dropSchemaIfEmpty($cacheSchemaName);

            $this->info('✓ Schema consolidation completed successfully!');
            return 0;
        } catch (Exception $e) {
            $this->error('Schema consolidation failed: ' . $e->getMessage());
            $this->error($e->getTraceAsString());
            return 1;
        }
    }

    /**
     * Transfer ownership of entity_cache tables to the database user
     *
     * @param array $tables
     * @param string $schema
     * @param string $dbUsername
     * @return void
     */
    private function transferEntityCacheTableOwnership(array $tables, string $schema, string $dbUsername): void
    {
        if (empty($tables)) {
            return;
        }

        $this->info("Transferring ownership of $schema schema tables to $dbUsername...");

        foreach ($tables as $tableName) {
            $tableInfo = DB::selectOne("
                SELECT tableowner
                FROM pg_tables
                WHERE schemaname = ? AND tablename = ?
            ", [$schema, $tableName]);

            if (!$tableInfo) {
                $this->line("  Skipping $tableName (not found in $schema schema)...");
                continue;
            }

            if ($tableInfo->tableowner === $dbUsername) {
                $this->line("  Skipping $tableName (already owned by $dbUsername)...");
                continue;
            }

            $this->line("  Transferring ownership of $tableName...");
            $quotedUsername = '"' . str_replace('"', '""', $dbUsername) . '"';
            DB::statement("ALTER TABLE $schema.$tableName OWNER TO $quotedUsername");
        }

        $this->info("✓ $schema schema table ownership transferred");
    }

    /**
     * Move all tables from application schema to public schema
     *
     * @param array $tables
     * @param string $sourceSchema
     * @param string $targetSchema
     * @return void
     */
    private function moveApplicationTablesToPublic(array $tables, string $sourceSchema, string $targetSchema): void
    {
        if (empty($tables)) {
            return;
        }

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

    /**
     * Move the 3 specific entity_cache tables to public schema
     *
     * @param array $tables
     * @param string $sourceSchema
     * @param string $targetSchema
     * @return void
     */
    private function moveEntityCacheTablesToPublic(array $tables, string $sourceSchema, string $targetSchema): void
    {
        if (empty($tables)) {
            return;
        }

        $this->info("Moving entity_cache tables from $sourceSchema schema to $targetSchema...");

        foreach ($tables as $tableName) {
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

    /**
     * Drop schema if it's empty
     *
     * @param string $schema
     * @return void
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
     * Get all tables in a schema
     *
     * @param string $schema
     * @return array
     */
    private function getSchemaTables(string $schema): array
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
