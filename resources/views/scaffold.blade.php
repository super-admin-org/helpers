<style>
    h4 small {
        font-size: 1rem;
    }
</style>
<style>
    /* wrapper keeps it scrollable on smaller screens */
    .table-responsive.scaffold-wrap {
        overflow-x: auto;
    }

    /* table tuning */
    .table.scaffold-table {
        table-layout: fixed;
        min-width: 1400px; /* force horizontal scroll instead of squishing */
        --col1: 48px; /* order/drag column width */
    }

    .table.scaffold-table th,
    .table.scaffold-table td {
        vertical-align: middle;
        white-space: nowrap;
        padding: .5rem .5rem;
    }

    /* sticky header */
    .table.scaffold-table thead th {
        position: sticky;
        top: 0;
        background: #fff;
        z-index: 4;
        box-shadow: 0 1px 0 rgba(0, 0, 0, .05);
    }

    /* sticky first two columns (order + field name) */
    .table.scaffold-table thead th:nth-child(1),
    .table.scaffold-table tbody td:nth-child(1) {
        position: sticky;
        left: 0;
        z-index: 5;
        background: #fff;
        width: var(--col1);
        min-width: var(--col1);
        max-width: var(--col1);
        text-align: center;
    }

    .table.scaffold-table thead th:nth-child(2),
    .table.scaffold-table tbody td:nth-child(2) {
        position: sticky;
        left: var(--col1);
        z-index: 3;
        background: #fff;
        min-width: 200px;
    }

    /* column widths (12 columns total) */
    .table.scaffold-table thead th:nth-child(3), /* Type */
    .table.scaffold-table tbody td:nth-child(3) {
        width: 120px;
    }

    .table.scaffold-table thead th:nth-child(4), /* Nullable */
    .table.scaffold-table tbody td:nth-child(4) {
        width: 80px;
        text-align: center;
    }

    .table.scaffold-table thead th:nth-child(5), /* Key */
    .table.scaffold-table tbody td:nth-child(5) {
        width: 120px;
    }

    .table.scaffold-table thead th:nth-child(6), /* Default */
    .table.scaffold-table tbody td:nth-child(6) {
        width: 140px;
    }

    .table.scaffold-table thead th:nth-child(7), /* Comment */
    .table.scaffold-table tbody td:nth-child(7) {
        min-width: 200px;
    }

    .table.scaffold-table thead th:nth-child(8), /* Input type */
    .table.scaffold-table tbody td:nth-child(8) {
        width: 140px;
    }

    .table.scaffold-table thead th:nth-child(9), /* Source for option */
    .table.scaffold-table tbody td:nth-child(9) {
        min-width: 180px;
    }

    .table.scaffold-table thead th:nth-child(10), /* Value(s) */
    .table.scaffold-table tbody td:nth-child(10) {
        min-width: 260px;
    }

    .table.scaffold-table thead th:nth-child(11), /* Label(s) */
    .table.scaffold-table tbody td:nth-child(11) {
        min-width: 260px;
    }

    .table.scaffold-table thead th:nth-child(12), /* Action */
    .table.scaffold-table tbody td:nth-child(12) {
        width: 120px;
    }

    /* make inputs/selects span full cell width */
    .table.scaffold-table td .form-control,
    .table.scaffold-table td .form-select {
        width: 100%;
    }

    /* nicer row affordances */
    .table.scaffold-table tbody tr:nth-child(even) {
        background: #fafafa;
    }

    .table.scaffold-table tbody tr:hover {
        background: #f5f9ff;
    }

    .move-handle {
        cursor: move;
        color: #888;
    }

    .table-field-remove {
        padding: .25rem .5rem !important;
    }
</style>

