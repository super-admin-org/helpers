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
use SuperAdmin\Admin\Helpers\Scaffold\ApiControllerCreator;
use SuperAdmin\Admin\Helpers\Scaffold\BladeCrudCreator;
use SuperAdmin\Admin\Helpers\Scaffold\PestTestCreator;
use SuperAdmin\Admin\Layout\Content;
use Illuminate\Support\Facades\File;
use Illuminate\Database\Eloquent\Model as EloquentModel;
use SuperAdmin\Admin\Facades\Admin;


/**
 * Handles the deletion of a scaffold and its associated resources.
 *
 * Deletes the specified scaffold record and its associated details. This method also ensures
 * that any resources generated for the scaffold, such as models, migrations, controllers,
 * and menus, can be appropriately removed if necessary. The deletion process logs critical
 * activities for auditing purposes and provides detailed feedback on the operation's success.
 *
 * @param int|string $id The identifier of the scaffold record to be deleted.
 *
 * @return \Illuminate\Http\JsonResponse A JSON response indicating the result of the deletion process.
 * @throws \Illuminate\Database\Eloquent\ModelNotFoundException If the scaffold with the given ID does not exist.
 */
class ScaffoldController extends Controller
{
    /**
     * Displays the list of scaffolds with optional search and sort functionality.
     *
     * This method retrieves a paginated list of scaffold definitions from the database,
     * allowing for client-side filtering and sorting. Leveraged search fields include
     * `table_name`, `model_name`, and `controller_name`. Sorting is applied to one of
     * the allowed columns and follows the specified direction.
     *
     * @param Content $content The page content constructor for rendering the view.
     *
     * @return \Illuminate\Http\Response The rendered view containing a list of scaffolds.
     */
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

    /**
     * Renders the 'Scaffold' creation form in the admin interface.
     *
     * This method sets up the content header and prepares various data fields
     * required for rendering the creation form. Database data types, application
     * models for selection, and the form action URL are compiled for the view.
     * Finally, it appends the rendered view to the content row and returns the
     * content object.
     *
     * @param Content $content The content object used for rendering in the admin interface.
     *
     * @return Content The modified content object with the compiled scaffold creation view.
     */
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

    /**
     * Displays the scaffold edit form in the admin panel.
     *
     * Retrieves the scaffold along with its details for editing, then prepares
     * necessary data such as database field types, available models for selection,
     * and the update action URL. Finally, returns the content for the edit form
     * view with the compiled data.
     *
     * @param int|string $id The identifier of the scaffold record to be edited.
     * @param Content $content The content instance for the admin panel view rendering.
     *
     * @return \Encore\Admin\Layout\Content The admin view content for editing the scaffold.
     *
     * @throws \Illuminate\Database\Eloquent\ModelNotFoundException If the scaffold record is not found.
     */
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

    /**
     * Handles the storage of a new scaffold based on the provided request.
     *
     * Validates the incoming request for required fields and processes the scaffold.
     * Delegates the scaffold saving logic to `saveScaffold`, which returns the scaffold object,
     * relevant paths, and a success message.
     * Finally, it redirects back with a success response.
     *
     * @param Request $request The HTTP request instance containing scaffold details.
     *
     * @return mixed Redirect response with success message and associated scaffold paths.
     */
    public function store(Request $request)
    {
        $request->validate([
            'table_name' => 'required|string',
            'fields' => 'required|array',
        ]);

        [$scaffold, $paths, $message] = $this->saveScaffold($request);

        return $this->backWithSuccess($paths, $message);
    }

    /**
     * Updates an existing scaffold record with the provided data.
     *
     * Validates the request data for required fields such as `table_name` and `fields`.
     * Retrieves the specified scaffold using the given ID and updates it using the provided data.
     * Upon successful update, displays a success message and redirects back with relevant paths and details.
     *
     * @param Request $request The HTTP request object containing the input data.
     * @param int $id The ID of the scaffold record to be updated.
     *
     * @return mixed Redirect response with success status and data.
     * @throws \Illuminate\Database\Eloquent\ModelNotFoundException If the scaffold with the given ID does not exist.
     */
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

