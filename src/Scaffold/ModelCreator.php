<?php

namespace SuperAdmin\Admin\Helpers\Scaffold;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use SuperAdmin\Admin\Helpers\Model\Scaffold;

class ModelCreator
{
    /**
     * Table name.
     *
     * @var string
     */
    protected $tableName;

    /**
     * Model name.
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

    /**
     * Map DB field types to PHPDoc types
     * @var array|string[]
     */
    protected $typeMap = [
        'string' => 'string',
        'integer' => 'int',
        'text' => 'string',
        'float' => 'float',
        'double' => 'float',
        'decimal' => 'float',
        'boolean' => 'bool',
        'date' => 'Carbon',
        'time' => 'string',
        'datetime' => 'Carbon',
        'timestamp' => 'Carbon',
        'char' => 'string',
        'mediumtext' => 'string',
        'longtext' => 'string',
        'tinyinteger' => 'int',
        'smallinteger' => 'int',
        'mediuminteger' => 'int',
        'biginteger' => 'int',
        'unsignedtinyinteger' => 'int',
        'unsignedsmallinteger' => 'int',
        'unsignedmediuminteger' => 'int',
        'unsignedinteger' => 'int',
        'unsignedbiginteger' => 'int',
        'enum' => 'string',
        'json' => 'array',
        'jsonb' => 'array',
        'datetimetz' => 'Carbon',
        'timetz' => 'string',
        'timestamptz' => 'Carbon',
        'nullabletimestamps' => 'Carbon', // not a column; usually skip it entirely
        'binary' => 'string',
        'ipaddress' => 'string',
        'macaddress' => 'string',
    ];
    /**
     * @var bool
     */
    protected $needsCarbon = false;
    /**
     * @var bool
     */
    protected $needsCollection = false;
    protected $scaffold;

    /**
     * ModelCreator constructor.
     *
     * @param string $tableName
     * @param string $name
     * @param null $files
     */
    public function __construct(Scaffold $scaffold = null, $files = null)
    {
        $this->tableName = $scaffold->table_name;

        $this->name = $scaffold->model_name;
        $this->scaffold = $scaffold;

        $this->files = $files ?: app('files');
        // dd($scaffold);
    }

    /**
     * Create a new migration file.
     *
     * @param string $keyName
     * @param bool|true $timestamps
     * @param bool|false $softDeletes
     *
     * @return string
     * @throws \Exception
     *
     */
    public function create()
    {
        $scaffold = $this->scaffold;
        $keyName = 'id';
        $timestamps = $scaffold->timestamps;
        $softDeletes = $scaffold->soft_deletes;
        $path = $this->getPath($this->name);

        if ($this->files->exists($path)) {
            throw new \Exception("Model [$this->name] already exists!");
        }

        $stub = $this->files->get($this->getStub());
        $scaffold = Scaffold::with('details')->find($scaffold->id);
        $phpDoc = $this->buildPhpDoc(
            $scaffold,

            $scaffold->primary_key ?: 'id',
            (bool)$scaffold->timestamps,
            (bool)$scaffold->soft_deletes
        );
        $fillableCode = $this->buildFillable($scaffold->details, $scaffold->primary_key ?: 'id');
        $relations = $this->buildRelations($scaffold->details);

        $stub = str_replace('DummyRelations', $relations, $stub);

        $stub = $this->replaceClass($stub, $this->name)
            ->replaceNamespace($stub, $this->name)
            ->replaceSoftDeletes($stub, $softDeletes)
            ->replaceTable($stub, $this->name)
            ->replaceTimestamp($stub, $timestamps)
            ->replacePrimaryKey($stub, $keyName)
            ->replacePhpDoc($stub, $phpDoc)
            ->replaceFillable($stub, $fillableCode)
            ->replaceRelations($stub, $relations)
            ->replaceSpace($stub);

        $this->files->put($path, $stub);

        return $path;
    }

    /**
     * Get path for migration file.
     *
     * @param string $name
     *
     * @return string
     */
    public function getPath($name)
    {
        $segments = explode('\\', $name);

        array_shift($segments);

        return app_path(implode('/', $segments)) . '.php';
    }

    /**
     * Get namespace of giving class full name.
     *
     * @param string $name
     *
     * @return string
     */
    protected function getNamespace($name)
    {
        return trim(implode('\\', array_slice(explode('\\', $name), 0, -1)), '\\');
    }

    /**
     * Replace class dummy.
     *
     * @param string $stub
     * @param string $name
     *
     * @return $this
     */
    protected function replaceClass(&$stub, $name)
    {
        $class = str_replace($this->getNamespace($name) . '\\', '', $name);

        $stub = str_replace('DummyClass', $class, $stub);

        return $this;
    }

    /**
     * Replace namespace dummy.
     *
     * @param string $stub
     * @param string $name
     *
     * @return $this
     */
    protected function replaceNamespace(&$stub, $name)
    {
        $stub = str_replace(
            'DummyNamespace',
            $this->getNamespace($name),
            $stub
        );

        return $this;
    }

