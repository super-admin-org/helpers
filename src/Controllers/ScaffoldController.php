<?php

namespace SuperAdmin\Admin\Helpers\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\MessageBag;
use Illuminate\Support\Str;
use SuperAdmin\Admin\Auth\Database\Menu;
use SuperAdmin\Admin\Helpers\Model\Scaffold;
use SuperAdmin\Admin\Helpers\Model\ScaffoldDetail;
use SuperAdmin\Admin\Helpers\Scaffold\MigrationCreator;
use SuperAdmin\Admin\Helpers\Scaffold\ModelCreator;
use SuperAdmin\Admin\Helpers\Scaffold\ControllerCreator;
use SuperAdmin\Admin\Layout\Content;
use Illuminate\Support\Facades\File;
use Illuminate\Database\Eloquent\Model as EloquentModel;
use SuperAdmin\Admin\Facades\Admin;


class ScaffoldController extends Controller
{
    public function index(Content $content)
    {
        $query = Scaffold::query();

        // Handle search
        if (request()->filled('search')) {
            $search = request('search');
            $query->where(function ($q) use ($search) {
                $q->where('table_name', 'like', "%{$search}%")
                    ->orWhere('model_name', 'like', "%{$search}%")
                    ->orWhere('controller_name', 'like', "%{$search}%");
            });
        }

        // Handle sorting
        $sort = request('sort', 'id'); // default column
        $direction = request('direction', 'asc'); // default direction
        $allowedSorts = ['id', 'table_name', 'model_name', 'controller_name', 'created_at'];

        if (!in_array($sort, $allowedSorts)) {
            $sort = 'id';
        }

        $scaffolds = $query->orderBy($sort, $direction)->paginate(15);

        return $content
            ->header('Scaffold List')
            ->description('Browse all scaffold definitions')
            ->row(view('super-admin-helpers::scaffold_list', compact('scaffolds', 'sort', 'direction')));
    }


    public function create(Content $content)
    {
        $content->header('Scaffold');

        $dbTypes = [
            'string', 'integer', 'text', 'float', 'double', 'decimal', 'boolean', 'date', 'time',
            'dateTime', 'timestamp', 'char', 'mediumText', 'longText', 'tinyInteger', 'smallInteger',
            'mediumInteger', 'bigInteger', 'unsignedTinyInteger', 'unsignedSmallInteger', 'unsignedMediumInteger',
            'unsignedInteger', 'unsignedBigInteger', 'enum', 'json', 'jsonb', 'dateTimeTz', 'timeTz',
            'timestampTz', 'nullableTimestamps', 'binary', 'ipAddress', 'macAddress',
        ];
        $modelsForSelect = $this->listAppModels(); // NEW
        // Admin::script($this->scaffoldAutofillJs());
        $action = route('scaffold.store');
        $content->row(view('super-admin-helpers::scaffold', compact('dbTypes', 'modelsForSelect', 'action')));

        return $content;
    }


    public function edit($id, Content $content)
    {
        $scaffold = Scaffold::with('details')->findOrFail($id);

        $dbTypes = [
            'string', 'integer', 'text', 'float', 'double', 'decimal', 'boolean', 'date', 'time',
            'dateTime', 'timestamp', 'char', 'mediumText', 'longText', 'tinyInteger', 'smallInteger',
            'mediumInteger', 'bigInteger', 'unsignedTinyInteger', 'unsignedSmallInteger', 'unsignedMediumInteger',
            'unsignedInteger', 'unsignedBigInteger', 'enum', 'json', 'jsonb', 'dateTimeTz', 'timeTz',
            'timestampTz', 'nullableTimestamps', 'binary', 'ipAddress', 'macAddress',
        ];
        // Admin::script($this->scaffoldAutofillJs());
        $action = route('scaffold.update', $scaffold->id);
        $modelsForSelect = $this->listAppModels(); // NEW
        return $content
            ->header('Edit Scaffold')
            ->row(view('super-admin-helpers::scaffold', compact('scaffold', 'modelsForSelect', 'dbTypes', 'action')));
    }


    public function store(Request $request)
    {
        $request->validate([
            'table_name' => 'required|string',
            'fields' => 'required|array',
        ]);

        [$scaffold, $paths, $message] = $this->saveScaffold($request);

        return $this->backWithSuccess($paths, $message);
    }

    public function update(Request $request, $id)
    {
        $request->validate([
            'table_name' => 'required|string',
            'fields' => 'required|array',
        ]);

        $scaffold = Scaffold::findOrFail($id);

        [$scaffold, $paths, $message] = $this->saveScaffold($request, $scaffold, false);

        admin_toastr('Scaffold updated successfully', 'success');
        return $this->backWithSuccess($paths, $message);
    }

