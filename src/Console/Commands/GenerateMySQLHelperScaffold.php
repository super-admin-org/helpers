<?php
/**
 * Author: Talemul Islam
 * Website: https://talemul.com
 */

namespace SuperAdmin\Admin\Helpers\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Carbon\Carbon;
use Doctrine\DBAL\Schema\Column;
use Doctrine\DBAL\Types\Type;

class GenerateMySQLHelperScaffold extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'scaffold:generate-from-mysql-tables  {--remove-prefix=}';
    /**
     * The console command description.
     * @var string
     */
    protected $description = 'Generate helper_scaffolds and helper_scaffold_details using native MySQL introspection';

    protected $laravelTypeMap = [
        'int' => 'integer',
        'tinyint' => 'tinyInteger',
        'smallint' => 'smallInteger',
        'mediumint' => 'mediumInteger',
        'bigint' => 'bigInteger',
        'varchar' => 'string',
        'char' => 'char',
        'text' => 'text',
        'mediumtext' => 'mediumText',
        'longtext' => 'longText',
        'timestamp' => 'timestamp',
        'datetime' => 'dateTime',
        'date' => 'date',
        'time' => 'time',
        'float' => 'float',
        'double' => 'double',
        'decimal' => 'decimal',
        'json' => 'json',
        'enum' => 'enum',
        'binary' => 'binary',
        'boolean' => 'boolean',
    ];

    protected $superAdminTypeMap = [
        // integers
        'int'       => 'number',      // $form->number()
        'tinyint'   => 'switch',      // often used as boolean flag
        'smallint'  => 'number',
        'mediumint' => 'number',
        'bigint'    => 'number',

        // string-like
        'varchar'   => 'text',        // $form->text()
        'char'      => 'text',

        // text
        'text'      => 'textarea',    // $form->textarea()
        'mediumtext'=> 'textarea',
        'longtext'  => 'textarea',

        // date & time
        'timestamp' => 'datetime',    // $form->datetime()
        'datetime'  => 'datetime',
        'date'      => 'date',        // $form->date()
        'time'      => 'time',        // $form->time()

        // numeric
        'float'     => 'number',
        'double'    => 'number',
        'decimal'   => 'number',

        // json
        'json'      => 'textarea',        // or 'textarea' if you prefer

        // enum
        'enum'      => 'select',      // you can parse enum values to options

        // binary / blob
        'binary'    => 'file',        // or 'textarea' if not a real file

        // boolean
        'boolean'   => 'switch',      // $form->switch()
    ];
    public function handle()
    {
        // Get optional prefix to remove
        $removePrefix = (string) $this->option('remove-prefix');
        $dbName = env('DB_DATABASE');
        $tables = DB::select("SHOW TABLES");
        $tableKey = "Tables_in_$dbName";

        $excluded = ['migrations', 'helper_scaffolds', 'helper_scaffold_details'];

        foreach ($tables as $tableRow) {
            $table = $tableRow->$tableKey;
            if (in_array($table, $excluded)) continue;

            $columns = DB::select("SHOW FULL COLUMNS FROM `$table`");
            $pkResult = DB::select("SHOW KEYS FROM `$table` WHERE Key_name = 'PRIMARY'");
            $primaryKey = $pkResult[0]->Column_name ?? 'id';

            $hasTimestamps = collect($columns)->pluck('Field')->intersect(['created_at', 'updated_at'])->count() === 2;
            $hasSoftDeletes = collect($columns)->pluck('Field')->contains('deleted_at');

            $logicalTable = $removePrefix && Str::startsWith($table, $removePrefix)
                ? Str::after($table, $removePrefix)
                : $table;
            $modelName = 'App\\Models\\' . Str::studly(Str::singular($logicalTable));
            $controllerName = 'App\\Admin\\Controllers\\' . Str::studly(Str::singular($logicalTable)) . 'Controller';

            $scaffoldId = DB::table('helper_scaffolds')->insertGetId([
                'table_name' => $logicalTable,
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
                if (in_array($col->Field, ['id', 'created_at', 'updated_at', 'deleted_at'])) continue;

                $type = $this->mapToLaravelType($col->Type);
                $superAdmintype = $this->mapToSuperAdminType($col->Type);

                DB::table('helper_scaffold_details')->insert([
                    'scaffold_id' => $scaffoldId,
                    'name' => $col->Field,
                    'type' => $type,
                    'nullable' => $col->Null === 'YES' ? 1 : 0,
                    'key' => $col->Key ?: null,
                    'default' => $col->Default,
                    'comment' => $col->Comment ?? null,
                    'order' => $order++,
                    'input_type' => $superAdmintype,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            $this->info("✅ Scaffold generated for table: $table");
        }
    }

    protected function mapToLaravelType(string $dbType): string
    {
        $typeKey = strtolower(preg_replace('/\(.*/', '', $dbType));
        return $this->laravelTypeMap[$typeKey] ?? 'string';
    }

    protected function mapToSuperAdminType(string $dbType): string
    {
        $typeKey = strtolower(preg_replace('/\(.*/', '', $dbType));
        return $this->superAdminTypeMap[$typeKey] ?? 'string';
    }
}
