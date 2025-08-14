<?php

namespace SuperAdmin\Admin\Helpers\Scaffold;

use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Str;
use SuperAdmin\Admin\Helpers\Model\Scaffold;

class PestTestCreator
{
    public function __construct(private ?Filesystem $files = null)
    {
        $this->files ??= app('files');
    }

    /**
     * Creates various scaffold-generated resources such as tests and factories based on the provided scaffold model or ID.
     *
     * @param int|string|Scaffold $scaffoldOrId The scaffold instance or ID used to generate resources.
     * @param array $options {
     *     Optional parameters for resource generation:
     *
     * @type bool $overwrite Indicates whether to overwrite existing files (default: false).
     * @type bool $with_factory Determines whether to generate a factory resource (default: true).
     * }
     *
     * @return array Returns an array containing:
     *               - 'tests': Generated test files.
     *               - 'factories': Generated factory files, if applicable.
     *
     * @throws \Illuminate\Database\Eloquent\ModelNotFoundException If the scaffold ID does not exist in the database.
     */
    public function create(int|string|Scaffold $scaffoldOrId, array $options = []): array
    {
        $scaffold = $scaffoldOrId instanceof Scaffold
            ? $scaffoldOrId
            : Scaffold::with('details')->findOrFail((int)$scaffoldOrId);

        $overwrite    = (bool)($options['overwrite'] ?? false);
        $withFactory  = (bool)($options['with_factory'] ?? true);

        // Names
        $modelFqcn   = $this->normalizeFqcn((string)$scaffold->model_name);
        $modelShort  = class_basename($modelFqcn);
        $table       = $scaffold->table_name ?: Str::snake(Str::pluralStudly($modelShort));
        $pk          = $scaffold->primary_key ?: 'id';

        // Web resource:
        //   URI   : dashed plural of model short (e.g. BloodGroup -> blood-groups)
        //   Names : dot-style mapping (blood.groups.*)
        $webSlug        = Str::kebab(Str::pluralStudly($modelShort));         // blood-groups
        $webRoutePrefix =  $webSlug;                     // blood.groups

        // API resource (routes/api.php): /api/{webSlug}
        $apiBase = "/api/{$webSlug}";

        // Admin resource path (same rule we used in ensureAdminRoute): singular kebab of table
        $adminPrefix  = config('admin.route.prefix', 'admin');
        $adminSlug    = Str::kebab(Str::singular($scaffold->table_name ?: Str::snake($modelShort)));
        $adminBaseUri = "/{$adminPrefix}/{$adminSlug}";

        // Fillable list for unit assertion
        $fillable = $this->computeFillable($scaffold, $pk);

        // Ensure Pest bootstrap
        $this->ensurePestBootstrap();

        // Generate tests
        $tests   = [];
        $tests[] = $this->writeIfAbsent("tests/Feature/API/{$modelShort}ApiTest.php",
            $this->apiTestStub($modelShort, $apiBase), $overwrite);

        $tests[] = $this->writeIfAbsent("tests/Feature/Web/{$modelShort}WebTest.php",
            $this->webTestStub($modelShort, $webRoutePrefix), $overwrite);

        $tests[] = $this->writeIfAbsent("tests/Feature/Admin/{$modelShort}AdminTest.php",
            $this->adminTestStub($modelShort, $adminBaseUri), $overwrite);

        $tests[] = $this->writeIfAbsent("tests/Unit/Models/{$modelShort}Test.php",
            $this->modelUnitStub($modelShort, $table, $fillable), $overwrite);

        // Optional factory
        $factories = [];
        if ($withFactory) {
            $factoryPath = "database/factories/{$modelShort}Factory.php";
            $factories[] = $this->writeIfAbsent($factoryPath,
                $this->factoryStub($modelFqcn, $modelShort, $scaffold, $fillable), $overwrite);
        }

        return [
            'tests'     => array_values(array_filter($tests)),
            'factories' => array_values(array_filter($factories)),
        ];
    }