    protected function saveScaffold(Request $request, Scaffold $scaffold = null, $menu_item = true)
    {
        $paths = [];
        $message = '';

        DB::transaction(function () use ($request, &$scaffold) {
            if (!$scaffold) {
                $scaffold = new Scaffold();
            }

            $scaffold->fill([
                'table_name' => $request->input('table_name'),
                'model_name' => $request->input('model_name'),
                'controller_name' => $request->input('controller_name'),
                'create_options' => $request->input('create', []),
                'primary_key' => $request->input('primary_key', 'id'),
                'timestamps' => $request->has('timestamps'),
                'soft_deletes' => $request->has('soft_deletes'),
            ])->save();

            // Remove old details if updating
            if ($scaffold->exists) {
                $scaffold->details()->delete();
            }

            foreach ($request->input('fields', []) as $index => $field) {
                $scaffold->details()->create([
                    'name' => $field['name'] ?? null,
                    'type' => $field['type'] ?? null,
                    'nullable' => isset($field['nullable']),
                    'key' => $field['key'] ?? null,
                    'default' => $field['default'] ?? null,
                    'comment' => $field['comment'] ?? null,
                    'input_type' => $field['input_type'] ?? null,
                    'options_source' => $field['options_source'] ?? null,
                    'options_value_col' => $field['options_value_col'] ?? null,
                    'options_label_col' => $field['options_label_col'] ?? null,
                    'order' => $index,
                ]);
            }
        });

        // File generation
        try {
            // 1. Model
            if (in_array('model', $request->get('create'))) {
                $modelPath = app_path(str_replace('\\', '/', str_replace('App\\', '', $request->get('model_name'))) . '.php');
                $this->backupIfExists($modelPath);

                $paths['model'] = (new ModelCreator($scaffold))
                    ->create($scaffold);
            }

            // 2. Migration
            if (in_array('migration', $request->get('create'))) {
                $tableName = $request->get('table_name');
                $migrationName = 'create_' . $tableName . '_table';
                $migrationFiles = glob(database_path('migrations/*_' . $migrationName . '.php'));
                foreach ($migrationFiles as $file) {
                    $this->backupIfExists($file);
                }

                $paths['migration'] = (new MigrationCreator(app('files'), '/'))->buildBluePrint(
                    $request->get('fields'),
                    $request->get('primary_key', 'id'),
                    $request->get('timestamps') == 'on' || $request->has('timestamps'),
                    $request->get('soft_deletes') == 'on' || $request->has('soft_deletes')
                )->create($migrationName, database_path('migrations'), $tableName);
            }

            // 3. Controller
            if (in_array('controller', $request->get('create'))) {
                $controllerPath = app_path(str_replace('\\', '/', str_replace('App\\', '', $request->get('controller_name'))) . '.php');
                $this->backupIfExists($controllerPath);

                $paths['controller'] = (new ControllerCreator($request->get('controller_name')))
                    ->create($scaffold->id);
            }

            // 4. Migrate DB
            if (in_array('migrate', $request->get('create'))) {
                $table = $request->input('table_name');
                $connection = config('database.default');
                $schema = DB::connection($connection)->getSchemaBuilder();
                $shouldMigrate = true;

                if ($schema->hasTable($table)) {
                    if (in_array('recreate_table', $request->get('create'))) {
                        $schema->drop($table);
                        $message .= "<br>Table <code>{$table}</code> dropped successfully.";
                    } else {
                        $shouldMigrate = false;
                        $message .= "<br>Migration skipped: Table <code>{$table}</code> already exists.";
                    }
                }

                if ($shouldMigrate) {
                    Artisan::call('migrate');
                    $message .= str_replace('Migrated:', '<br>Migrated:', Artisan::output());
                }
            }


            // 5. Menu
            if (in_array('menu_item', $request->get('create'))) {
                $route = $this->createMenuItem($request);
                $message .= '<br>Menu item created at: ' . $route;
            }
            // 6. Admin Route
            $this->ensureAdminRoute($scaffold, $route);


        } catch (\Exception $e) {
            Log::error('Scaffold generation failed', ['exception' => $e]);
            app('files')->delete($paths);
            //throw $e;
        }

        return [$scaffold, $paths, $message];
    }

    protected function backupIfExists($path)
    {
        if (file_exists($path)) {
            $backupDir = storage_path('scaffold_backups/' . date('YMd_His'));
            if (!is_dir($backupDir)) {
                mkdir($backupDir, 0755, true);
            }

            $filename = basename($path);
            $newPath = $backupDir . '/' . $filename;

            rename($path, $newPath);
            Log::info("Backed up existing file: $path to $newPath");
        }
    }