    /**
     * Replace soft-deletes dummy.
     *
     * @param string $stub
     * @param bool $softDeletes
     *
     * @return $this
     */
    protected function replaceSoftDeletes(&$stub, $softDeletes)
    {
        $import = $use = '';

        if ($softDeletes) {
            $import = "use Illuminate\\Database\\Eloquent\\SoftDeletes;\n";
            $use = "use SoftDeletes;\n";
        }

        $stub = str_replace(['DummyImportSoftDeletesTrait', 'DummyUseSoftDeletesTrait'], [$import, $use], $stub);

        return $this;
    }

    /**
     * Replace primarykey dummy.
     *
     * @param string $stub
     * @param string $keyName
     *
     * @return $this
     */
    protected function replacePrimaryKey(&$stub, $keyName)
    {
        $modelKey = $keyName == 'id' ? '' : "protected \$primaryKey = '$keyName';\n";

        $stub = str_replace('DummyModelKey', $modelKey, $stub);

        return $this;
    }

    /**
     * Replace Table name dummy.
     *
     * @param string $stub
     * @param string $name
     *
     * @return $this
     */
    protected function replaceTable(&$stub, $name)
    {
        $class = str_replace($this->getNamespace($name) . '\\', '', $name);

        $table = Str::plural(strtolower($class)) !== $this->tableName ? "protected \$table = '$this->tableName';\n" : '';

        $stub = str_replace('DummyModelTable', $table, $stub);

        return $this;
    }

    /**
     * Replace timestamps dummy.
     *
     * @param string $stub
     * @param bool $timestamps
     *
     * @return $this
     */
    protected function replaceTimestamp(&$stub, $timestamps)
    {
        $useTimestamps = $timestamps ? '' : "public \$timestamps = false;\n";

        $stub = str_replace('DummyTimestamp', $useTimestamps, $stub);

        return $this;
    }

    /**
     * Replace spaces.
     *
     * @param string $stub
     *
     * @return mixed
     */
    public function replaceSpace($stub)
    {
        return str_replace(["\n\n\n", "\n    \n"], ["\n\n", ''], $stub);
    }

    /**
     * Get stub path of model.
     *
     * @return string
     */
    public function getStub()
    {
        return __DIR__ . '/stubs/model.stub';
    }

    /**
     * Build the PHPDoc block from Scaffold + Details + Relations.
     *
     * @param \SuperAdmin\Admin\Helpers\Model\Scaffold $scaffold
     * @param string $primaryKey
     * @param bool $usesTimestamps
     * @param bool $usesSoftDeletes
     * @return string  Full /** ...
     *
     * docblock
     */
    protected function buildPhpDoc(Scaffold $scaffold, string $primaryKey = 'id', bool $usesTimestamps = true, bool $usesSoftDeletes = false): string
    {
        $this->needsCarbon = false;
        $this->needsCollection = false;

        $lines = [];
        $lines[] = '/**';

        // Primary key
        $lines[] = " * @property int \${$primaryKey}";

        $details = $scaffold->details;

        // Fields
        foreach ($details as $d) {
            $name = $d->name ?? null;
            $type = strtolower((string)($d->type ?? 'string'));
            if (!$name || $name === $primaryKey) {
                continue;
            }

            // Map DB type to PHP type
            $phpType = $this->typeMap[$type] ?? 'mixed';
            if ($phpType === 'Carbon') {
                $this->needsCarbon = true;
                $phpType .= '|null';
            } elseif ((bool)($d->nullable ?? false)) {
                $phpType .= '|null';
            }

            $lines[] = " * @property {$phpType} \${$name}";
        }

        // Timestamps
        if ($usesTimestamps) {
            $lines[] = " * @property Carbon|null \$created_at";
            $lines[] = " * @property Carbon|null \$updated_at";
            $this->needsCarbon = true;
        }

        // Soft deletes
        if ($usesSoftDeletes) {
            $lines[] = " * @property Carbon|null \$deleted_at";
            $this->needsCarbon = true;
        }

        // Implicit belongsTo from *_id fields
        foreach ($details as $d) {
            $name = $d->name ?? '';
            if (Str::endsWith($name, '_id')) {
                $relatedBase = Str::beforeLast($name, '_id');
                $relatedName = Str::camel($relatedBase);
                $relatedModel = Str::studly($relatedBase);
                $lines[] = " * @property-read {$relatedModel} \${$relatedName}";
            }
        }

        // Relations inferred from input_type + options_source
        foreach ($details as $d) {
            if (!in_array($d->input_type, ['select', 'radio', 'checkbox'])) {
                continue;
            }

            if (!empty($d->options_source) && $d->options_source !== 'static') {
                $relatedModel = ltrim($d->options_source, '\\');
                $relatedBase = class_basename($relatedModel);
                $relationName = Str::camel(Str::plural($relatedBase));

                $type = match ($d->input_type) {
                    'checkbox' => "Collection<int, {$relatedBase}>",
                    'select', 'radio' => $relatedBase,
                    default => 'mixed',
                };

                $lines[] = " * @property-read {$type} \${$relationName}";
//                if ($d->input_type === 'checkbox') {
//                    $lines[] = " * @property-read int|null \${$relationName}_count";
//                    $this->needsCollection = true;
//                }
            }
        }

        $lines[] = " *";
        $lines[] = " * @mixin \Illuminate\Database\Eloquent\Model";
        $lines[] = " */";

        return implode("\n", $lines);
    }