    /**
     * Saves a Scaffold instance based on the provided request and generates corresponding resources.
     *
     * This method handles the saving and updating of the Scaffold instance, including its associated
     * details (fields). Additionally, it generates various resources such as models, migrations,
     * controllers, and routes, based on the options provided in the request. It also supports features
     * like database migration, menu item creation, and API controller generation.
     *
     * Key functionalities include:
     * - Saving or updating the Scaffold instance in the database.
     * - Handling related fields by updating or creating scaffold details.
     * - Generating models, migrations, controllers, and other resources based on the request options.
     * - Managing database migrations for the associated table, conditionally recreating the table if needed.
     * - Creating menu items and updating the admin route file.
     * - Ensuring the existence of relevant API controllers, traits, and routes.
     *
     * Upon any failure during the resource generation, it attempts to clean up generated files and logs
     * the error.
     *
     * @param Request $request The request containing scaffold and resource configuration.
     * @param Scaffold|null $scaffold The existing scaffold instance to update, or null to create a new one.
     * @param bool $menu_item Whether to include a menu item creation step in the scaffold process.
     *
     * @return array An array containing the scaffold instance, generated paths, and a message indicating the result.
     */
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
                try {
                    $modelPath = app_path(str_replace('\\', '/', str_replace('App\\', '', $request->get('model_name'))) . '.php');
                    $this->backupIfExists($modelPath);

                    $paths['model'] = (new ModelCreator($scaffold))
                        ->create($scaffold);
                } catch (\Throwable $e) {
                    Log::error('Generating model failed: ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
                }

            }

