<?php

namespace SuperAdmin\Admin\Helpers\Scaffold;

use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Str;
use SuperAdmin\Admin\Helpers\Model\Scaffold;

class BladeCrudCreator
{
    public function __construct(private ?Filesystem $files = null)
    {
        $this->files ??= app('files');
    }

    /** @return array{controller:string,views:string[]} */
    public function create(int|string|Scaffold $scaffoldOrId): array
    {
        $scaffold = $scaffoldOrId instanceof Scaffold
            ? $scaffoldOrId
            : Scaffold::with('details')->findOrFail((int)$scaffoldOrId);

        // Names
        $modelFqcn  = $this->normalizeFqcn((string)$scaffold->model_name);
        $modelShort = class_basename($modelFqcn);
        $webNs      = 'App\\Http\\Controllers';
        $webClass   = $modelShort.'WebController';

        $slug       = Str::kebab(Str::pluralStudly($modelShort));           // student-infos
        $viewDir    = str_replace('-', '_', $slug);                          // student_infos
        $title      = Str::title(str_replace(['_','-'], ' ', $viewDir));
        $routeName  = $slug;//Str::slug($slug, '.');
        //dd($slug);
        // student-infos -> student-infos (we'll replace '.' with '-') later
        //$routeName  = str_replace('-', '.', $slug);                           // route names like student.infos

        /* ---------- Controller ---------- */
        $stub = $this->files->get($this->stub('web_controller.stub'));
        $code = str_replace(
            ['DummyWebNamespace','DummyWebClass','DummyModelNamespace','DummyModel','DummySlug','DummyTitle','DummyRouteName','DummyRulesStore','DummyRulesUpdate','DummyOptionsBag','DummyManyToManyKeys','DummySyncManyToMany'],
            [$webNs,             $webClass,       $modelFqcn,            $modelShort, $viewDir,  $title,       $routeName,     $this->rules($scaffold,false), $this->rules($scaffold,true), $this->optionsBag($scaffold), $this->manyToManyKeys($scaffold), $this->syncManyToMany($scaffold)],
            $stub
        );

        $controllerPath = app_path('Http/Controllers/'.$webClass.'.php');
        $this->ensureDir(dirname($controllerPath));
        $this->files->put($controllerPath, $this->eol($code));

        /* ---------- Views ---------- */
        $views = [];
        $this->ensureDir(resource_path("views/{$viewDir}"));

        // layout
        $layout = str_replace(['DummyTitle'], [$title], $this->files->get($this->stub('blade/_layout.blade.stub')));
        $this->files->put(resource_path("views/{$viewDir}/_layout.blade.php"), $this->eol($layout));
        $views[] = "resources/views/{$viewDir}/_layout.blade.php";

        // index
        $index = $this->files->get($this->stub('blade/index.blade.stub'));
        $index = str_replace(
            ['DummySlug','DummyRouteName','DummyIndexHead','DummyIndexCols'],
            [$viewDir,   $routeName,      $this->indexHead($scaffold), $this->indexCols($scaffold)],
            $index
        );
        $this->files->put(resource_path("views/{$viewDir}/index.blade.php"), $this->eol($index));
        $views[] = "resources/views/{$viewDir}/index.blade.php";

        // form partial
        $form = $this->files->get($this->stub('blade/_form.blade.stub'));
        $form = str_replace(
            ['DummyRouteName','DummyFormFields'],
            [$routeName,      $this->formFields($scaffold)],
            $form
        );
        $this->files->put(resource_path("views/{$viewDir}/_form.blade.php"), $this->eol($form));
        $views[] = "resources/views/{$viewDir}/_form.blade.php";

        // create
        $create = str_replace(
            ['DummySlug','DummyRouteName'],
            [$viewDir,   $routeName],
            $this->files->get($this->stub('blade/create.blade.stub'))
        );
        $this->files->put(resource_path("views/{$viewDir}/create.blade.php"), $this->eol($create));
        $views[] = "resources/views/{$viewDir}/create.blade.php";

        // edit
        $edit = str_replace(
            ['DummySlug','DummyRouteName'],
            [$viewDir,   $routeName],
            $this->files->get($this->stub('blade/edit.blade.stub'))
        );
        $this->files->put(resource_path("views/{$viewDir}/edit.blade.php"), $this->eol($edit));
        $views[] = "resources/views/{$viewDir}/edit.blade.php";

        // show
        $show = str_replace(
            ['DummySlug','DummyRouteName','DummyShowRows'],
            [$viewDir,   $routeName,       $this->showRows($scaffold)],
            $this->files->get($this->stub('blade/show.blade.stub'))
        );
        $this->files->put(resource_path("views/{$viewDir}/show.blade.php"), $this->eol($show));
        $views[] = "resources/views/{$viewDir}/show.blade.php";

        return ['controller' => $controllerPath, 'views' => $views];
    }