<div class="card card-primary">
    <div class="card-header with-border">
        <h3 class="card-title">Scaffold</h3>
    </div>
    <div class="card-body">
        <form method="post" action="{{ $action }}" class="needs-validation" autocomplete="off" id="scaffold">
            @csrf
            {{--            @if(isset($scaffold))--}}
            {{--                @method('PUT')--}}
            {{--            @endif--}}
            <div class="card-body">
                <div class="row mb-3">
                    <label for="inputTableName" class="col-sm-2 col-form-label">Table name</label>
                    <div class="col-sm-4">
                        <input type="text" name="table_name" class="form-control" id="inputTableName"
                               placeholder="table name" value="{{ old('table_name', $scaffold->table_name ?? '') }}"
                               required>
                    </div>
                    <span class="invalid-feedback" id="table-name-help">
                        <i class="icon-info"></i>&nbsp; Table name can't be empty!
                    </span>
                </div>

                <div class="row mb-3">
                    <label for="inputModelName" class="col-sm-2 col-form-label">Model</label>
                    <div class="col-sm-4">
                        <input type="text" name="model_name" class="form-control" id="inputModelName"
                               placeholder="model"
                               value="{{ old('model_name', $scaffold->model_name ?? 'App\\Models\\') }}">
                    </div>
                </div>

                <div class="row mb-3">
                    <label for="inputControllerName" class="col-sm-2 col-form-label">Controller</label>
                    <div class="col-sm-4">
                        <input type="text" name="controller_name" class="form-control" id="inputControllerName"
                               placeholder="controller"
                               value="{{ old('controller_name', $scaffold->controller_name ?? 'App\\Admin\\Controllers\\') }}">
                    </div>
                </div>

                <div class="row mb-3">
                    <div class="offset-sm-2 col-sm-10">
                        @php
                            $createOptions = old('create', $scaffold->create_options ?? []);
                        @endphp
                        @foreach(['migration', 'model', 'controller', 'migrate', 'menu_item','recreate_table'] as $option)
                            <div class="form-check form-check-inline me-3">
                                <input class="form-check-input" type="checkbox" name="create[]" value="{{ $option }}"
                                       id="{{ $option }}" {{ in_array($option, $createOptions) ? 'checked' : '' }}>
                                <label class="form-check-label"
                                       for="{{ $option }}">{{ ucfirst(str_replace('_', ' ', $option)) }}</label>
                            </div>
                        @endforeach
                    </div>
                </div>

                <hr>
                <h4>Fields <small>(Note, `id` is already included in field list)</small></h4>
                <div class="table-responsive scaffold-wrap">

                    <table class="table table-hover table-bordered scaffold-table" id="table-fields">
                        <thead>
                        <tr>
                            <th>Order</th>
                            <th>Field name</th>
                            <th>Type</th>
                            <th>Nullable</th>
                            <th>Key</th>
                            <th>Default</th>
                            <th>Comment</th>
                            <th>Input type</th>
                            <th>Source for option</th>
                            <th>Value(s)</th>
                            <th>Label(s)</th>
                            <th>Action</th>
                        </tr>
                        </thead>
                        <tbody id="table-fields-body">
                        @php
                            $fields = old('fields', isset($scaffold) ? $scaffold->details->toArray() : []);
                        @endphp
                        @foreach($fields as $index => $field)
                            @php
                                $it     = old("fields.$index.input_type", $field['input_type'] ?? 'text');          // text/select/etc.
                                $src    = old("fields.$index.options_source", $field['options_source'] ?? '');      // '', 'static', FQCN
                                $valCol = old("fields.$index.options_value_col", $field['options_value_col'] ?? ''); // csv or column
                                $labCol = old("fields.$index.options_label_col", $field['options_label_col'] ?? '');
                                //$isChoice = in_array($it, ['select','radio','checkbox']);
                            @endphp
                            <tr>
                                <td><i class="icon-arrows-alt move-handle"></i></td>
                                <td><input type="text" name="fields[{{ $index }}][name]" class="form-control"
                                           value="{{ $field['name'] ?? '' }}"></td>
                                <td>
                                    <select name="fields[{{ $index }}][type]" class="form-select">
                                        @foreach($dbTypes as $type)
                                            <option
                                                    value="{{ $type }}" {{ ($field['type'] ?? '') == $type ? 'selected' : '' }}>{{ $type }}</option>
                                        @endforeach
                                    </select>
                                </td>
                                <td><input type="checkbox" class="form-check-input"
                                           name="fields[{{ $index }}][nullable]" {{ isset($field['nullable']) && $field['nullable'] ? 'checked' : '' }}>
                                </td>
                                <td>
                                    <select name="fields[{{ $index }}][key]" class="form-select">
                                        <option value="" {{ ($field['key'] ?? '') == '' ? 'selected' : '' }}>NULL
                                        </option>
                                        <option
                                                value="unique" {{ ($field['key'] ?? '') == 'unique' ? 'selected' : '' }}>
                                            Unique
                                        </option>
                                        <option value="index" {{ ($field['key'] ?? '') == 'index' ? 'selected' : '' }}>
                                            Index
                                        </option>
                                    </select>
                                </td>
                                <td><input type="text" class="form-control" name="fields[{{ $index }}][default]"
                                           value="{{ $field['default'] ?? '' }}"></td>
                                <td><input type="text" class="form-control" name="fields[{{ $index }}][comment]"
                                           value="{{ $field['comment'] ?? '' }}"></td>

                                {{-- NEW: Input type --}}
                                <td>
                                    <select name="fields[{{ $index }}][input_type]" class="form-select js-input-type">
                                        <option value="text" {{ $it==='text'?'selected':'' }}>Text</option>
                                        <option value="textarea" {{ $it==='textarea'?'selected':'' }}>Textarea</option>
                                        <option value="number" {{ $it==='number'?'selected':'' }}>Number</option>
                                        <option value="email" {{ $it==='email'?'selected':'' }}>Email</option>
                                        <option value="date" {{ $it==='date'?'selected':'' }}>Date</option>
                                        <option value="file" {{ $it==='file'?'selected':'' }}>File</option>

                                        <option value="image" {{ $it==='image'?'selected':'' }}>Image</option>
                                        <option value="password" {{ $it==='password'?'selected':'' }}>Password</option>
                                        <option value="hiden" {{ $it==='hiden'?'selected':'' }}>Hidden</option>
                                        <option value="switch" {{ $it==='switch'?'selected':'' }}>Switch</option>
                                        <option value="checkbox" {{ $it==='checkbox'?'selected':'' }}>Checkbox</option>
                                        <option value="radio" {{ $it==='radio'?'selected':'' }}>Radio</option>
                                        <option value="select" {{ $it==='select'?'selected':'' }}>Select</option>
                                    </select>
                                </td>

                                {{-- NEW: Source for option --}}
                                <td>
                                    <select name="fields[{{ $index }}][options_source]"
                                            class="form-select js-opt-source">
                                        <option value="" {{ $src===''?'selected':'' }}>— none —</option>
                                        <option value="static" {{ $src==='static'?'selected':'' }}>static</option>
                                        @foreach(($modelsForSelect ?? []) as $fqcn)
                                            <option
                                                    value="{{ $fqcn }}" {{ $src===$fqcn?'selected':'' }}>{{ $fqcn }}</option>
                                        @endforeach
                                    </select>
                                </td>

                                {{-- NEW: Value(s) --}}
                                <td>
                                    <input type="text"
                                           name="fields[{{ $index }}][options_value_col]"
                                           class="form-control js-values"
                                           value="{{ $valCol }}"

                                           placeholder="{{ $src==='static' ? 'male,female,other' : ($src ? 'id' : '') }}">
                                </td>

                                {{-- NEW: Label(s) --}}
                                @php $auto = filled($labCol) ? 0 : 1; @endphp
                                <td>
                                    <input type="text"
                                           name="fields[{{ $index }}][options_label_col]"
                                           class="form-control js-labels"
                                           value="{{ $labCol }}"
                                           data-autofilled="{{ $auto }}"

                                           placeholder="{{ $src==='static' ? 'Male,Female,Other' : ($src ? 'name' : '') }}">
                                </td>
                                <td><a class="btn btn-sm btn-danger table-field-remove"><i class="icon-trash"></i>
                                        remove</a></td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
                <button type="button" class="btn btn-success btn-sm" id="add-table-field"><i class="icon-plus"></i> Add
                    field
                </button>

                <hr>
                <div class="d-flex align-items-center">
                    <div class="form-check me-3">
                        <input class="form-check-input" type="checkbox" id="timestamps"
                               name="timestamps" {{ old('timestamps', $scaffold->timestamps ?? true) ? 'checked' : '' }}>
                        <label class="form-check-label" for="timestamps">Timestamps</label>
                    </div>
                    <div class="form-check me-3">
                        <input class="form-check-input" type="checkbox" id="soft-deletes"
                               name="soft_deletes" {{ old('soft_deletes', $scaffold->soft_deletes ?? false) ? 'checked' : '' }}>
                        <label class="form-check-label" for="soft-deletes">Soft Deletes</label>
                    </div>
                    <div class="d-flex align-items-center">
                        <label class="me-2" for="inputPrimaryKey">Primary key</label>
                        <input type="text" name="primary_key" class="form-control" id="inputPrimaryKey"
                               value="{{ old('primary_key', $scaffold->primary_key ?? 'id') }}" style="width: 120px;">
                    </div>
                </div>
            </div>

            <div class="card-footer clearfix">
                <button type="submit"
                        class="btn btn-info float-end">{{ isset($scaffold) ? 'Update' : 'Submit' }}</button>
            </div>
        </form>
    </div>