    /**
     * Replace the DummyPhpDoc (and import placeholders) in the stub.
     *
     * @param string $stub
     * @param string $phpDoc
     * @return $this
     */
    protected function replacePhpDoc(&$stub, string $phpDoc)
    {
        $stub = str_replace('DummyPhpDoc', $phpDoc, $stub);

        // Imports
        $stub = str_replace('DummyImportCarbon', $this->needsCarbon ? "use Illuminate\\Support\\Carbon;" : '', $stub);
        $stub = str_replace('DummyImportCollection', $this->needsCollection ? "use Illuminate\\Database\\Eloquent\\Collection;" : '', $stub);

        return $this;
    }

    /**
     * Build the $fillable property from scaffold details.
     * *
     * * @param \Illuminate\Support\Collection|\SuperAdmin\Admin\Helpers\Model\ScaffoldDetail[] $details
     * * @param string $primaryKey
     * * @return string
     */
    protected function buildFillable($details, string $primaryKey = 'id'): string
    {
        $fields = [];
        Log::info('buildFillable:' . json_encode($details));
        foreach ($details as $d) {
            $name = $d->name ?? null;
            if (!$name || $name === $primaryKey) {
                continue; // skip primary key
            }
            $fields[] = $name;
        }

        // Create the PHP array string
        if (empty($fields)) {
            return ''; // no fillable
        }

        $indent = '    '; // 4 spaces
        $lines = [];
        $lines[] = '    protected $fillable = [';
        foreach ($fields as $f) {
            $lines[] = "{$indent}'{$f}',";
        }
        $lines[] = '];';
        $phpDocs = '/**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */';
        return $phpDocs . "\n" . implode("\n{$indent}", $lines);
    }

    /**
     * Replace the fillable placeholder within the given stub.
     *
     * @param string $stub The stub content to modify.
     * @param string $fillableCode The fillable code to replace the placeholder with.
     * @return $this
     */
    protected function replaceFillable(&$stub, string $fillableCode)
    {
        $stub = str_replace('DummyFillable', $fillableCode, $stub);
        return $this;
    }

    /**
     * Replaces placeholders with actual relations in the given stub content.
     *
     * @param string $stub The stub content passed by reference.
     * @param string $relations The relations to replace the placeholder with.
     * @return $this
     */
    protected function replaceRelations(&$stub, string $relations)
    {
        $stub = str_replace('DummyRelations', $relations, $stub);
        return $this;
    }

    /**
     * Build the relationships for the given fields.
     *
     * @param array $fields Collection of field definitions used to build relations.
     * @return string Generated PHP code for model relationships based on field configurations.
     */
    protected function buildRelations($fields): string
    {
        $output = '';

        foreach ($fields as $field) {
            if (!in_array($field->input_type, ['select', 'radio', 'checkbox'])) continue;

            if ($field->options_source && $field->options_source !== 'static') {
                $relatedModel = $field->options_source;
                $value = $field->options_value_col;
                $relationName = Str::camel(Str::plural(class_basename($relatedModel)));
                $phpDocsRelations = Str::lower(Str::headline($relationName));

                $methodBody = match ($field->input_type) {
                    'checkbox' => "return \$this->belongsToMany(\\{$relatedModel}::class);",
                    'select' => "return \$this->hasOne(\\{$relatedModel}::class, '{$field->name}','{$field->options_value_col}');",
                    'radio' => "return \$this->hasOne(\\{$relatedModel}::class, '{$field->name}','{$field->options_value_col}');",
                };
                $phpDocsMethodBody = match ($field->input_type) {
                    'checkbox' => "\Illuminate\Database\Eloquent\Relations\BelongsToMany",
                    'select' => "\Illuminate\Database\Eloquent\Relations\HasOne",
                    'radio' => "\Illuminate\Database\Eloquent\Relations\HasOne",
                };
                $phpDocs = "    /**
     * Retrieve the {$phpDocsRelations} associated with the model.
     *
     * @return {$phpDocsMethodBody}
     */";
                $output .= "\n" . $phpDocs . "\n    public function {$relationName}()\n    {\n        {$methodBody}\n    }\n";
            }
        }

        return $output;
    }


}
