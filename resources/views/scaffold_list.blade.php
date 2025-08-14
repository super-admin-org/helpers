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
                    <th class="text-end">Actions</th>
                    <th>{!! sortLink('table_name', 'Table Name', $sort, $direction) !!}</th>
                    <th>{!! sortLink('model_name', 'Model Name', $sort, $direction) !!}</th>
                    <th>{!! sortLink('controller_name', 'Controller Name', $sort, $direction) !!}</th>
                    <th>{!! sortLink('created_at', 'Created At', $sort, $direction) !!}</th>

                </tr>
                </thead>

                <tbody>
                @forelse($scaffolds as $scaffold)
                    <tr>
                        <td>{{ $scaffold->id }}</td>
                        <td class="text-end">
                            <a href="{{ admin_url("helpers/scaffold/{$scaffold->id}/edit") }}"
                               class="btn btn-sm btn-warning">
                                <i class="icon-edit"></i> Edit
                            </a>

                            <button class="btn btn-sm btn-danger" data-delete
                                    onclick="confirmDelete({{ $scaffold->id }}, '{{ $scaffold->table_name }}', '{{ $scaffold->model_name }}', '{{ $scaffold->controller_name }}')">
                                <i class="fa fa-trash"></i> Delete
                            </button>


                        </td>
                        <td>{{ $scaffold->table_name }}</td>
                        <td>{{ $scaffold->model_name }}</td>
                        <td>{{ $scaffold->controller_name }}</td>
                        <td>{{ $scaffold->created_at->format('Y-m-d H:i') }}</td>

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
{{--<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>--}}
<script>

    function confirmDelete(id, title = '') {
        Swal.fire({
            title: 'Are you sure?',
            text: `Delete "${title}"? This cannot be undone.`,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: 'Yes, delete',
            cancelButtonText: 'Cancel',
            confirmButtonColor: '#d33',
            cancelButtonColor: '#3085d6'
        }).then((result) => {
            if (!result.isConfirmed) return;

            fetch(`{{ url('admin/helpers/scaffold') }}/${id}`, {
                method: 'DELETE',
                headers: {
                    'X-CSRF-TOKEN': '{{ csrf_token() }}',
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json'
                },
                // 👇 IMPORTANT: send cookies/session so CSRF passes
                credentials: 'same-origin'
            })
                .then(async (res) => {
                    const ct = res.headers.get('content-type') || '';
                    const isJson = ct.includes('application/json');
                    const body = isJson ? await res.json() : { message: await res.text() };

                    if (!res.ok || (isJson && body.status !== 'success')) {
                        const msg = (isJson && body.message) ? body.message : `HTTP ${res.status}`;
                        throw new Error(msg);
                    }
                    return body;
                })
                .then((data) => {
                    Swal.fire('Deleted!', data.message || 'Scaffold deleted.', 'success')
                        .then(() => location.reload());
                })
                .catch((err) => {
                    Swal.fire('Error!', err.message || 'Delete failed.', 'error');
                });
        });
    }
    function bindDeleteButtons() {
        document.querySelectorAll('[data-delete]').forEach(btn => {
            if (btn.dataset.bound) return;
            btn.dataset.bound = '1';
            btn.addEventListener('click', /* your handler */);
        });
    }

    document.addEventListener('DOMContentLoaded', bindDeleteButtons);
    document.addEventListener('pjax:end', bindDeleteButtons); // re-bind after PJAX swaps content

</script>