            // 2. Migration
            if (in_array('migration', $request->get('create'))) {
                try {
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
                } catch (\Throwable $e) {
                    Log::error('Generating migration failed: ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
                }

            }

            // 3. Controller
            if (in_array('controller', $request->get('create'))) {
                try {
                    $controllerPath = app_path(str_replace('\\', '/', str_replace('App\\', '', $request->get('controller_name'))) . '.php');
                    $this->backupIfExists($controllerPath);

                    $paths['controller'] = (new ControllerCreator($request->get('controller_name')))
                        ->create($scaffold->id);
                    //  Admin Route
                    $route=$this->getRoute($request);
                    $this->ensureAdminRoute($scaffold, $route);
                } catch (\Throwable $e) {
                    Log::error('Generating super-admin controller failed: ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
                }
            }

            // 4. Migrate DB
            if (in_array('migrate', $request->get('create'))) {
                try {
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

                } catch (\Throwable $e) {
                    Log::error('Generating migrate failed: ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
                }
            }

            $route = $this->createMenuItem($request);
            $message .= '<br>Menu item created at: ' . $route;


            if (in_array('api', $request->get('create'))) {
                try {
                    // make sure the trait exists
                    $this->ensureResponseMapperTrait();

// generate API controller (uses the trait via the stub)
                    $apiPath = (new ApiControllerCreator())->create($scaffold);

// make sure the API route exists
                    $this->ensureApiRoute($scaffold);
                    $message .= '<br>API Controller : ' . $apiPath . ' We use response mapper trait to map the response to the correct format.';
                } catch (\Throwable $e) {
                    Log::error('Generating api failed: ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
                }
            }
            // 7. API controller laravel mode


            if (in_array('blade_crud', $request->get('create'))) {
                try {
                    // 1) Generate Blade CRUD
                    $result = (new BladeCrudCreator())->create($scaffold);
                    // 2) Ensure web route
                    $this->ensureWebRoute($scaffold);
                    $message .= '<br>Web Controller : ' . $result['controller'];
                    $message .= '<br>Web views : ' . json_encode($result['views']) .
                        '  We create a web route for the blade crud.';
                } catch (\Throwable $e) {
                    Log::error('Generating blade_crud failed: ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
                }
            }else{
                $message .= '<br> message despla failed for blade_crud failed: .';
            }

            // After existing generators inside saveScaffold(...)

            if (in_array('test_case', $request->get('create'))) {
                try {
                    $testResult = (new PestTestCreator())
                        ->create($scaffold, [
                            'with_factory' => true,         // set false if you don’t want factories
                            'overwrite' => true,        // set true to force-regenerate
                        ]);
                    $message .= '<br>Test Case : ' . json_encode($testResult) . '  We create test case for the crud.';
                } catch (\Throwable $e) {
                    Log::error('Generating tests failed: ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
                }
            }else{
                $message .= '<br> message despla failed for test case failed: .';
            }

        } catch (\Exception $e) {
            Log::error('Scaffold generation failed', ['exception' => $e]);
            app('files')->delete($paths);
            //throw $e;
        }

        return [$scaffold, $paths, $message];
    }

    /**
     * Backs up an existing file at the specified path if it exists.
     *
     * This method checks if the given file exists, and if so, it creates a backup
     * in a timestamped directory under the `storage/scaffold_backups` path. The filename
     * remains the same, but it is moved to the newly created backup directory. Logs
     * an informational message upon successful backup.
     *
     * @param string $path The full path of the file to back up (if it exists).
     *
     * @return void
     */
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

    /**
     * Deletes a scaffold and its associated files and database records.
     *
     * This method performs the following actions:
     * - Retrieves the scaffold record with its associated details.
     * - Builds paths to the scaffold's model, controller, and migration files.
     * - Creates backups of the files, if they exist, before deleting them.
     * - Deletes the associated database records for the scaffold and its details.
     * - Returns a JSON response indicating success or failure.
     *
     * In case of failure, an error is logged and a 500 status code is returned with an error message.
     *
     * @param int|string $id The ID of the scaffold record to be deleted.
     *
     * @return \Illuminate\Http\JsonResponse JSON response indicating the outcome of the operation.
     */
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

    /**
     * Generates a pluralized, kebab-case route slug based on the given model name.
     *
     * The method retrieves the `model_name` parameter from the given request, converts its class basename
     * into kebab-case, and pluralizes it to create the route slug.
     *
     * @param \Illuminate\Http\Request $request The HTTP request containing the `model_name` parameter.
     *
     * @return string The generated route slug in pluralized kebab-case format.
     */
    public function getRoute($request)
    {
        return Str::plural(Str::kebab(class_basename($request->get('model_name'))));
    }

    /**
     * Creates a new menu item for the application menu structure.
     *
     * Determines the route based on the request, retrieves the highest current order value
     * in the menu, and creates a menu item with the given information.
     * The menu item is created only if it does not already exist.
     *
     * @param mixed $request The input request containing data to determine the route.
     *
     * @return string The route associated with the created menu item.
     */
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

    /**
     * Extracts and returns the controller name from a fully qualified class name (FQCN).
     *
     * This method retrieves the last segment of a given FQCN, which is presumed
     * to represent the controller name.
     *
     * @param string $str The fully qualified class name to process.
     *
     * @return string The extracted controller name.
     */
    public function getControllerName($str)
    {
        return last(explode('\\', $str));
    }

    /**
     * Redirects back to the previous page with the provided exception details.
     *
     * This method captures the exception message and wraps it into a `MessageBag`
     * for structured error handling. The error message is then flashed to the session,
     * allowing it to be displayed after the redirection.
     *
     * @param \Exception $exception The exception to be handled and passed back.
     *
     * @return \Illuminate\Http\RedirectResponse A redirect response to the previous page with input and error details.
     */
    protected function backWithException(\Exception $exception)
    {
        $error = new MessageBag([
            'title' => 'Error',
            'message' => $exception->getMessage(),
        ]);

        return back()->withInput()->with(compact('error'));
    }

    /**
     * Redirects back to the previous page with a success message and additional details.
     *
     * This method generates a formatted success message by combining the provided paths
     * and message and redirects back to the user's previous location. The formatted
     * success message is structured to include a title and the message body.
     *
     * @param array $paths An associative array where keys represent entity names and values represent paths.
     * @param string $message The main success message to display.
     *
     * @return \Illuminate\Http\RedirectResponse A redirect response with the success message.
     */
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
     * Retrieves a list of all Eloquent models within the application's Models directory.
     *
     * This method scans the Models directory for all files, determines their fully qualified class names (FQCN),
     * and checks if they are valid Eloquent model subclasses. Abstract classes are excluded from the results.
     * The resulting list of models is sorted alphabetically before being returned.
     *
     * @return array An array of fully qualified class names (FQCN) of detected Eloquent models.
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
     * Extracts the fully qualified class name (FQCN) from a given PHP file.
     *
     * This method analyzes the tokens of the given PHP file to determine its namespace and
     * class name, combining them to produce the FQCN. If the file contains an anonymous class,
     * it will be ignored. Returns null if no class can be identified.
     *
     * @param string $path The path to the PHP file from which to extract the class name.
     *
     * @return string|null The fully qualified class name, or null if the file does not contain a valid class.
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

    /**
     * Ensures that a given admin route exists in the application's admin route file.
     *
     * If the route file does not exist, it will create it, including the full group and resource.
     * If the route already exists, it will clear the cached routes.
     * Attempts to insert the new route either into an existing `$router->resources([...]);` block,
     * into the `Route::group([...], function (Router $router)` group, or as a fallback
     * appends a full admin group with the resource.
     *
     * @param Scaffold $scaffold The scaffold instance providing route and controller data.
     * @param string $route The route slug to ensure within the admin route file.
     *
     * @return void
     */
    protected function ensureAdminRoute(Scaffold $scaffold, $route): void
    {
        $routeFile = base_path('app/Admin/routes.php');
        if (!File::exists($routeFile)) {
            // If file is missing, create it with a full group and the resource.
            $this->writeNewAdminRoutesFile($scaffold, $route);
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

    /**
     * Writes a new admin routes file for the given scaffold model.
     * It generates the route group configuration and resource route for the specified scaffold.
     *
     * @param mixed $scaffold The scaffold instance containing controller and table metadata.
     * @param string $slug The slug used to define the resource route.
     * @return void
     */
    protected function writeNewAdminRoutesFile($scaffold, $slug): void
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

    /**
     * Appends an admin route group with a resource controller to the specified routes file.
     * This method ensures the correct grouping and middleware for admin-specific route resources.
     *
     * @param mixed $scaffold The scaffold instance containing metadata such as the controller name.
     * @param string $routeFile The path to the routes file where the generated block will be appended.
     * @return void
     */
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

    /**
     * Normalizes a Fully Qualified Class Name (FQCN).
     * Processes the given FQCN string to ensure it is structured correctly and resolves shorthand formats.
     *
     * @param string|null $fqcn The FQCN to normalize, or null if not provided.
     * @return string The normalized FQCN.
     */
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

    /**
     * Normalizes the end-of-line characters in the provided code to use Unix-style line endings.
     * Converts Windows or mixed line endings to LF and adds a single trailing newline.
     *
     * @param string $code The code string to normalize.
     * @return string The normalized code string with consistent Unix-style line endings.
     */
    protected function normalizeEol(string $code): string
    {
        return rtrim(preg_replace("/\r\n?/", "\n", $code)) . "\n";
    }

    /**
     * Ensures the `ResponseMapper` trait exists within the application. If the trait does
     * not already exist, this method creates it and includes functionality for handling
     * JSON responses, pagination data, and standardized error messages.
     *
     * The method performs the following actions:
     * 1. Checks if `Traits/ResponseMapper.php` exists in the application directory.
     * 2. Creates the `Traits` directory if it does not exist.
     * 3. Writes the `ResponseMapper` trait to `Traits/ResponseMapper.php` with necessary
     *    methods for JSON response handling.
     *
     * @return void
     */
    protected function ensureResponseMapperTrait(): void
    {
        $path = app_path('Traits/ResponseMapper.php');
        if (File::exists($path)) {
            return; // already there
        }

        // make sure app/Traits exists
        $dir = dirname($path);
        if (!File::isDirectory($dir)) {
            File::makeDirectory($dir, 0755, true);
        }

        // write the trait (with ceil fix)
        File::put($path, <<<'PHP'
<?php
/**
 * @author Talemul Islam <talemulislam@gmail.com>
 * @link   https://talemul.com
 */
namespace App\Traits;

use Illuminate\Http\JsonResponse;

/**
 * Trait ResponseMapper
 *
 * Provides functionalities to handle API response formatting, including setting pagination data,
 * creating custom JSON responses, and managing error handling.
 */
trait ResponseMapper
{
    /**
     * @var mixed
     */
    protected mixed $error = null;
    protected $payload = null;
    protected $message = null;
    protected $responseCode = null;

    /**
     * Prepares pagination details from the given data.
     *
     * @param mixed $data The data object supporting pagination methods.
     *
     * @return array An array containing pagination information:
     *               - 'page': Current page number.
     *               - 'pageSize': Number of records per page.
     *               - 'totalPage': Total number of pages.
     *               - 'totalRecords': Total number of records.
     */
    public function setPagination($data): array
    {
        return [
            'page' => $data->currentPage(),
            'pageSize' => $data->perPage(),
            'totalPage' => (int)ceil($data->total() / max(1, $data->perPage())),
            'totalRecords' => $data->total(),
        ];
    }

    /**
     * Sets the response data with the provided details.
     *
     * @param string|null $message The message to set for the response.
     * @param mixed|null $error Error information, or null if no errors.
     * @param int|null $responseCode The HTTP status code to set for the response.
     * @param array $data The data to set in the response payload.
     *
     * @return void
     */
    public function setResponseData($message = null, $error = null, $responseCode = null, $data = []): void
    {
        $this->message = $message;
        $this->error = $error;
        $this->responseCode = $responseCode;
        $this->payload = $data;
    }

    /**
     * Sends a JSON response using class properties for message, payload, error, and response code.
     *
     * @return JsonResponse The generated JSON response containing success status, message, data, and error details.
     */
    public function sendJsonResponse(): JsonResponse
    {
        $code = $this->responseCode ?: 200;

        return response()->json([
            'success' => in_array($code, [200, 201, 204], true),
            'message' => $this->message,
            'data' => $this->payload,
            'error' => $this->error,
        ], $code);
    }

    /**
     * Constructs a JSON response with the provided details.
     *
     * @param string|null $message The message to include in the response.
     * @param mixed|null $error Error details or null if no error.
     * @param int|null $responseCode The HTTP status code for the response. Defaults to 200.
     * @param array $data The data to include in the response payload.
     *
     * @return JsonResponse The constructed JSON response.
     */
    public function jsonResponse($message = null, $error = null, $responseCode = null, $data = []): JsonResponse
    {
        $code = $responseCode ?: 200;

        return response()->json([
            'success' => in_array($code, [200, 201, 204], true),
            'message' => $message,
            'data' => $data,
            'error' => $error,
        ], $code);
    }

    /**
     * Returns a JSON response for a "Not Found" error.
     *
     * @return \Illuminate\Http\JsonResponse The JSON response with a 404 status code and error details.
     */
    protected function notFound(): \Illuminate\Http\JsonResponse
    {
        return $this->jsonResponse('Not found', ['resource' => 'Not found'], 404, []);
    }

}

PHP
        );
    }

    /**
     * Ensures the API route for the specified scaffold model exists.
     * Dynamically generates a route for API resource controllers if it does not already exist.
     *
     * @param \SuperAdmin\Admin\Helpers\Model\Scaffold $scaffold The scaffold instance containing model metadata.
     * @return void
     */
    protected function ensureApiRoute(\SuperAdmin\Admin\Helpers\Model\Scaffold $scaffold): void
    {
        $routeFile = base_path('routes/api.php');
        $content = File::exists($routeFile) ? File::get($routeFile) : "<?php\n\nuse Illuminate\\Support\\Facades\\Route;\n\n";

        $modelFqcn = ltrim((string)$scaffold->model_name, '\\');
        $modelShort = class_basename($modelFqcn);
        $apiFqcn = "App\\Http\\Controllers\\Api\\{$modelShort}ApiController";

        // slug -> kebab plural of model short name: StudentInfo => student-infos
        $slug = Str::kebab(Str::pluralStudly($modelShort));

        // Check duplicates
        $needle = "Route::apiResource('{$slug}', {$apiFqcn}::class)";
        if (Str::contains($content, $needle)) {
            return;
        }

        // Append route
        $line = "Route::apiResource('{$slug}', {$apiFqcn}::class);\n";
        if (!Str::contains($content, 'use Illuminate\\Support\\Facades\\Route;')) {
            $content = "<?php\n\nuse Illuminate\\Support\\Facades\\Route;\n\n" . ltrim($content, "<?php");
        }
        $content .= $line;

        File::put($routeFile, rtrim(preg_replace("/\r\n?/", "\n", $content)) . "\n");
    }

    /**
     * Ensures the web route for the specified scaffold model exists.
     * It dynamically generates a route for resource controllers if it does not already exist.
     *
     * @param \SuperAdmin\Admin\Helpers\Model\Scaffold $scaffold The scaffold instance containing model metadata.
     * @return void
     */
    protected function ensureWebRoute(\SuperAdmin\Admin\Helpers\Model\Scaffold $scaffold): void
    {
        $routeFile = base_path('routes/web.php');
        $content = File::exists($routeFile) ? File::get($routeFile) : "<?php\n\nuse Illuminate\\Support\\Facades\\Route;\n\n";

        $modelFqcn = ltrim((string)$scaffold->model_name, '\\');
        $modelShort = class_basename($modelFqcn);
        $webFqcn = "App\\Http\\Controllers\\{$modelShort}WebController";

        $slug = Str::kebab(Str::pluralStudly($modelShort)); // student-infos

        $needle = "Route::resource('{$slug}', {$webFqcn}::class)";
        if (Str::contains($content, $needle)) return;

        if (!Str::contains($content, 'use Illuminate\\Support\\Facades\\Route;')) {
            $content = "<?php\n\nuse Illuminate\\Support\\Facades\\Route;\n\n" . ltrim($content, "<?php");
        }
        $content .= $needle . ";\n";
        File::put($routeFile, rtrim(preg_replace("/\r\n?/", "\n", $content)) . "\n");
    }
}
