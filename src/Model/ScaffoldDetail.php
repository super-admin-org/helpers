<?php

namespace SuperAdmin\Admin\Helpers\Model;

use Illuminate\Database\Eloquent\Model;

class ScaffoldDetail extends Model
{
    protected $table = 'helper_scaffold_details';
    protected $fillable = [
        'scaffold_id', 'name', 'type', 'nullable', 'key', 'default', 'comment', 'order' ,'input_type','options_source','options_value_col','options_label_col'
    ];
}
