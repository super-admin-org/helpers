<?php

namespace SuperAdmin\Admin\Helpers\Model;

use Illuminate\Database\Eloquent\Model;

class ScaffoldDetail extends Model
{
    protected $table = 'helper_scaffold_details';
    protected $fillable = [
        'scaffold_id', 'name', 'type', 'nullable', 'key', 'default', 'comment', 'order'
    ];
}
