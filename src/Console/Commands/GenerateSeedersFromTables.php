<?php
/**
 * Author: Talemul Islam
 * Website: https://talemul.com
 */
namespace SuperAdmin\Admin\Helpers\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class GenerateSeedersFromTables extends Command
{
    protected $signature = 'scaffold:generate-seeders {--remove-prefix=}';
    protected $description = 'Generate Laravel seeders from existing table data';

    public function handle()
    {
        $connection = DB::connection();
        $driver = $connection->getDriverName();

        // Get optional prefix to remove
        $removePrefix = (string) $this->option('remove-prefix');

        switch ($driver) {
            case 'pgsql':
                // PostgreSQL
                $tables = DB::select("
                SELECT tablename AS name
                FROM pg_tables
                WHERE schemaname = 'public'
            ");
                break;

            case 'mysql':
            case 'mariadb': // some setups may still report 'mysql'
                // MySQL / MariaDB
                $dbName = DB::getDatabaseName();
                $tables = DB::select("
                SELECT TABLE_NAME AS name
                FROM information_schema.tables
                WHERE table_schema = ?
                  AND TABLE_TYPE = 'BASE TABLE'
            ", [$dbName]);
                break;

            default:
                $this->error('Unsupported database driver: ' . $driver);
                return Command::FAILURE;
        }

        foreach ($tables as $tableObj) {
            $table = $tableObj->name;

            if (in_array($table, ['migrations', 'helper_scaffolds', 'helper_scaffold_details'])) {
                continue;
            }

            $data = DB::table($table)->limit(500)->get();

            if ($data->isEmpty()) {
                $this->warn("⚠️ No data in table: $table");
                continue;
            }
            // Remove given prefix (if any) from table name for class & seeder table
            $logicalTable = $removePrefix && Str::startsWith($table, $removePrefix)
                ? Str::after($table, $removePrefix)
                : $table;

            $className = Str::studly($logicalTable) . 'Seeder';
            $filePath = database_path("seeders/{$className}.php");

            $content = $this->buildSeederClass($className, $logicalTable, $data);
            File::put($filePath, $content);

            $this->info("✅ Seeder generated: {$className}.php");
        }

        return Command::SUCCESS;
    }

    protected function buildSeederClass(string $className, string $table, $data): string
    {
        $insertData = $data->map(function ($row) {
            $rowArray = (array) $row;

            foreach ($rowArray as $key => $value) {
                if (is_bool($value)) {
                    $rowArray[$key] = $value ? 'true' : 'false';
                } elseif (is_null($value)) {
                    $rowArray[$key] = 'null';
                } elseif (is_string($value)) {
                    $rowArray[$key] = "'" . str_replace("'", "\'", $value) . "'";
                } elseif (is_numeric($value)) {
                    $rowArray[$key] = $value;
                } else {
                    $rowArray[$key] = "'" . strval($value) . "'";
                }
            }

            return '[' . implode(', ', array_map(
                    fn($k, $v) => "'$k' => $v",
                    array_keys($rowArray),
                    $rowArray
                )) . ']';
        })->implode(",
");

        return <<<PHP
<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class {$className} extends Seeder
{
    public function run(): void
    {
        DB::table('{$table}')->insert([
            {$insertData}
        ]);
    }
}
PHP;
    }
}
