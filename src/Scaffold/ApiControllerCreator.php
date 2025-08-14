<?php

namespace SuperAdmin\Admin\Helpers\Scaffold;

use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Str;
use SuperAdmin\Admin\Helpers\Model\Scaffold;

class ApiControllerCreator
{
    protected Filesystem $files;

    public function __construct(?Filesystem $files = null)
    {
        $this->files = $files ?: app('files');
    }

    /**
     * Generate API Controller for given scaffold id or instance.
     *
     * @param  int|string|Scaffold  $scaffoldOrId
     * @return string  Absolute path to generated file
     */
    public function create($scaffoldOrId): string
    {
        $scaffold = $scaffoldOrId instanceof Scaffold
            ? $scaffoldOrId
            : Scaffold::with('details')->findOrFail((int) $scaffoldOrId);

        $stub =$stub = $this->files->get(__DIR__.'/stubs/api_controller.stub');
        //$this->files->get($this->getStub());

        $modelFqcn  = $this->normalizeFqcn((string) $scaffold->model_name);
        $modelShort = class_basename($modelFqcn);

        // API controller fqcn (App\Http\Controllers\Api\{Model}ApiController)
        $apiNamespace = 'App\\Http\\Controllers\\Api';
        $apiClass     = $modelShort . 'ApiController';

        // Build dynamic blocks
        $rulesStore = $this->buildRules($scaffold, false);
        $rulesUpdate= $this->buildRules($scaffold, true);
        $fieldMeta  = $this->buildFieldMeta($scaffold);

        // Replace placeholders
        $code = str_replace(
            ['DummyApiNamespace','DummyApiClass','DummyModelNamespace','DummyModel','DummyRulesStore','DummyRulesUpdate','DummyFieldMeta'],
            [$apiNamespace,      $apiClass,      $modelFqcn,          $modelShort, $rulesStore,     $rulesUpdate,     $fieldMeta],
            $stub
        );

        // Write file
        $path = app_path('Http/Controllers/Api/' . $apiClass . '.php');
        $this->ensureDir(dirname($path));
        $this->files->put($path, $this->normalizeEol($code));

        return $path;
    }

    /* -------------------- builders -------------------- */

    /**
     * Build validation rules array literal for store/update.
     * - Static options => in:...
     * - Model options => exists:{table},{value_col}
     * - Checkbox(model) => array + each.* exists
     */
    protected function buildRules(Scaffold $scaffold, bool $forUpdate): string
    {
        $lines = [];
        $primary = $scaffold->primary_key ?: 'id';

        foreach ($scaffold->details as $d) {
            $field = (string)($d->name ?? '');
            if ($field === '' || $field === $primary) continue;

            $nullable = (bool)$d->nullable;
            $input    = strtolower((string)($d->input_type ?? 'text'));
            $source   = (string)($d->options_source ?? '');
            $valCol   = (string)($d->options_value_col ?? '');
            $labCol   = (string)($d->options_label_col ?? '');

            // base required/nullable
            $rules = [];
            $rules[] = $nullable ? 'nullable' : 'required';

            // basic type hints
            $type = strtolower((string)$d->type);
            $rules[] = match (true) {
                in_array($type, ['tinyinteger','smallinteger','integer','biginteger','bigint','int'], true) => 'integer',
                $type === 'date'    => 'date',
                $type === 'email'   => 'email',
                default             => 'string',
            };

            // Option-driven rules
            if (in_array($input, ['select','radio'], true)) {
                if ($source === 'static') {
                    $values = implode(',', array_map('trim', explode(',', $valCol)));
                    if ($values !== '') $rules[] = 'in:' . $values;
                } elseif ($source) {
                    // dynamic exists using model table at runtime
                    $fqcn = $this->normalizeFqcn($source);
                    $vCol = $valCol ?: 'id';
                    $lines[] = "            '{$field}' => array_filter([" .
                        implode(', ', array_map(fn($r) => var_export($r, true), $rules)) .
                        ", 'exists:' . (new \\{$fqcn})->getTable() . ',{$vCol}']),";
                    continue;
                }
            } elseif ($input === 'checkbox' && $source && $source !== 'static') {
                // array + each item exists
                $fqcn  = $this->normalizeFqcn($source);
                $vCol  = $valCol ?: 'id';
                $relation = lcfirst(Str::pluralStudly(class_basename($fqcn)));
                $lines[] = "            '{$relation}' => ['sometimes','array'],";
                $lines[] = "            '{$relation}.*' => ['integer', function(\$attr,\$value,\$fail){ if (!\\{$fqcn}::where('{$vCol}',\$value)->exists()) { \$fail('Invalid id: ' . \$value); } }],";
                // also accept field name as array key
                $lines[] = "            '{$field}' => ['sometimes','array'],";
                $lines[] = "            '{$field}.*' => ['integer', function(\$attr,\$value,\$fail){ if (!\\{$fqcn}::where('{$vCol}',\$value)->exists()) { \$fail('Invalid id: ' . \$value); } }],";
                continue;
            }

            $lines[] = "            '{$field}' => [" . implode(', ', array_map(fn($r) => var_export($r, true), $rules)) . "],";
        }

        if (empty($lines)) {
            return "        return [];\n";
        }

        return "        return [\n" . implode("\n", $lines) . "\n        ];\n";
    }

    /**
     * Build fieldMeta() array for the stub.
     */
    protected function buildFieldMeta(Scaffold $scaffold): string
    {
        $rows = [];

        foreach ($scaffold->details as $d) {
            $field = (string)($d->name ?? '');
            $type  = strtolower((string)($d->input_type ?? 'text'));
            $src   = (string)($d->options_source ?? '');
            $val   = (string)($d->options_value_col ?? '');
            $lab   = (string)($d->options_label_col ?? '');

            if (!in_array($type, ['select','radio','checkbox'], true)) continue;
            if (!$src) continue;

            $row = "            '{$field}' => [ 'type' => '{$type}', ";

            if ($src === 'static') {
                $values = array_values(array_filter(array_map('trim', explode(',', $val)), 'strlen'));
                $labels = array_values(array_filter(array_map('trim', explode(',', $lab)), 'strlen'));
                $row .= "'source' => 'static', 'values' => " . var_export($values, true) .
                    ", 'labels' => " . var_export($labels, true);
            } else {
                $fqcn = $this->normalizeFqcn($src);
                $vCol = $val ?: 'id';
                $lCol = $lab ?: 'name';
                $relation = lcfirst(($type === 'checkbox')
                    ? Str::pluralStudly(class_basename($fqcn))
                    : class_basename($fqcn));
                $row .= "'source' => 'model', 'fqcn' => \\{$fqcn}::class, 'value' => '{$vCol}', 'label' => '{$lCol}', 'relation' => '{$relation}'";
            }

            $row .= " ],";
            $rows[] = $row;
        }

        if (empty($rows)) {
            return "            // no option-driven fields\n";
        }

        return implode("\n", $rows) . "\n";
    }

    /* -------------------- utils -------------------- */

    protected function getStub(): string
    {
        $project = base_path('stubs/api_controller.stub');
        if ($this->files->exists($project)) return $project;
        return __DIR__ . '/api_controller.stub';
    }

    protected function ensureDir(string $dir): void
    {
        if (!$this->files->isDirectory($dir)) {
            $this->files->makeDirectory($dir, 0755, true);
        }
    }

    protected function normalizeEol(string $code): string
    {
        return rtrim(preg_replace("/\r\n?/", "\n", $code)) . "\n";
    }

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
}
