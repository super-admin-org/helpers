<?php

namespace SuperAdmin\Admin\Helpers\Scaffold;

use Illuminate\Support\Facades\Log;
use SuperAdmin\Admin\Helpers\Model\Scaffold;

class ControllerCreator
{
    /**
     * Controller full name.
     *
     * @var string
     */
    protected $name;

    /**
     * The filesystem instance.
     *
     * @var \Illuminate\Filesystem\Filesystem
     */
    protected $files;

    protected $DummyGridField = '';

    protected $DummyShowField = '';

    protected $DummyFormField = '';

    /**
     * ControllerCreator constructor.
     *
     * @param string $name
     * @param null $files
     */
    public function __construct($name, $files = null)
    {
        $this->name = $name;

        $this->files = $files ?: app('files');
    }

    /**
     * Create the controller for given scaffold id or instance.
     *
     * @param int|string|Scaffold $scaffoldOrId
     * @return string               Absolute path to generated file
     */
    public function create(int $scaffoldId): string
    {
        $scaffold = Scaffold::with('details')->findOrFail($scaffoldId);
        $stub = $this->files->get($this->getStub());

        // Resolve names
        $controllerFqcn = ltrim($scaffold->controller_name, '\\');              // App\Admin\Controllers\StudentInfoController
        $namespace = trim(\Illuminate\Support\Str::beforeLast($controllerFqcn, '\\'), '\\');
        $class = class_basename($controllerFqcn);
        $modelFqcn = ltrim($scaffold->model_name, '\\');                   // App\Models\StudentInfo
        $modelShort = class_basename($modelFqcn);

        // 🔑 Build fields for Grid, Form, (optional) Show
        // (each method sets $this->DummyGridField / $this->DummyFormField / $this->DummyShowField OR returns strings)
        $this->buildGridFields($scaffold);
        $this->buildFormFields($scaffold);
        $this->buildShowFields($scaffold); // if you have a detail() section

        // Replace placeholders from controller.stub
        $code = str_replace([
            'DummyNamespace',
            'DummyClass',
            'DummyModelNamespace',
            'DummyModel',
            'DummyGridField',
            'DummyFormField',
            'DummyShowField',
        ], [
            $namespace,
            $class,
            $modelFqcn,
            $modelShort,
            $this->DummyGridField ?? '',
            $this->DummyFormField ?? '',
            $this->DummyShowField ?? '',
        ], $stub);

        // Write controller
        $path = app_path('Admin/Controllers/' . $class . '.php');
        $this->files->put($path, $code);

        return $path;
    }

    /**
     * Build Show fields for laravel-admin detail() view.
     * Populates $this->DummyShowField.
     *
     * Rules:
     * - select/radio + static: show mapped label
     * - select/radio + model:  show related model's label (e.g. bloodGroup->group_name)
     * - checkbox + static:     show joined labels
     * - checkbox + model:      show joined related labels (e.g. studentTypes->pluck('name')->implode(', '))
     *
     * @param \SuperAdmin\Admin\Helpers\Model\Scaffold $scaffold
     * @return $this
     */
    protected function buildShowFields($scaffold)
    {
        $rows = [];

        foreach ($scaffold->details as $d) {
            $name = (string)$d->name;
            $label = ucwords(str_replace('_', ' ', $name));
            $type = strtolower((string)($d->input_type ?? 'text'));
            $src = $d->options_source;
            $val = $d->options_value_col;
            $lab = $d->options_label_col;

            // Helpers
            $labelCol = $lab ?: 'name';
            $valueCol = $val ?: 'id';

            // Build a PHP array literal for static maps when present
            $makeStaticMap = function (?string $valuesCsv, ?string $labelsCsv): string {
                $values = array_values(array_filter(array_map('trim', explode(',', (string)$valuesCsv)), 'strlen'));
                $labels = array_values(array_filter(array_map('trim', explode(',', (string)$labelsCsv)), 'strlen'));
                $pairs = [];
                foreach ($values as $i => $v) {
                    $pairs[] = var_export($v, true) . ' => ' . var_export($labels[$i] ?? $v, true);
                }
                return '[' . implode(', ', $pairs) . ']';
            };

            // Choose based on input_type + source
            switch ($type) {
                case 'select':
                case 'radio':
                    if ($src === 'static') {
                        $map = $makeStaticMap($val, $lab);
                        $rows[] = <<<PHP
\$show->field('{$name}', '{$label}')->as(function (\$v) {
    \$map = {$map};
    return \$map[\$v] ?? \$v;
});
PHP;
                    } elseif ($src) {
                        $related = lcfirst(class_basename($src)); // e.g. BloodGroup -> bloodGroup
                        $rows[] = <<<PHP
\$show->field('{$name}', '{$label}')->as(function () {
    return optional(\$this->{$related})->{$labelCol};
});
PHP;
                    } else {
                        $rows[] = "\$show->field('{$name}', '{$label}');";
                    }
                    break;

                case 'checkbox':
                    if ($src === 'static') {
                        // Assume DB stores CSV of values; map to labels and join
                        $map = $makeStaticMap($val, $lab);
                        $rows[] = <<<PHP
\$show->field('{$name}', '{$label}')->as(function (\$v) {
    \$map = {$map};
    \$vals = array_values(array_filter(array_map('trim', explode(',', (string)\$v)), 'strlen'));
    \$labels = array_map(fn(\$x) => \$map[\$x] ?? \$x, \$vals);
    return implode(', ', \$labels);
});
PHP;
                    } elseif ($src) {
                        // belongsToMany rendered as comma-joined labels
                        $relation = lcfirst(\Illuminate\Support\Str::pluralStudly(class_basename($src))); // e.g. StudentType -> studentTypes
                        $rows[] = <<<PHP
\$show->field('{$relation}', '{$label}')->as(function () {
    return \$this->{$relation}->pluck('{$labelCol}')->implode(', ');
});
PHP;
                    } else {
                        $rows[] = "\$show->field('{$name}', '{$label}');";
                    }
                    break;

                default:
                    // everything else: plain field
                    $rows[] = "\$show->field('{$name}', '{$label}');";
                    break;
            }
        }

        // Join with proper indentation for the stub
        $this->DummyShowField = '        ' . implode("\n\n        ", $rows) . "\n";
        return $this;
    }