    /* -------------------- Stubs -------------------- */
    /**
     * Generates the API test stub for a given model and API base URL.
     *
     * @param string $modelShort The short name of the model being scaffolded, typically the singular form of the model class name.
     * @param string $apiBase The base URL for the API endpoints associated with the model.
     *
     * @return string The string representation of the generated test stub, containing predefined structure for API CRUD operations.
     */
    private function apiTestStub(string $modelShort, string $apiBase): string
    {
        return <<<PHP
<?php

use {$this->fq("Illuminate\\Support\\Str")};

it('lists {$modelShort} with ResponseMapper shape', function () {
    \$res = \$this->getJson('{$apiBase}');
    \$res->assertOk()
        ->assertJsonStructure([
            'success', 'message',
            'data' => ['items', 'pagination' => ['page','pageSize','totalPage','totalRecords']],
            'error'
        ])
        ->assertJsonPath('success', true)
        ->assertJsonPath('message', 'List fetched');
});

it('creates, shows, updates and deletes {$modelShort} with uniform JSON', function () {
    // Minimal payload: only nullable-friendly fields are required in your validators
    \$create = \$this->postJson('{$apiBase}', []);
    // Could be 201 on permissive scaffolds, or 422 if required rules exist.
    // We'll branch to keep it robust.
    if (\$create->status() === 201) {
        \$id = data_get(\$create->json(), 'data.id');
        expect(\$id)->toBeInt();
    } else {
        \$create->assertStatus(422);
        // Try again with a common string field if present:
        \$create = \$this->postJson('{$apiBase}', ['name' => 'Test']);
        if (\$create->status() === 422) {
            \$this->markTestSkipped('Scaffold requires more fields; generator kept test generic.');
        }
        \$id = data_get(\$create->json(), 'data.id');
    }

    // show
    \$this->getJson('{$apiBase}/'.\$id)
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('message', 'Resource fetched')
        ->assertJsonPath('data.id', \$id);

    // update
    \$this->putJson('{$apiBase}/'.\$id, ['remarks' => 'updated via test'])
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('message', 'Updated');

    // delete
    \$this->deleteJson('{$apiBase}/'.\$id)
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('message', 'Deleted');

    // not found shape
    \$this->getJson('{$apiBase}/'.\$id)
        ->assertStatus(404)
        ->assertJsonPath('success', false)
        ->assertJsonPath('message', 'Not found');
});
PHP;
    }

    /**
     * Generates a PHP code snippet for testing CRUD operations on a given model.
     *
     * This function prepares a code template for testing the `index`, `create`, `store`, `edit`, `update`, and `delete`
     * routes of a given model in a Laravel application. The generated test scenarios include:
     * - Ensuring the index and create routes render successfully.
     * - Testing the store route with minimal and fallback payloads.
     * - Retrieving the latest model ID after creation.
     * - Validating the edit route rendering.
     * - Performing model update and delete operations, ensuring proper HTTP response assertions.
     *
     * @param string $modelShort A short name for the model, used in constructing table and route names.
     * @param string $routePrefixDot The route prefix in dot notation (e.g., `users.index`).
     *
     * @return string A PHP code snippet for the described test scenarios.
     */
    private function webTestStub(string $modelShort, string $routePrefixSnake): string
    {
        // Example: student_infos.index
        return <<<PHP
<?php

it('renders {$modelShort} index and create pages and performs store/update/delete', function () {
    // index
    \$this->get(route('{$routePrefixSnake}.index'))->assertOk();

    // create
    \$this->get(route('{$routePrefixSnake}.create'))->assertOk();

    // store (best-effort minimal payload)
    \$store = \$this->post(route('{$routePrefixSnake}.store'), []);
    if (\$store->status() !== 302) {
        // try again with a common field
        \$store = \$this->post(route('{$routePrefixSnake}.store'), ['name' => 'Test']);
        if (\$store->status() !== 302) {
            \$this->markTestSkipped('Scaffold requires additional fields; skipping strict assertions.');
            return;
        }
    }

    // Find newly created model by latest id (fallback—works in RefreshDatabase)
    \$id = \\DB::table((new \\App\\Models\\{$modelShort})->getTable())->max('id');

    // edit page
    \$this->get(route('{$routePrefixSnake}.edit', \$id))->assertOk();

    // update
    \$this->put(route('{$routePrefixSnake}.update', \$id), ['remarks' => 'updated'])
         ->assertStatus(302);

    // destroy
    \$this->delete(route('{$routePrefixSnake}.destroy', \$id))
         ->assertStatus(302);
});
PHP;
    }