    public function destroy($id)
    {
        $scaffold = Scaffold::with('details')->findOrFail($id);

        try {
            $paths = [];

            // Build file paths
            $modelPath = app_path(str_replace('\\', '/', str_replace('App\\', '', $scaffold->model_name)) . '.php');
            $controllerPath = app_path(str_replace('\\', '/', str_replace('App\\', '', $scaffold->controller_name)) . '.php');
            $migrationPattern = '*_create_' . $scaffold->table_name . '_table.php';
            $migrationFiles = glob(database_path('migrations/' . $migrationPattern));

            // Backup and delete files
            $this->backupIfExists($modelPath);
            $this->backupIfExists($controllerPath);
            foreach ($migrationFiles as $file) {
                $this->backupIfExists($file);
            }

            // Delete DB records
            $scaffold->details()->delete();
            $scaffold->delete();

            return response()->json([
                'status' => 'success',
                'message' => 'Scaffold and associated files were deleted successfully.'
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to delete scaffold: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to delete scaffold. Check logs for details.'
            ], 500);
        }
    }


    public function getRoute($request)
    {
        return Str::plural(Str::kebab(class_basename($request->get('model_name'))));
    }

    public function createMenuItem($request)
    {
        $route = $this->getRoute($request);
        $lastOrder = Menu::max('order');
        $root = [
            'parent_id' => 0,
            'order' => $lastOrder++,
            'title' => ucfirst($route),
            'icon' => 'icon-file',
            'uri' => $route,
        ];
        $root = Menu::firstOrCreate($root);

        return $route;
    }

    public function getControllerName($str)
    {
        return last(explode('\\', $str));
    }

    protected function backWithException(\Exception $exception)
    {
        $error = new MessageBag([
            'title' => 'Error',
            'message' => $exception->getMessage(),
        ]);

        return back()->withInput()->with(compact('error'));
    }

    protected function backWithSuccess($paths, $message)
    {
        $messages = [];

        foreach ($paths as $name => $path) {
            $messages[] = ucfirst($name) . ": $path";
        }

        $messages[] = $message;

        $success = new MessageBag([
            'title' => 'Success',
            'message' => implode('<br />', $messages),
        ]);

        return back()->with(compact('success'));
    }

    /**
     * List all non-abstract Eloquent models under app/Models.
     * @return array<string> FQCNs like "App\Models\User"
     */
    private function listAppModels(): array
    {
        $models = [];
        foreach (File::allFiles(app_path('Models')) as $file) {
            $fqcn = $this->classFromFile($file->getRealPath());
            if (!$fqcn) continue;

            if (is_subclass_of($fqcn, EloquentModel::class) && !(new \ReflectionClass($fqcn))->isAbstract()) {
                $models[] = $fqcn;
            }
        }
        sort($models);
        return $models;
    }

    /**
     * Parse FQCN from a PHP file using token_get_all.
     */
    private function classFromFile(string $path): ?string
    {
        $code = file_get_contents($path);
        $tokens = token_get_all($code);
        $namespace = '';
        $class = '';

        for ($i = 0, $len = count($tokens); $i < $len; $i++) {
            if ($tokens[$i][0] === T_NAMESPACE) {
                $ns = [];
                for ($j = $i + 1; $j < $len; $j++) {
                    if ($tokens[$j] === '{' || $tokens[$j] === ';') break;
                    if (is_array($tokens[$j])) $ns[] = $tokens[$j][1];
                }
                $namespace = trim(implode('', $ns));
            }
            if ($tokens[$i][0] === T_CLASS) {
                // Ignore anonymous classes
                $isAnon = false;
                for ($k = $i - 1; $k >= 0 && is_array($tokens[$k]); $k--) {
                    if ($tokens[$k][0] === T_NEW) {
                        $isAnon = true;
                        break;
                    }
                    if ($tokens[$k][0] === T_STRING) break;
                }
                if ($isAnon) continue;

                // Next string token after T_CLASS is the class name
                for ($j = $i + 1; $j < $len; $j++) {
                    if ($tokens[$j][0] === T_STRING) {
                        $class = $tokens[$j][1];
                        break 2;
                    }
                }
            }
        }

        if ($class) {
            return $namespace ? $namespace . '\\' . $class : $class;
        }
        return null;
    }