</div>

<template id="table-field-tpl">
    <tr>
        <td><i class="icon-arrows-alt move-handle"></i></td>
        <td><input type="text" name="fields[__index__][name]" class="form-control"></td>
        <td>
            <select name="fields[__index__][type]" class="form-select">
                @foreach($dbTypes as $type)
                    <option value="{{ $type }}">{{ $type }}</option>
                @endforeach
            </select>
        </td>
        <td><input type="checkbox" name="fields[__index__][nullable]" class="form-check-input" checked></td>
        <td>
            <select name="fields[__index__][key]" class="form-select">
                <option value="" selected>NULL</option>
                <option value="unique">Unique</option>
                <option value="index">Index</option>
            </select>
        </td>
        <td><input type="text" name="fields[__index__][default]" class="form-control"></td>
        <td><input type="text" name="fields[__index__][comment]" class="form-control"></td>
        <td>
            <select name="fields[__index__][input_type]" class="form-select js-input-type">
                <option value="text">Text</option>
                <option value="textarea">Textarea</option>
                <option value="number">Number</option>
                <option value="email">Email</option>
                <option value="date">Date</option>
                <option value="file">File</option>
                <option value="image">Image</option>
                <option value="password">Password</option>
                <option value="hiden">Hidden</option>
                <option value="switch">Switch</option>
                <option value="checkbox">Checkbox</option>
                <option value="radio">Radio</option>
                <option value="select">Select</option>
            </select>
        </td>

        <td>
            <select name="fields[__index__][options_source]" class="form-select js-opt-source">
                <option value="">— none —</option>
                <option value="static">static</option>
                @foreach($modelsForSelect ?? [] as $fqcn)
                    <option value="{{ $fqcn }}">{{ $fqcn }}</option>
                @endforeach
            </select>
        </td>

        <td>
            <!-- static values (csv) OR model value column -->
            <input type="text"
                   placeholder="male,female,other  OR  id"
                   name="fields[__index__][options_value_col]"
                   class="form-control js-values">
        </td>

        <td>
            <!-- static labels (csv) OR model label column -->
            <input type="text"
                   placeholder="Male,Female,Other  OR  name"
                   name="fields[__index__][options_label_col]"
                   class="form-control js-labels" data-autofilled="1">
        </td>
        <td><a class="btn btn-sm btn-danger table-field-remove"><i class="icon-trash"></i> remove</a></td>
    </tr>