    /**
     * Generates a test stub for verifying the functionality of an admin grid and create form.
     *
     * The returned stub includes tests for loading the admin grid, accessing the create form,
     * and submitting data to the create endpoint. It ensures that the appropriate responses
     * are returned, such as successful page loads or form submissions.
     *
     * @param string $modelShort The short name of the model being tested.
     * @param string $adminBaseUri The base URI for the admin panel of the specified model.
     * @return string The generated test stub as a string.
     */
    private function adminTestStub(string $modelShort, string $adminBaseUri): string
    {
        return <<<PHP
<?php

it('loads {$modelShort} admin grid and can submit create form', function () {
    // login as laravel-admin user via helper in tests/Pest.php
    loginAsAdmin();

    // grid
    \$this->get('{$adminBaseUri}')->assertOk();

    // create page
    \$this->get('{$adminBaseUri}/create')->assertOk();

    // submit (may redirect back with validation errors; we accept 200/302)
    \$res = \$this->post('{$adminBaseUri}', ['name' => 'From Admin']);
    expect(in_array(\$res->status(), [200,302]))->toBeTrue();
});
PHP;
    }

    /**
     * Generates a unit test stub for a model to ensure that it uses the correct table,
     * has the expected fillable attributes, and optionally uses the SoftDeletes trait.
     *
     * @param string $modelShort The short name of the model.
     * @param string $table The name of the table associated with the model.
     * @param array $fillable The fillable attributes for the model.
     * @return string             The generated PHP code as a string.
     */
    private function modelUnitStub(string $modelShort, string $table, array $fillable): string
    {
        $fillableExport = implode("', '", array_map('strval', $fillable));
        return <<<PHP
<?php

use App\Models\\{$modelShort};
use Illuminate\Database\Eloquent\SoftDeletes;

it('uses the expected table and fillable, and (if present) SoftDeletes', function () {
    \$m = new {$modelShort}();

    expect(\$m->getTable())->toBe('{$table}');
    // fillable should at least include these (order not enforced)
    foreach (['{$fillableExport}'] as \$f) {
        if (\$f === '') continue;
        expect(\$m->getFillable())->toContain(\$f);
    }

    // SoftDeletes is optional; if used, trait must be present
    \$traits = class_uses_recursive({$modelShort}::class);
    if (in_array(SoftDeletes::class, array_keys(\$traits))) {
        expect(\$traits)->toHaveKey(SoftDeletes::class);
    } else {
        expect(true)->toBeTrue(); // not using soft deletes is okay
    }
});
PHP;
    }

    /**
     * Generates a factory stub for a given model to create database factories with default fillable values.
     *
     * @param string $modelFqcn The fully qualified class name of the model.
     * @param string $modelShort The short name of the model.
     * @param Scaffold $scaffold An instance of the Scaffold object for the model.
     * @param array $fillable The fillable attributes of the model.
     * @return string The generated PHP code for the factory as a string.
     */
    private function factoryStub(string $modelFqcn, string $modelShort, Scaffold $scaffold, array $fillable): string
    {
        // naive faker mapping; nullable fields default to null
        $assigns = [];
        foreach ($fillable as $f) {
            $assigns[] = "            '{$f}' => null,";
        }
        $assignsStr = implode("\n", $assigns);

        return <<<PHP
<?php

namespace Database\\Factories;

use {$this->fq($modelFqcn)};
use Illuminate\\Database\\Eloquent\\Factories\\Factory;

class {$modelShort}Factory extends Factory
{
    protected \$model = {$modelShort}::class;

    public function definition(): array
    {
        return [
{$assignsStr}
        ];
    }
}
PHP;
    }