    /* ---------------- builders ---------------- */

    protected function rules(Scaffold $scaffold, bool $update): string
    {
        // same rules strategy you already use for API/ControllerCreator
        $lines = [];
        $pk = $scaffold->primary_key ?: 'id';

        foreach ($scaffold->details as $d) {
            $field = (string)$d->name;
            if ($field === '' || $field === $pk) continue;

            $nullable = (bool)$d->nullable;
            $input    = strtolower((string)($d->input_type ?? 'text'));
            $source   = (string)($d->options_source ?? '');
            $valCol   = (string)($d->options_value_col ?? '');

            $rules = [];
            $rules[] = $nullable ? 'nullable' : 'required';
            $type = strtolower((string)$d->type);
            $rules[] = match (true) {
                in_array($type, ['tinyinteger','smallinteger','integer','biginteger','bigint','int']) => 'integer',
                $type === 'date'  => 'date',
                $type === 'email' => 'email',
                default           => 'string',
            };

            if (in_array($input, ['select','radio'], true)) {
                if ($source === 'static') {
                    $values = implode(',', array_map('trim', explode(',', (string)$d->options_value_col)));
                    if ($values !== '') $rules[] = 'in:'.$values;
                } elseif ($source) {
                    $fqcn = $this->normalizeFqcn($source);
                    $vCol = $valCol ?: 'id';
                    $lines[] = "            '{$field}' => array_filter([" .
                        implode(', ', array_map(fn($r) => var_export($r, true), $rules)) .
                        ", 'exists:' . (new \\{$fqcn})->getTable() . ',{$vCol}']),";
                    continue;
                }
            } elseif ($input === 'checkbox' && $source && $source !== 'static') {
                $fqcn  = $this->normalizeFqcn($source);
                $vCol  = $valCol ?: 'id';
                $relation = lcfirst(Str::pluralStudly(class_basename($fqcn)));
                $lines[] = "            '{$relation}' => ['sometimes','array'],";
                $lines[] = "            '{$relation}.*' => ['integer', function(\$a,\$v,\$f){ if(!\\{$fqcn}::where('{$vCol}',\$v)->exists()){ \$f('Invalid id: '.\$v);} }],";
                continue;
            }

            $lines[] = "            '{$field}' => [" . implode(', ', array_map(fn($r)=>var_export($r,true), $rules)) . "],";
        }

        return empty($lines) ? "        return [];\n" : "        return [\n".implode("\n",$lines)."\n        ];\n";
    }

    protected function optionsBag(Scaffold $scaffold): string
    {
        $rows = [];
        foreach ($scaffold->details as $d) {
            $type = strtolower((string)($d->input_type ?? ''));
            $src  = (string)($d->options_source ?? '');
            if (!in_array($type, ['select','radio','checkbox'], true) || !$src) continue;

            if ($src === 'static') {
                $values = array_values(array_filter(array_map('trim', explode(',', (string)$d->options_value_col)), 'strlen'));
                $labels = array_values(array_filter(array_map('trim', explode(',', (string)$d->options_label_col)), 'strlen'));
                $map    = [];
                foreach ($values as $i=>$v) { $map[$v] = $labels[$i] ?? $v; }
                $rows[] = "            '{$d->name}' => " . var_export($map, true) . ",";
            } else {
                $fqcn = $this->normalizeFqcn($src);
                $vCol = $d->options_value_col ?: 'id';
                $lCol = $d->options_label_col ?: 'name';
                $rows[] = "            '{$d->name}' => \\{$fqcn}::query()->pluck('{$lCol}','{$vCol}')->toArray(),";
                // For checkbox belongsToMany, also expose relation key for form convenience
                if ($type === 'checkbox') {
                    $rel = lcfirst(Str::pluralStudly(class_basename($fqcn)));
                    $rows[] = "            '{$rel}' => \\{$fqcn}::query()->pluck('{$lCol}','{$vCol}')->toArray(),";
                }
            }
        }
        if (empty($rows)) return "            // no dynamic options\n";
        return implode("\n", array_unique($rows)) . "\n";
    }