</template>

<script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.0/Sortable.min.js"></script>

<script>

    // (function() {

    //$('select').select2();

    var el = document.getElementById('table-fields-body');
    var sortable = Sortable.create(el, {
        handle: '.move-handle'
    });

    // initialize relation count based on existing rows
    let relation_count = document.querySelectorAll('#model-relations tbody tr').length;

    document.getElementById('add-table-field').addEventListener('click', function(event) {

        let template = document.getElementById('table-field-tpl').innerHTML;
        let fieldRow = (String(template)).replace(/__index__/g, String(document.querySelectorAll('#table-fields tr').length - 1));
        let newRow = document.createElement('tr');
        newRow.innerHTML = fieldRow;
        console.log(newRow);

        document.querySelector('#table-fields-body').appendChild(newRow);
        // maybe add nice select function
    });

    document.getElementById('table-fields').addEventListener('click', function(event) {
        if (!event.target.closest('.table-field-remove')) return;
        event.target.closest('tr').remove();
    });

    if (document.getElementById('add-model-relation')) {
        // not implemented yet :-(
        document.getElementById('add-model-relation').addEventListener('click', function(event) {
            let template = document.getElementById('model-relation-tpl').innerHTML;
            let relationRow = template.replace(/__index__/g, relation_count);
            let newRow = document.createElement('tr');
            newRow.innerHTML = relationRow;

            document.querySelector('#model-relations tbody').appendChild(newRow);

            relation_count++;
        });

        document.getElementById('table-fields').querySelectorAll('.model-relation-remove').forEach(elm => {
            elm.addEventListener('click', function(event) {
                event.target.closest('tr').remove();
            });
        });
    }

    document.getElementById('scaffold').addEventListener('submit', function(event) {
        const tableInput = document.getElementById('inputTableName');
        const helpText = document.getElementById('table-name-help');

        if (!tableInput.value.trim()) {
            event.preventDefault(); // prevent only if empty
            tableInput.classList.add('is-invalid');
            helpText.classList.remove('d-none');
        } else {
            tableInput.classList.remove('is-invalid');
            helpText.classList.add('d-none');
        }
    });

    //  })();


    document.addEventListener('DOMContentLoaded', function() {
        const tableInput = document.getElementById('inputTableName');
        const modelInput = document.getElementById('inputModelName');
        const controllerInput = document.getElementById('inputControllerName');

        tableInput.addEventListener('input', function() {
            const rawValue = tableInput.value.trim();

            if (rawValue) {
                const studly = rawValue
                    .split(/[_\s-]+/)
                    .map(word => word.charAt(0).toUpperCase() + word.slice(1))
                    .join('');

                modelInput.value = `App\\Models\\${studly}`;
                controllerInput.value = `App\\Admin\\Controllers\\${studly}Controller`;
            }
        });
    });

    function reloadOnce() {
        const url = new URL(location.href);
        if (url.searchParams.get('_reloaded') === '1') return; // already reloaded
        url.searchParams.set('_reloaded', '1');
        // replace (no history entry) to avoid back-button ping-pong
        location.replace(url.toString());
    }

    reloadOnce();
</script>

