<?php
/**
 * Author: Talemul Islam
 * Website: https://talemul.com
 */

namespace SuperAdmin\Admin\Helpers\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class GeneratePgHelperScaffold extends Command
{
    protected $signature = 'scaffold:generate-from-pg';
    protected $description = 'Generate helper_scaffolds and helper_scaffold_details using PostgreSQL schema metadata (no doctrine/dbal)';

    protected $laravelTypeMap = [
        'character varying' => 'string',
        'varchar' => 'string',
        'character' => 'char',
        'text' => 'text',
        'boolean' => 'boolean',
        'integer' => 'integer',
        'bigint' => 'bigInteger',
        'smallint' => 'smallInteger',
        'timestamp without time zone' => 'timestamp',
        'timestamp with time zone' => 'timestampTz',
        'date' => 'date',
        'time without time zone' => 'time',
        'time with time zone' => 'timeTz',
        'json' => 'json',
        'jsonb' => 'jsonb',
        'numeric' => 'decimal',
        'double precision' => 'double',
        'real' => 'float',
        'bytea' => 'binary',
        'inet' => 'ipAddress',
        'macaddr' => 'macAddress',
        'uuid' => 'uuid',
    ];

    public function handle()
    {
        $schema = 'public'; // Change if your tables are in a different schema
        $excluded = ['migrations', 'helper_scaffolds', 'helper_scaffold_details'];

        $tables = DB::select("
            SELECT table_name
            FROM information_schema.tables
            WHERE table_schema = ? AND table_type = 'BASE TABLE'
        ", [$schema]);

        foreach ($tables as $tableRow) {
            $table = $tableRow->table_name;
            if (in_array($table, $excluded)) continue;

            $columns = DB::select("
                SELECT column_name, is_nullable, data_type, column_default
                FROM information_schema.columns
                WHERE table_name = ? AND table_schema = ?
                ORDER BY ordinal_position
            ", [$table, $schema]);

            $pkResult = DB::select("
                SELECT a.attname AS column_name
                FROM pg_index i
                JOIN pg_attribute a ON a.attrelid = i.indrelid AND a.attnum = ANY(i.indkey)
                WHERE i.indrelid = ?::regclass AND i.indisprimary
            ", [$table]);

            $primaryKey = $pkResult[0]->column_name ?? 'id';
            $columnNames = collect($columns)->pluck('column_name');
            $hasTimestamps = $columnNames->intersect(['created_at', 'updated_at'])->count() === 2;
            $hasSoftDeletes = $columnNames->contains('deleted_at');

            $modelName = 'App\\Models\\' . Str::studly(Str::singular($table));
            $controllerName = 'App\\Admin\\Controllers\\' . Str::studly(Str::singular($table)) . 'Controller';

            $scaffoldId = DB::table('helper_scaffolds')->insertGetId([
                'table_name' => $table,
                'model_name' => $modelName,
                'controller_name' => $controllerName,
                'create_options' => json_encode(['migration', 'model', 'controller', 'migrate', 'menu_item','recreate_table']),
                'primary_key' => $primaryKey,
                'timestamps' => $hasTimestamps,
                'soft_deletes' => $hasSoftDeletes,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $order = 0;
            foreach ($columns as $col) {
                if (in_array($col->column_name, [$primaryKey, 'created_at', 'updated_at', 'deleted_at'])) continue;

                DB::table('helper_scaffold_details')->insert([
                    'scaffold_id' => $scaffoldId,
                    'name' => $col->column_name,
                    'type' => $this->mapToLaravelType($col->data_type),
                    'nullable' => $col->is_nullable === 'YES' ? 1 : 0,
                    'key' => $col->column_name === $primaryKey ? 'PRI' : null,
                    'default' => $col->column_default,
                    'comment' => null,
                    'order' => $order++,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            $this->info("✅ Scaffold generated for table: $table");
        }
    }

    protected function mapToLaravelType(string $pgType): string
    {
        return $this->laravelTypeMap[strtolower($pgType)] ?? 'string';
    }
}