    protected function manyToManyKeys(Scaffold $scaffold): string
    {
        $keys = [];
        foreach ($scaffold->details as $d) {
            $type = strtolower((string)($d->input_type ?? ''));
            $src  = (string)($d->options_source ?? '');
            if ($type === 'checkbox' && $src && $src !== 'static') {
                $fqcn = $this->normalizeFqcn($src);
                $keys[] = lcfirst(Str::pluralStudly(class_basename($fqcn))); // relation name
                $keys[] = (string)$d->name;                                  // raw field if used
            }
        }
        $keys = array_values(array_unique(array_filter($keys)));
        return empty($keys) ? '' : implode(",\n            ", array_map(fn($k)=>"'{$k}'",$keys));
    }

    protected function syncManyToMany(Scaffold $scaffold): string
    {
        $lines = ["        foreach (request()->all() as \$k=>\$v) {/* no-op */} // keep IDE happy"];
        $done = false;
        foreach ($scaffold->details as $d) {
            $type = strtolower((string)($d->input_type ?? ''));
            $src  = (string)($d->options_source ?? '');
            if ($type === 'checkbox' && $src && $src !== 'static') {
                $fqcn = $this->normalizeFqcn($src);
                $rel  = lcfirst(Str::pluralStudly(class_basename($fqcn)));
                $lines = [
                    "        // {$rel} many-to-many sync",
                    "        if (\$request->has('{$rel}') || \$request->has('{$d->name}')) {",
                    "            \$ids = \$request->input('{$rel}', \$request->input('{$d->name}', []));",
                    "            if (!is_array(\$ids)) { \$ids = (array)\$ids; }",
                    "            \$model->{$rel}()->sync(\$ids);",
                    "        }"
                ];
                $done = true;
                break; // one example; add more if you need multi-many-to-many
            }
        }
        return $done ? implode("\n", $lines) . "\n" : "        // no many-to-many fields\n";
    }

    protected function indexHead(Scaffold $scaffold): string
    {
        $heads = [];
        foreach ($scaffold->details as $d) {
            $heads[] = "        <th>".e($d->name)."</th>";
        }
        return implode("\n", $heads);
    }

    protected function indexCols(Scaffold $scaffold): string
    {
        $cols = [];
        foreach ($scaffold->details as $d) {
            $name = (string)$d->name;
            $cols[] = "            <td>{{ \$row->{$name} }}</td>";
        }
        return implode("\n", $cols);
    }

