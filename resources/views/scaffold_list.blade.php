<?php /** @var \Illuminate\Pagination\LengthAwarePaginator $scaffolds */ ?>
@php
    function sortLink($column, $label, $sort, $direction) {
        $newDirection = ($sort === $column && $direction === 'asc') ? 'desc' : 'asc';
        $icon = '';
        if ($sort === $column) {
            $icon = $direction === 'asc' ? '↑' : '↓';
        }
        $query = array_merge(request()->query(), ['sort' => $column, 'direction' => $newDirection]);
        $url = request()->url() . '?' . http_build_query($query);
        return "<a href='{$url}'>{$label} {$icon}</a>";
    }
@endphp

<div class="card card-primary">
    <div class="card-header">
        <h3 class="card-title">Scaffold List</h3>
        <form method="GET" class="p-3">
            <div class="input-group">
                <input type="text" name="search" class="form-control"
                       placeholder="Search..." value="{{ request('search') }}">
                <button class="btn btn-primary" type="submit">Search</button>
                @if(request('search'))
                    <a href="{{ request()->url() }}" class="btn btn-secondary">Clear</a>
                @endif
            </div>
        </form>

        <div class="card-tools">
            <a href="{{ admin_url('helpers/scaffold/create') }}" class="btn btn-sm btn-success">
                <i class="icon-plus"></i> Create New
            </a>
        </div>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover">
                {{--                <thead>--}}
                {{--                <tr>--}}
                {{--                    <th>ID</th>--}}
                {{--                    <th>Table Name</th>--}}
                {{--                    <th>Model Name</th>--}}
                {{--                    <th>Controller Name</th>--}}
                {{--                    <th>Created At</th>--}}
                {{--                    <th class="text-end">Actions</th>--}}
                {{--                </tr>--}}
                {{--                </thead>--}}
                <thead>
                <tr>
                    <th>{!! sortLink('id', 'ID', $sort, $direction) !!}</th>
                    <th>{!! sortLink('table_name', 'Table Name', $sort, $direction) !!}</th>
                    <th>{!! sortLink('model_name', 'Model Name', $sort, $direction) !!}</th>
                    <th>{!! sortLink('controller_name', 'Controller Name', $sort, $direction) !!}</th>
                    <th>{!! sortLink('created_at', 'Created At', $sort, $direction) !!}</th>
                    <th class="text-end">Actions</th>
                </tr>
                </thead>

                <tbody>
                @forelse($scaffolds as $scaffold)
                    <tr>
                        <td>{{ $scaffold->id }}</td>
                        <td>{{ $scaffold->table_name }}</td>
                        <td>{{ $scaffold->model_name }}</td>
                        <td>{{ $scaffold->controller_name }}</td>
                        <td>{{ $scaffold->created_at->format('Y-m-d H:i') }}</td>
                        <td class="text-end">
                            <a href="{{ admin_url("helpers/scaffold/{$scaffold->id}/edit") }}"
                               class="btn btn-sm btn-warning">
                                <i class="icon-edit"></i> Edit
                            </a>

                            <button class="btn btn-sm btn-danger"
                                    onclick="confirmDelete({{ $scaffold->id }}, '{{ $scaffold->table_name }}', '{{ $scaffold->model_name }}', '{{ $scaffold->controller_name }}')">
                                <i class="fa fa-trash"></i> Delete
                            </button>


                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="text-center">No scaffold data found.</td>
                    </tr>
                @endforelse
                </tbody>
            </table>
        </div>
        <div class="card-footer clearfix">
            {{ $scaffolds->appends(request()->except('page'))->links() }}

        </div>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
    function confirmDelete(id, table, model, controller) {
        Swal.fire({
            title: 'Are you absolutely sure?',
            html: `<strong>This will permanently delete:</strong><br>
            <ul class="text-start">
                <li>Table: <code>${table}</code></li>
                <li>Model: <code>${model}</code></li>
                <li>Controller: <code>${controller}</code></li>
                <li>Migration file(s)</li>
            </ul>
            <br><span class="text-danger">Your application code and data will be affected.</span>`,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: 'Yes, delete everything!',
            cancelButtonText: 'Cancel',
            confirmButtonColor: '#d33',
            cancelButtonColor: '#3085d6'
        }).then((result) => {
            if (result.isConfirmed) {
                fetch(`{{ url('admin/helpers/scaffold') }}/${id}`, {
                    method: 'DELETE',
                    headers: {
                        'X-CSRF-TOKEN': '{{ csrf_token() }}',
                        'Accept': 'application/json'
                    }
                }).then(response => response.json())
                    .then(data => {
                        if (data.status === 'success') {
                            Swal.fire('Deleted!', data.message, 'success').then(() => {
                                location.reload();
                            });
                        } else {
                            Swal.fire('Error!', data.message, 'error');
                        }
                    });
            }
        });
    }
</script>
