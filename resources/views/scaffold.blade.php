<style>
    h4 small {
        font-size: 1rem;
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
                        <input type="text" name="table_name" class="form-control" id="inputTableName" placeholder="table name" value="{{ old('table_name', $scaffold->table_name ?? '') }}" required>
                    </div>
                    <span class="invalid-feedback" id="table-name-help">
                        <i class="icon-info"></i>&nbsp; Table name can't be empty!
                    </span>
                </div>

                <div class="row mb-3">
                    <label for="inputModelName" class="col-sm-2 col-form-label">Model</label>
                    <div class="col-sm-4">
                        <input type="text" name="model_name" class="form-control" id="inputModelName" placeholder="model" value="{{ old('model_name', $scaffold->model_name ?? 'App\\Models\\') }}">
                    </div>
                </div>

                <div class="row mb-3">
                    <label for="inputControllerName" class="col-sm-2 col-form-label">Controller</label>
                    <div class="col-sm-4">
                        <input type="text" name="controller_name" class="form-control" id="inputControllerName" placeholder="controller" value="{{ old('controller_name', $scaffold->controller_name ?? 'App\\Admin\\Controllers\\') }}">
                    </div>
                </div>

                <div class="row mb-3">
                    <div class="offset-sm-2 col-sm-10">
                        @php
                            $createOptions = old('create', $scaffold->create_options ?? []);
                        @endphp
                        @foreach(['migration', 'model', 'controller', 'migrate', 'menu_item','recreate_table'] as $option)
                            <div class="form-check form-check-inline me-3">
                                <input class="form-check-input" type="checkbox" name="create[]" value="{{ $option }}" id="{{ $option }}" {{ in_array($option, $createOptions) ? 'checked' : '' }}>
                                <label class="form-check-label" for="{{ $option }}">{{ ucfirst(str_replace('_', ' ', $option)) }}</label>
                            </div>
                        @endforeach
                    </div>
                </div>

                <hr>
                <h4>Fields <small>(Note, `id` is already included in field list)</small></h4>

                <table class="table table-hover" id="table-fields">
                    <thead>
                    <tr>
                        <th>Order</th>
                        <th>Field name</th>
                        <th>Type</th>
                        <th>Nullable</th>
                        <th>Key</th>
                        <th>Default</th>
                        <th>Comment</th>
                        <th>Action</th>
                    </tr>
                    </thead>
                    <tbody id="table-fields-body">
                    @php
                        $fields = old('fields', isset($scaffold) ? $scaffold->details->toArray() : []);
                    @endphp
                    @foreach($fields as $index => $field)
                        <tr>
                            <td><i class="icon-arrows-alt move-handle"></i></td>
                            <td><input type="text" name="fields[{{ $index }}][name]" class="form-control" value="{{ $field['name'] ?? '' }}"></td>
                            <td>
                                <select name="fields[{{ $index }}][type]" class="form-select">
                                    @foreach($dbTypes as $type)
                                        <option value="{{ $type }}" {{ ($field['type'] ?? '') == $type ? 'selected' : '' }}>{{ $type }}</option>
                                    @endforeach
                                </select>
                            </td>
                            <td><input type="checkbox" class="form-check-input" name="fields[{{ $index }}][nullable]" {{ isset($field['nullable']) && $field['nullable'] ? 'checked' : '' }}></td>
                            <td>
                                <select name="fields[{{ $index }}][key]" class="form-select">
                                    <option value="" {{ ($field['key'] ?? '') == '' ? 'selected' : '' }}>NULL</option>
                                    <option value="unique" {{ ($field['key'] ?? '') == 'unique' ? 'selected' : '' }}>Unique</option>
                                    <option value="index" {{ ($field['key'] ?? '') == 'index' ? 'selected' : '' }}>Index</option>
                                </select>
                            </td>
                            <td><input type="text" class="form-control" name="fields[{{ $index }}][default]" value="{{ $field['default'] ?? '' }}"></td>
                            <td><input type="text" class="form-control" name="fields[{{ $index }}][comment]" value="{{ $field['comment'] ?? '' }}"></td>
                            <td><a class="btn btn-sm btn-danger table-field-remove"><i class="icon-trash"></i> remove</a></td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>

                <button type="button" class="btn btn-success btn-sm" id="add-table-field"><i class="icon-plus"></i> Add field</button>

                <hr>
                <div class="d-flex align-items-center">
                    <div class="form-check me-3">
                        <input class="form-check-input" type="checkbox" id="timestamps" name="timestamps" {{ old('timestamps', $scaffold->timestamps ?? true) ? 'checked' : '' }}>
                        <label class="form-check-label" for="timestamps">Timestamps</label>
                    </div>
                    <div class="form-check me-3">
                        <input class="form-check-input" type="checkbox" id="soft-deletes" name="soft_deletes" {{ old('soft_deletes', $scaffold->soft_deletes ?? false) ? 'checked' : '' }}>
                        <label class="form-check-label" for="soft-deletes">Soft Deletes</label>
                    </div>
                    <div class="d-flex align-items-center">
                        <label class="me-2" for="inputPrimaryKey">Primary key</label>
                        <input type="text" name="primary_key" class="form-control" id="inputPrimaryKey" value="{{ old('primary_key', $scaffold->primary_key ?? 'id') }}" style="width: 120px;">
                    </div>
                </div>
            </div>

            <div class="card-footer clearfix">
                <button type="submit" class="btn btn-info float-end">{{ isset($scaffold) ? 'Update' : 'Submit' }}</button>
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
        <td><a class="btn btn-sm btn-danger table-field-remove"><i class="icon-trash"></i> remove</a></td>
    </tr>
</template>

<script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.0/Sortable.min.js"></script>

<script>

    (function () {

        //$('select').select2();

        var el = document.getElementById('table-fields-body');
        var sortable = Sortable.create(el,{
            handle: ".move-handle"
        });

        // initialize relation count based on existing rows
        let relation_count = document.querySelectorAll('#model-relations tbody tr').length;

        document.getElementById('add-table-field').addEventListener("click",function (event) {

            let template = document.getElementById('table-field-tpl').innerHTML;
            let fieldRow = (String(template)).replace(/__index__/g, String(document.querySelectorAll('#table-fields tr').length - 1));
            let newRow = document.createElement('tr');
            newRow.innerHTML = fieldRow;
            console.log(newRow);

            document.querySelector('#table-fields-body').appendChild(newRow);
            // maybe add nice select function
        });

        document.getElementById('table-fields').addEventListener("click",function(event){
            if (!event.target.closest('.table-field-remove')) return;
            event.target.closest('tr').remove();
        });

        if (document.getElementById('add-model-relation')){
            // not implemented yet :-(
            document.getElementById('add-model-relation').addEventListener("click",function (event) {
                let template = document.getElementById('model-relation-tpl').innerHTML;
                let relationRow = template.replace(/__index__/g, relation_count);
                let newRow = document.createElement('tr');
                newRow.innerHTML = relationRow;

                document.querySelector('#model-relations tbody').appendChild(newRow);

                relation_count++;
            });

            document.getElementById('table-fields').querySelectorAll('.model-relation-remove').forEach( elm => {
                elm.addEventListener("click", function(event) {
                    event.target.closest('tr').remove();
                });
            });
        }

        document.getElementById('scaffold').addEventListener('submit', function (event) {
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

    })();


    document.addEventListener('DOMContentLoaded', function () {
        const tableInput = document.getElementById('inputTableName');
        const modelInput = document.getElementById('inputModelName');
        const controllerInput = document.getElementById('inputControllerName');

        tableInput.addEventListener('input', function () {
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
</script>