    protected function formFields(Scaffold $scaffold): string
    {
        $blocks = [];
        foreach ($scaffold->details as $d) {
            $name  = (string)$d->name;
            $label = ucwords(str_replace('_',' ', $name));
            $type  = strtolower((string)($d->input_type ?? 'text'));
            $src   = (string)($d->options_source ?? '');
            $val   = (string)($d->options_value_col ?? '');
            $lab   = (string)($d->options_label_col ?? '');

            $valueExpr = "{{ old('{$name}', \$model->{$name} ?? '') }}";

            switch ($type) {
                case 'select':
                    if ($src) {
                        $blocks[] = <<<BLADE
<div class="mb-3">
    <label class="form-label">{$label}</label>
    <select name="{$name}" class="form-select">
        <option value="">-- Select --</option>
        @foreach(\$options['{$name}'] ?? [] as \$v => \$t)
            <option value="{{ \$v }}" @selected(old('{$name}', \$model->{$name} ?? null) == \$v)>{{ \$t }}</option>
        @endforeach
    </select>
</div>
BLADE;
                    } else {
                        $blocks[] = <<<BLADE
<div class="mb-3">
    <label class="form-label">{$label}</label>
    <input type="text" name="{$name}" class="form-control" value="{$valueExpr}">
</div>
BLADE;
                    }
                    break;

                case 'radio':
                    $blocks[] = <<<BLADE
<div class="mb-3">
    <label class="form-label d-block">{$label}</label>
    @foreach(\$options['{$name}'] ?? [] as \$v => \$t)
        <label class="me-3"><input type="radio" name="{$name}" value="{{ \$v }}" @checked(old('{$name}', \$model->{$name} ?? null) == \$v)> {{ \$t }}</label>
    @endforeach
</div>
BLADE;
                    break;

                case 'checkbox':
                    if ($src && $src !== 'static') {
                        $rel = lcfirst(Str::pluralStudly(class_basename($this->normalizeFqcn($src))));
                        $blocks[] = <<<BLADE
<div class="mb-3">
    <label class="form-label d-block">{$label}</label>
    @php \$selected = old('{$rel}', isset(\$model) ? \$model->{$rel}->pluck('id')->all() : (old('{$name}', []) ?? [])); @endphp
    @foreach(\$options['{$rel}'] ?? [] as \$v => \$t)
        <label class="me-3"><input type="checkbox" name="{$rel}[]" value="{{ \$v }}" @checked(in_array(\$v, (array)\$selected))> {{ \$t }}</label>
    @endforeach
</div>
BLADE;
                    } else {
                        $blocks[] = <<<BLADE
<div class="mb-3">
    <label class="form-label d-block">{$label}</label>
    @php \$selected = (array) old('{$name}', \$model->{$name} ?? []); @endphp
    @foreach(\$options['{$name}'] ?? [] as \$v => \$t)
        <label class="me-3"><input type="checkbox" name="{$name}[]" value="{{ \$v }}" @checked(in_array(\$v, \$selected))> {{ \$t }}</label>
    @endforeach
</div>
BLADE;
                    }
                    break;

                case 'date':
                    $blocks[] = <<<BLADE
<div class="mb-3">
    <label class="form-label">{$label}</label>
    <input type="date" name="{$name}" class="form-control" value="{$valueExpr}">
</div>
BLADE;
                    break;

                case 'email':
                    $blocks[] = <<<BLADE
<div class="mb-3">
    <label class="form-label">{$label}</label>
    <input type="email" name="{$name}" class="form-control" value="{$valueExpr}">
</div>
BLADE;
                    break;

                case 'file':
                    $blocks[] = <<<BLADE
<div class="mb-3">
    <label class="form-label">{$label}</label>
    <input type="file" name="{$name}" class="form-control">
</div>
BLADE;
                    break;

                case 'textarea':
                    $blocks[] = <<<BLADE
<div class="mb-3">
    <label class="form-label">{$label}</label>
    <textarea name="{$name}" class="form-control">{{ old('{$name}', \$model->{$name} ?? '') }}</textarea>
</div>
BLADE;
                    break;

                default:
                    $blocks[] = <<<BLADE
<div class="mb-3">
    <label class="form-label">{$label}</label>
    <input type="text" name="{$name}" class="form-control" value="{$valueExpr}">
</div>
BLADE;
                    break;
            }
        }
        return implode("\n\n", $blocks) . "\n";
    }

    protected function showRows(Scaffold $scaffold): string
    {
        $rows = [];
        foreach ($scaffold->details as $d) {
            $name = (string)$d->name;
            $label = ucwords(str_replace('_',' ',$name));
            $rows[] = "    <tr><th style=\"width:200px\">{$label}</th><td>{{ \$model->{$name} }}</td></tr>";
        }
        return implode("\n", $rows);
    }

    /* ---------------- utils ---------------- */

    protected function stub(string $name): string
    {
        $project = base_path('stubs/'.$name);
        if ($this->files->exists($project)) return $project;
        return __DIR__.'/stubs/'.$name; // package fallback if you ship them
    }

    protected function ensureDir(string $dir): void
    {
        if (!$this->files->isDirectory($dir)) $this->files->makeDirectory($dir, 0755, true);
    }

    protected function eol(string $s): string
    {
        return rtrim(preg_replace("/\r\n?/", "\n", $s)) . "\n";
    }

    protected function normalizeFqcn(?string $fqcn): string
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
}