    protected function buildGridFields($scaffold): self
    {
        $rows = [];
        $handled = [];

        foreach ($scaffold->details as $d) {
            $name = $d->name;
            $label = ucwords(str_replace('_', ' ', $name));
            $type = strtolower((string)($d->input_type ?? 'text'));
            $src = $d->options_source;
            $lab = $d->options_label_col ?: 'name';

            if (in_array($type, ['select', 'radio']) && $src && $src !== 'static') {
                $related = lcfirst(class_basename($src));          // e.g. BloodGroup -> bloodGroup
                $rows[] = "\$grid->column('{$name}', '{$label}')->display(function () { return optional(\$this->{$related})->{$lab}; });";
                $handled[] = $name;
                continue;
            }

            if ($type === 'checkbox' && $src && $src !== 'static') {
                $relation = lcfirst(\Illuminate\Support\Str::pluralStudly(class_basename($src))); // e.g. StudentTypes
                $rows[] = "\$grid->column('{$relation}', '{$label}')->display(function () { return \$this->{$relation}->pluck('{$lab}')->implode(', '); });";
                $handled[] = $relation;
                continue;
            }
        }

        // Add remaining scalar columns as sortable
        foreach ($scaffold->details as $d) {
            $name = $d->name;
            if (in_array($name, $handled, true)) continue;
            $rows[] = "\$grid->column('{$name}', '{$name}')->sortable();";
        }

        $this->DummyGridField = "        " . implode("\n        ", $rows) . "\n";
        return $this;
    }

    protected function buildFormFields($scaffold): self
    {
        $rows = [];

        foreach ($scaffold->details as $d) {
            $name = $d->name;
            $label = ucwords(str_replace('_', ' ', $name));
            $type = strtolower((string)($d->input_type ?? 'text'));
            $src = $d->options_source;
            $val = $d->options_value_col;
            $lab = $d->options_label_col;

            // helper to emit options call
            $optCall = function (string $sourceExpr) use ($src, $val, $lab) {
                $s = $src === 'static' ? "'static'" : "\\{$src}::class";
                $v = $val ? "'{$val}'" : 'null';
                $l = $lab ? "'{$lab}'" : 'null';
                return "\$this->optionsMap({$s}, {$v}, {$l})";
            };

            switch ($type) {
                case 'select':
                    if ($src) {
                        $rows[] = "\$form->select('{$name}', '{$label}')->options({$optCall($src)});";
                    } else {
                        $rows[] = "\$form->select('{$name}', '{$label}');";
                    }
                    break;

                case 'radio':
                    if ($src) {
                        $rows[] = "\$form->radio('{$name}', '{$label}')->options({$optCall($src)});";
                    } else {
                        $rows[] = "\$form->radio('{$name}', '{$label}');";
                    }
                    break;

                case 'checkbox':
                    if ($src && $src !== 'static') {
                        // relation name: camel(plural(class_basename(FQCN)))
                        $related = class_basename($src);
                        $relation = lcfirst(\Illuminate\Support\Str::pluralStudly($related)); // e.g. StudentTypes -> studentTypes
                        $rows[] = "\$form->multipleSelect('{$relation}', '{$label}')->options({$optCall($src)});";
                    } else {
                        $rows[] = "\$form->checkbox('{$name}', '{$label}')->options({$optCall('static')});";
                    }
                    break;

                case 'date':
                    $rows[] = "\$form->date('{$name}', '{$label}');";
                    break;

                case 'email':
                    $rows[] = "\$form->email('{$name}', '{$label}');";
                    break;

                case 'file':
                    $rows[] = "\$form->file('{$name}', '{$label}');";
                    break;

                case 'textarea':
                    $rows[] = "\$form->textarea('{$name}', '{$label}');";
                    break;

                case 'number':
                case 'integer':
                case 'tinyinteger':
                case 'smallinteger':
                case 'biginteger':
                    $rows[] = "\$form->number('{$name}', '{$label}');";
                    break;

                default:
                    // text, char, string, others
                    $rows[] = "\$form->text('{$name}', '{$label}');";
                    break;
            }
        }

        $this->DummyFormField = '        ' . implode("\n\n        ", $rows) . "\n";
        return $this;
    }

    /**
     * Get stub file path.
     *
     * @return string
     */
    public function getStub()
    {
        return __DIR__ . '/stubs/controller.stub';
    }

}
