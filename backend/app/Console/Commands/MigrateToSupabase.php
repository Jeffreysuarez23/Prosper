<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class MigrateToSupabase extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'migrate:supabase';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Migrate all data from MySQL to Supabase without omitting any record.';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $sourceDb = 'mysql';
        $destDb = 'supabase';

        $this->info("Starting data migration from {$sourceDb} to {$destDb}...");

        // Get all tables from the source database (MySQL)
        $tables = DB::connection($sourceDb)->select('SHOW FULL TABLES WHERE Table_type = "BASE TABLE"');
        $tableKey = 'Tables_in_' . DB::connection($sourceDb)->getDatabaseName();

        $tableNames = [];
        foreach ($tables as $table) {
            $tableName = $table->$tableKey;
            // Skip the migrations table so we don't mess up the state if we run artisan migrate beforehand
            if ($tableName !== 'migrations') {
                $tableNames[] = $tableName;
            }
        }

        // Disable foreign key checks on Supabase (PostgreSQL)
        // Since PostgreSQL doesn't have a simple session-wide disable like MySQL (SET FOREIGN_KEY_CHECKS=0),
        // we can set session_replication_role to replica.
        $this->info("Disabling foreign keys in destination...");
        DB::connection($destDb)->statement('SET session_replication_role = replica;');

        foreach ($tableNames as $table) {
            $this->info("Migrating table: {$table}");
            
            // Count total records
            $totalRecords = DB::connection($sourceDb)->table($table)->count();
            if ($totalRecords === 0) {
                $this->info("  -> Table {$table} is empty. Skipping.");
                continue;
            }

            // Clean destination table before inserting to prevent duplicate keys if rerunning
            DB::connection($destDb)->table($table)->truncate();

            // Fetch records in chunks to prevent memory issues
            $chunkSize = 1000;
            $migrated = 0;

            DB::connection($sourceDb)->table($table)->orderBy(DB::raw('1'))->chunk($chunkSize, function ($records) use ($table, $destDb, &$migrated, $totalRecords) {
                $dataToInsert = [];
                foreach ($records as $record) {
                    $dataToInsert[] = (array) $record;
                }

                if (!empty($dataToInsert)) {
                    DB::connection($destDb)->table($table)->insert($dataToInsert);
                    $migrated += count($dataToInsert);
                    $this->output->write("\r  -> Progress: {$migrated} / {$totalRecords}");
                }
            });
            $this->newLine();
            $this->info("  -> Migrated {$migrated} records successfully.");
        }

        // Re-enable foreign key checks
        $this->info("Re-enabling foreign keys in destination...");
        DB::connection($destDb)->statement('SET session_replication_role = origin;');

        $this->info("Migration completed successfully!");
    }
}
