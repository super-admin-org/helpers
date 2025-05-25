<?php

namespace SuperAdmin\Admin\Helpers\Model;


use Illuminate\Database\Eloquent\Model;

class Scaffold extends Model
{
    protected $table = 'helper_scaffolds';
    protected $fillable = [
        'table_name', 'model_name', 'controller_name', 'create_options', 'primary_key', 'timestamps', 'soft_deletes'
    ];

    protected $casts = [
        'create_options' => 'array',
        'timestamps' => 'boolean',
        'soft_deletes' => 'boolean',
    ];

    public function details()
    {
        return $this->hasMany(ScaffoldDetail::class);
    }
}