    protected function ensureAdminRoute(Scaffold $scaffold, $route): void
    {
        $routeFile = base_path('app/Admin/routes.php');
        if (!File::exists($routeFile)) {
            // If file is missing, create it with a full group and the resource.
            $this->writeNewAdminRoutesFile($scaffold,$route);
            return;
        }

        $content = File::get($routeFile);

        // slug: student_info -> student-info
//        $slug = Str::kebab(Str::singular($scaffold->table_name ?: ''));
//        if ($slug === '') {
//            $slug = Str::kebab(class_basename($scaffold->model_name ?: 'resource'));
//        }
        $slug = $route;

        // Normalize FQCN (handles AppAdminControllersX -> App\Admin\Controllers\X)
        $controllerFqcn = $this->normalizeFqcn((string)$scaffold->controller_name);
        if ($controllerFqcn === '') {
            $stud = Str::studly(Str::singular($scaffold->table_name));
            $controllerFqcn = "App\\Admin\\Controllers\\{$stud}Controller";
        }

        // If route already present (either resources([...]) or resource(...)), do nothing.
        $already = Str::contains($content, "'{$slug}' => {$controllerFqcn}::class")
            || Str::contains($content, "\$router->resource('{$slug}', {$controllerFqcn}::class")
            || Str::contains($content, "\$router->resources([") && Str::contains($content, "'{$slug}'") && Str::contains($content, class_basename($controllerFqcn));

        if ($already) {
            try {
                Artisan::call('route:clear');
            } catch (\Throwable $e) {
            }
            return;
        }

        // Prefer adding into an existing $router->resources([...]) block
        if (preg_match('/\$router->resources\(\s*\[(.*?)\]\s*\);/s', $content, $m, PREG_OFFSET_CAPTURE)) {
            $fullBlockStart = $m[0][1];
            $innerStart = $m[1][1];
            $innerLen = strlen($m[1][0]);
            $insertLine = "        '{$slug}' => {$controllerFqcn}::class,\n";

            // Insert at end of inner array, before ]);
            $content = substr_replace($content, $m[1][0] . $insertLine, $innerStart, $innerLen);
            File::put($routeFile, $this->normalizeEol($content));
            try {
                Artisan::call('route:clear');
            } catch (\Throwable $e) {
            }
            return;
        }

        // Else, try to insert a single resource() before the closing of the admin Route::group
        if (preg_match('/Route::group\(\s*\[(.*?)\]\s*,\s*function\s*\(Router\s*\$router\)\s*\{/s', $content)) {
            $closingPos = strrpos($content, '});');
            if ($closingPos !== false) {
                $line = "\$router->resource('{$slug}', {$controllerFqcn}::class);\n";
                $content = substr_replace($content, $line, $closingPos, 0);
                File::put($routeFile, $this->normalizeEol($content));
                try {
                    Artisan::call('route:clear');
                } catch (\Throwable $e) {
                }
                return;
            }
        }

        // Fallback: append a full admin group
        $this->appendAdminGroupWithResource($scaffold, $routeFile);
        try {
            Artisan::call('route:clear');
        } catch (\Throwable $e) {
        }
    }

    /** Create a fresh routes file with the admin group + resource. */
    protected function writeNewAdminRoutesFile($scaffold,$slug): void
    {
        $routeFile = base_path('app/Admin/routes.php');
        $controllerFqcn = $this->normalizeFqcn((string)$scaffold->controller_name);
        // $slug = Str::kebab(Str::singular($scaffold->table_name));
        $stub = <<<PHP
<?php

use Illuminate\Routing\Router;

Route::group([
    'prefix'     => config('admin.route.prefix'),
    'namespace'  => config('admin.route.namespace'),
    'middleware' => config('admin.route.middleware'),
    'as'         => config('admin.route.prefix') . '.',
], function (Router \$router) {
    \$router->resource('{$slug}', {$controllerFqcn}::class);
});

PHP;
        File::put($routeFile, $this->normalizeEol($stub));
    }

    /** Append a new admin group if nothing matches. */
    protected function appendAdminGroupWithResource($scaffold, string $routeFile): void
    {
        $controllerFqcn = $this->normalizeFqcn((string)$scaffold->controller_name);
        $slug = $this->getRoute();
        $block = <<<PHP

Route::group([
    'prefix'     => config('admin.route.prefix'),
    'namespace'  => config('admin.route.namespace'),
    'middleware' => config('admin.route.middleware'),
    'as'         => config('admin.route.prefix') . '.',
], function (Router \$router) {
    \$router->resource('{$slug}', {$controllerFqcn}::class);
});

PHP;
        File::append($routeFile, $this->normalizeEol($block));
    }

    /** Normalize FQCNs like "AppAdminControllersX" -> "App\\Admin\\Controllers\\X". */
    protected function normalizeFqcn(?string $fqcn): string
    {
        $s = trim((string)$fqcn);
        if ($s === '') return '';
        $s = ltrim($s, '\\');
        if (strpos($s, '\\') === false && Str::startsWith($s, 'App')) {
            $parts = preg_split('/(?=[A-Z])/', $s, -1, PREG_SPLIT_NO_EMPTY);
            if (!empty($parts)) $s = implode('\\', $parts);
        }
        return $s;
    }

    protected function normalizeEol(string $code): string
    {
        return rtrim(preg_replace("/\r\n?/", "\n", $code)) . "\n";
    }
}