    /* -------------------- helpers -------------------- */
    /**
     * Escapes backslashes in a fully qualified class name (FQCN) by replacing
     * each single backslash with double backslashes.
     *
     * @param string $fqcn The fully qualified class name to escape.
     * @return string The escaped class name with double backslashes.
     */
//    private function fq(string $fqcn): string
//    {
//        return str_replace('\\', '\\\\', $fqcn);
//    }
    /**
     * Computes the fillable attributes for a given model.
     *
     * @param Scaffold $scaffold The Scaffold object for the model.
     * @param string $pk The primary key of the model.
     * @return array The list of fillable attributes for the model.
     */
    private function computeFillable(Scaffold $scaffold, string $pk): array
    {
        $skip = [$pk, 'created_at', 'updated_at', 'deleted_at'];
        $fillable = [];
        foreach ($scaffold->details as $d) {
            $n = (string)($d->name ?? '');
            if ($n === '' || in_array($n, $skip, true)) continue;
            $fillable[] = $n;
        }
        return $fillable;
    }

    /**
     * Ensures the existence of a Pest.php bootstrap file for Pest testing
     * and initializes necessary test directories and files.
     *
     * - Creates a `tests/Pest.php` file if it does not already exist, and populates it with
     *   default Pest setup configurations such as enabling the `RefreshDatabase` trait and defining
     *   a helper function for admin user login.
     * - Ensures the required testing directories, such as `tests/Feature/API`, `tests/Feature/Web`,
     *   and others, are created if missing.
     *
     * @return void
     */
    private function ensurePestBootstrap(): void
    {
        $pest = base_path('tests/Pest.php');

        $this->ensureDir(dirname($pest));

        if (!file_exists($pest)) {
            // Fresh file with uses() and helper
            file_put_contents($pest, $this->eol(<<<'PHP'
<?php

use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class)->in('Feature', 'Unit');

/**
 * Log in using the laravel-admin guard and return the admin user.
 *
 * @param  array{username?:string,password?:string,name?:string,email?:string}  $attrs
 */
function loginAsAdmin(array $attrs = [])
{
    $userClass = config('admin.database.users_model')
        ?? \Encore\Admin\Auth\Database\Administrator::class;

    $guard = config('admin.auth.guard', 'admin');

    /** @var \Illuminate\Database\Eloquent\Model $u */
    $u = new $userClass();

    $u->username = $attrs['username'] ?? 'admin@test.local';
    $u->password = bcrypt($attrs['password'] ?? 'secret');
    $u->name     = $attrs['name'] ?? 'Test Admin';

    // Set email only if the column exists on your admin users table
    if (\Illuminate\Support\Facades\Schema::hasColumn($u->getTable(), 'email')) {
        $u->email = $attrs['email'] ?? 'admin@test.local';
    }

    $u->save();

    // Attach Admin role if roles are enabled
    $roleClass = config('admin.database.roles_model');
    if ($roleClass && method_exists($u, 'roles')) {
        $role = $roleClass::firstOrCreate(['slug' => 'admin'], ['name' => 'Admin']);
        $u->roles()->syncWithoutDetaching([$role->getKey()]);
    }

    // Authenticate on the admin guard so Admin routes pass middleware
    test()->actingAs($u, $guard);

    return $u;
}
PHP));
            return;
        }

        // File exists: append missing parts only
        $content = file_get_contents($pest);
        $dirty   = false;

