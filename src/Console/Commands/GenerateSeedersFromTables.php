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
    protected $signature = 'scaffold:generate-seeders';
    protected $description = 'Generate Laravel seeders from existing table data';

    public function handle()
    {
        $tables = DB::select("SELECT tablename FROM pg_tables WHERE schemaname = 'public' ");//

        foreach ($tables as $tableObj) {
            $table = $tableObj->tablename;

            if (in_array($table, ['migrations', 'helper_scaffolds', 'helper_scaffold_details'])) continue;

            $data = DB::table($table)->limit(500)->get(); // limit for performance

            if ($data->isEmpty()) {
                $this->warn("⚠️ No data in table: $table");
                continue;
            }

            $className = Str::studly($table) . 'Seeder';
            $filePath = database_path("seeders/CmisWebsite/{$className}.php");

            $content = $this->buildSeederClass($className, $table, $data);
            File::put($filePath, $content);

            $this->info("✅ Seeder generated: {$className}.php");
        }
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

namespace Database\Seeders\CmisWebsite;

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