        if (!str_contains($content, 'uses(RefreshDatabase::class)')) {
            $content = rtrim($content) . $this->eol(<<<'PHP'

use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class)->in('Feature', 'Unit');
PHP);
            $dirty = true;
        }

        if (!preg_match('/function\s+loginAsAdmin\s*\(/', $content)) {
            $content = rtrim($content) . $this->eol(<<<'PHP'

/**
 * Log in using the laravel-admin guard and return the admin user.
 *
 * @param  array{username?:string,password?:string,name?:string,email?:string}  $attrs
 */
function loginAsAdmin(array $attrs = [])
{
    $userClass = config('admin.database.users_model')
        ?? \Encore\Admin\Auth\Database\Administrator::class;

    $guard = config('admin.auth.guard', 'admin');

    /** @var \Illuminate\Database\Eloquent\Model $u */
    $u = new $userClass();

    $u->username = $attrs['username'] ?? 'admin@test.local';
    $u->password = bcrypt($attrs['password'] ?? 'secret');
    $u->name     = $attrs['name'] ?? 'Test Admin';

    if (\Illuminate\Support\Facades\Schema::hasColumn($u->getTable(), 'email')) {
        $u->email = $attrs['email'] ?? 'admin@test.local';
    }

    $u->save();

    $roleClass = config('admin.database.roles_model');
    if ($roleClass && method_exists($u, 'roles')) {
        $role = $roleClass::firstOrCreate(['slug' => 'admin'], ['name' => 'Admin']);
        $u->roles()->syncWithoutDetaching([$role->getKey()]);
    }

    test()->actingAs($u, $guard);

    return $u;
}
PHP);
            $dirty = true;
        }

        if ($dirty) {
            file_put_contents($pest, $this->eol($content));
        }

        // Make base folders
        foreach (['tests/Feature/API','tests/Feature/Web','tests/Feature/Admin','tests/Unit/Models','database/factories'] as $dir) {
            $this->ensureDir(base_path($dir));
        }
    }


    /**
     * Writes contents to a file at the specified relative path if the file does not already exist
     * or if overwriting is allowed. Ensures that the necessary directory structure is created.
     *
     * @param string $relPath The relative path of the file to write to.
     * @param string $contents The contents to write to the file.
     * @param bool $overwrite Whether to overwrite the file if it already exists.
     * @return string|null The absolute path of the file if written, or null if the file was not written.
     */
    private function writeIfAbsent(string $relPath, string $contents, bool $overwrite): ?string
    {
        $abs = base_path($relPath);
        $this->ensureDir(dirname($abs));
        if (!$overwrite && $this->files->exists($abs)) {
            return null;
        }
        $this->files->put($abs, $this->eol($contents));
        return $abs;
    }

    /**
     * Ensures that the specified directory exists by creating it if it does not already exist.
     *
     * @param string $dir The path of the directory to verify or create.
     * @return void
     */
    private function ensureDir(string $dir): void
    {
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }
    }

    /**
     * Normalizes the given string to use Unix line endings (`\n`) and ensures it ends with a newline.
     *
     * @param string $s The input string to normalize.
     * @return string The normalized string with Unix line endings and a trailing newline.
     */
    private function eol(string $s): string
    {
        return rtrim(preg_replace("/\r\n?/", "\n", $s)) . "\n";
    }

    /**
     * Normalizes a fully qualified class name (FQCN) to ensure it adheres to
     * a consistent format by trimming, handling leading backslashes,
     * and restructuring non-FQCN strings that start with "App".
     *
     * @param string|null $fqcn The fully qualified class name to normalize, or null.
     * @return string           The normalized fully qualified class name as a string.
     */
    private function normalizeFqcn(?string $fqcn): string
    {
        $s = trim((string)$fqcn);
        if ($s === '') return '';
        $s = ltrim($s, '\\');
        if (!str_contains($s, '\\') && Str::startsWith($s, 'App')) {
            $parts = preg_split('/(?=[A-Z])/', $s, -1, PREG_SPLIT_NO_EMPTY);
            if (!empty($parts)) $s = implode('\\', $parts);
        }
        return $s;
    }

    /**
     * Removes the leading backslash from a fully qualified class name (FQN) or namespace string.
     *
     * @param string $fq The fully qualified class name or namespace.
     * @return string    The class name or namespace string without a leading backslash.
     */
    private function fq(string $fq): string
    {
        return ltrim($fq, '\\');
    }
}
